<?php

namespace App\Services\Upnp;

use App\Http\H;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

abstract class AbstractUpnpHandler implements RouterHandlerInterface
{
    protected SsdpDiscovery $discovery;

    /**
     * Resolved device description URL (LOCATION).
     */
    protected ?string $descriptionUrl = null;

    /**
     * Parsed device description XML (SimpleXMLElement).
     */
    protected ?\SimpleXMLElement $descriptionXml = null;

    /**
     * Root URL (scheme://host:port) derived from the description URL.
     */
    protected ?string $rootUrl = null;

    /**
     * Control URL path for the WAN connection service.
     */
    protected ?string $controlPath = null;

    /**
     * Full control URL (rootUrl + controlPath).
     */
    protected ?string $controlUrl = null;

    /**
     * Service type URN for the WAN connection service.
     */
    protected ?string $serviceType = null;

    /**
     * Local IP used to reach the router (captured from HTTP handler stats).
     */
    protected ?string $localIp = null;

    protected bool $pfm = false;

    public function __construct(SsdpDiscovery $discovery, bool $pfm)
    {
        $this->pfm = $pfm;
        $this->discovery = $discovery;
    }

    /**
     * Full discovery flow: SSDP multicast -> fetch description -> parse control URL.
     * Falls back to direct IP probing when SSDP yields nothing.
     */
    public function discover(): ?string
    {
        return $this->discoverWithLocations($this->discovery->discoverGatewayDevices());
    }

    /**
     * Discovery flow that reuses a pre-fetched list of SSDP LOCATION URLs.
     *
     * This lets the UpnpManager run SSDP multicast exactly once and share the
     * results across every handler instead of re-multicasting per handler.
     */
    public function discoverWithLocations(array $locations): ?string
    {
        // 1) Try each SSDP-discovered LOCATION.
        foreach ($locations as $location) {
            H::pfm('discoverWithLocations: '.$location, pfm: $this->pfm);
            if ($this->trySetupFromLocation($location)) {
                Log::debug('UPNP discover: matched via SSDP', [
                    'handler' => $this->getName(),
                    'location' => $location,
                ]);
                return $this->descriptionUrl;
            }
        }

        // 2) Fall back to direct IP probing for this router brand.
        H::pfm('probeDirectIp', pfm: $this->pfm);
        if ($this->probeDirectIp()) {
            Log::debug('UPNP discover: matched via direct IP probe', [
                'handler' => $this->getName(),
                'descriptionUrl' => $this->descriptionUrl,
            ]);
            return $this->descriptionUrl;
        }

        Log::debug('UPNP discover: no match', ['handler' => $this->getName()]);
        return null;
    }

    /**
     * Attempt to set up the handler from a discovered LOCATION URL.
     */
    protected function trySetupFromLocation(string $locationUrl): bool
    {
        $xml = $this->discovery->fetchDescription($locationUrl);
        if (!$xml) {
            return false;
        }

        $rawXml = $xml;

        // Strip the default XML namespace so that SimpleXML property traversal
        // (e.g. $node->serviceList->service) works uniformly across routers.
        // Many routers (TP-Link, etc.) declare xmlns="urn:schemas-upnp-org:device-1-0"
        // which makes SimpleXML require namespace-aware access otherwise.
        $xml = preg_replace('/xmlns="[^"]*"/', '', $xml, 1);

        // Use instanceof rather than a boolean cast: a namespaced root element
        // with no text content evaluates to false even when parsing succeeded.
        $useErrors = libxml_use_internal_errors(true);
        $parsed = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        if (!$parsed instanceof \SimpleXMLElement) {
            return false;
        }

        if (!$this->matches($locationUrl, $rawXml)) {
            return false;
        }

        $this->descriptionUrl = $locationUrl;
        $this->descriptionXml = $parsed;
        $this->rootUrl = $this->deriveRootUrl($locationUrl);

        return $this->resolveControlUrl();
    }

    /**
     * Probe the router's known IP directly, trying common description paths.
     */
    protected function probeDirectIp(): bool
    {
        $ip = $this->getRouterIp();
        if (!$ip) {
            return false;
        }

        $paths = $this->getDescriptionPaths();
        foreach ($paths as $path) {
            $location = 'http://' . $ip . $path;
            if ($this->trySetupFromLocation($location)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Derive scheme://host:port from a URL.
     */
    protected function deriveRootUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'http';
        $host = $parts['host'] ?? '';
        $port = $parts['port'] ?? null;

        $root = $scheme . '://' . $host;
        if ($port) {
            $root .= ':' . $port;
        }
        return $root;
    }

    /**
     * Walk the device description XML looking for a WANIPConnection or
     * WANPPPConnection service, then capture its controlURL + serviceType.
     */
    protected function resolveControlUrl(): bool
    {
        if (!$this->descriptionXml) {
            return false;
        }

        $service = $this->findWanService($this->descriptionXml);
        if (!$service) {
            return false;
        }

        $this->serviceType = (string) $service->serviceType;
        $this->controlPath = (string) $service->controlURL;
        $this->controlUrl = $this->rootUrl . $this->controlPath;

        return true;
    }

    /**
     * Recursively search the device tree for a WAN connection service.
     */
    protected function findWanService(\SimpleXMLElement $node): ?\SimpleXMLElement
    {
        // Check services directly under this node.
        if (isset($node->serviceList->service)) {
            foreach ($node->serviceList->service as $service) {
                $type = (string) $service->serviceType;
                if (Str::contains($type, ['WANIPConnection', 'WANPPPConnection'])) {
                    return $service;
                }
            }
        }

        // Recurse into child devices.
        if (isset($node->deviceList->device)) {
            foreach ($node->deviceList->device as $child) {
                $found = $this->findWanService($child);
                if ($found) {
                    return $found;
                }
            }
        }

        // Fallback: some routers nest differently; try generic children.
        foreach ($node->children() as $child) {
            if ($child->getName() === 'device' || $child->getName() === 'service') {
                $found = $this->findWanService($child);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Build a well-formed SOAP envelope body.
     */
    protected function buildSoapEnvelope(string $action, string $serviceType, string $bodyContent): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            . '<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' s:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">'
            . '<s:Body>'
            . '<u:' . $action . ' xmlns:u="' . $serviceType . '">'
            . $bodyContent
            . '</u:' . $action . '>'
            . '</s:Body>'
            . '</s:Envelope>';
    }

    /**
     * Send a SOAP request to the router's control URL.
     */
    protected function sendSoapRequest(string $action, string $serviceType, string $bodyContent): ?string
    {
        if (!$this->controlUrl) {
            Log::debug('UPNP sendSoapRequest: no controlUrl');
            return null;
        }

        $envelope = $this->buildSoapEnvelope($action, $serviceType, $bodyContent);

        try {
            $response = Http::withBody($envelope, 'text/xml')
                ->withHeaders([
                    'SOAPAction' => '"' . $serviceType . '#' . $action . '"',
                    'Content-Type' => 'text/xml; charset="utf-8"',
                ])
                ->timeout(10)
                ->post($this->controlUrl);
        } catch (\Throwable $e) {
            Log::debug('UPNP sendSoapRequest: HTTP exception', [
                'action' => $action,
                'controlUrl' => $this->controlUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        Log::debug('UPNP sendSoapRequest: response', [
            'action' => $action,
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body_length' => strlen($response->body()),
        ]);

        if (!$response->successful()) {
            return null;
        }

        // Capture the local IP used to reach the router.
        try {
            $stats = $response->transferStats->getHandlerStats();
            if (isset($stats['local_ip'])) {
                $this->localIp = $stats['local_ip'];
            }
        } catch (\Throwable $e) {
        }

        return $response->body();
    }

    /**
     * Get the external IP address of the router via SOAP.
     */
    public function getExternalIpAddress(): ?string
    {
        if (!$this->controlUrl || !$this->serviceType) {
            Log::debug('UPNP getExternalIpAddress: missing setup', [
                'controlUrl' => $this->controlUrl,
                'serviceType' => $this->serviceType,
            ]);
            return null;
        }

        $action = 'GetExternalIPAddress';
        $body = '';

        $result = $this->sendSoapRequest($action, $this->serviceType, $body);
        if (!$result) {
            Log::debug('UPNP getExternalIpAddress: sendSoapRequest returned null');
            return null;
        }

        $ip = $this->extractFromSoapResponse($result, 'NewExternalIPAddress');
        if ($ip !== null) {
            return $ip;
        }

        Log::debug('UPNP getExternalIpAddress: could not extract IP', [
            'result' => $result,
        ]);

        return null;
    }

    /**
     * Extract a single element value from a SOAP response.
     *
     * Handles the common SimpleXML namespace quirk where a valid object
     * that contains only namespaced children evaluates to a falsy boolean.
     * Falls back to a regex when XML parsing yields nothing.
     */
    protected function extractFromSoapResponse(string $result, string $elementName): ?string
    {
        // Suppress libxml errors and parse. Note: we must NOT use a boolean
        // cast (e.g. `if (!$xml)`) to validate the result, because a
        // SimpleXMLElement whose root has no direct text content (very common
        // for SOAP Envelope responses using namespace prefixes like SOAP-ENV:)
        // evaluates to false even though it is a perfectly valid object.
        $useErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($result);
        libxml_clear_errors();
        libxml_use_internal_errors($useErrors);

        if ($xml instanceof \SimpleXMLElement) {
            // Register the SOAP envelope namespace so XPath works regardless
            // of the prefix the router chose (s:, SOAP-ENV:, soap:, etc.).
            $xml->registerXPathNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
            if ($this->serviceType) {
                $xml->registerXPathNamespace('u', $this->serviceType);
            }

            $nodes = $xml->xpath('//' . $elementName);
            if (!empty($nodes)) {
                $value = trim((string) $nodes[0]);
                if ($value !== '') {
                    return $value;
                }
            }
        }

        // Regex fallback: robust against any XML namespace / encoding oddity.
        if (preg_match('/<' . preg_quote($elementName, '/') . '[^>]*>([^<]+)<\/' . preg_quote($elementName, '/') . '>/', $result, $m)) {
            $value = trim($m[1]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Add a port mapping via SOAP AddPortMapping.
     */
    public function addPortMapping(
        string $externalPort,
        string $internalPort,
        ?string $internalClient = null,
        string $protocol = 'TCP',
        string $description = 'Serveronet',
        int $duration = 0,
        bool $pfm = false
    ): array {
        if (!$this->controlUrl || !$this->serviceType) {
            return ['retriable' => false, 'success' => false, 'stage' => 'setup'];
        }

        // Determine the internal client IP if not provided.
        if (!$internalClient) {
            if (!$this->localIp) {
                // Trigger a request to capture local IP from transfer stats.
                $this->getExternalIpAddress();
            }
            if (!$this->localIp) {
                // Fallback: derive the local IP by opening a socket to the router.
                $this->localIp = $this->detectLocalIp();
            }
            $internalClient = $this->localIp;
        }

        if (!$internalClient) {
            H::pfm('UPNP addPortMapping: could not determine internal client IP', pfm: $pfm);
            Log::debug('UPNP addPortMapping: could not determine internal client IP');
            return ['retriable' => false, 'success' => false, 'stage' => 'internalClient'];
        }

        $action = 'AddPortMapping';
        $body = implode('', [
            '<NewRemoteHost></NewRemoteHost>',
            '<NewExternalPort>' . htmlspecialchars($externalPort) . '</NewExternalPort>',
            '<NewProtocol>' . htmlspecialchars($protocol) . '</NewProtocol>',
            '<NewInternalPort>' . htmlspecialchars($internalPort) . '</NewInternalPort>',
            '<NewInternalClient>' . htmlspecialchars($internalClient) . '</NewInternalClient>',
            '<NewEnabled>1</NewEnabled>',
            '<NewPortMappingDescription>' . htmlspecialchars($description) . '</NewPortMappingDescription>',
            '<NewLeaseDuration>' . intval($duration) . '</NewLeaseDuration>',
        ]);

        $result = $this->sendSoapRequest($action, $this->serviceType, $body);
        if ($result === null) {
            return ['retriable' => true, 'success' => false, 'stage' => 'request'];
        }

        $evaluation = $this->evaluateSoapResponse($action, $result);
        return [
            'retriable' => true,
            'success' => $evaluation['success'],
            'stage' => 'AddPortMapping',
            'fault' => $evaluation['fault'] ?? null,
            'response' => $evaluation['success'] ? null : $result,
        ];
    }

    /**
     * Delete a port mapping via SOAP DeletePortMapping.
     */
    public function deletePortMapping(string $externalPort, string $protocol = 'TCP'): array
    {
        if (!$this->controlUrl || !$this->serviceType) {
            return ['retriable' => false, 'success' => false, 'stage' => 'setup'];
        }

        $action = 'DeletePortMapping';
        $body = implode('', [
            '<NewRemoteHost></NewRemoteHost>',
            '<NewExternalPort>' . htmlspecialchars($externalPort) . '</NewExternalPort>',
            '<NewProtocol>' . htmlspecialchars($protocol) . '</NewProtocol>',
        ]);

        $result = $this->sendSoapRequest($action, $this->serviceType, $body);
        if ($result === null) {
            return ['retriable' => true, 'success' => false, 'stage' => 'request'];
        }

        $evaluation = $this->evaluateSoapResponse($action, $result);
        return [
            'retriable' => true,
            'success' => $evaluation['success'],
            'stage' => 'DeletePortMapping',
            'fault' => $evaluation['fault'] ?? null,
            'response' => $evaluation['success'] ? null : $result,
        ];
    }

    /**
     * Evaluate a SOAP action response for success/failure.
     *
     * A response is considered successful when it contains the action's
     * response element (e.g. <u:AddPortMappingResponse>) and no SOAP fault.
     * Some routers (TP-Link among them) return an empty-body 200 response
     * with only the response element and no faultcode — that is success.
     */
    protected function evaluateSoapResponse(string $action, string $result): array
    {
        $lower = strtolower($result);

        // Explicit SOAP fault -> failure.
        if (Str::contains($lower, ['faultcode', 'faultstring', '<s:fault', 'upnperror'])) {
            $fault = $this->extractFromSoapResponse($result, 'faultstring')
                ?? $this->extractFromSoapResponse($result, 'errorDescription')
                ?? 'SOAP fault';
            return ['success' => false, 'fault' => $fault];
        }

        // Success: response element for the action is present.
        if (Str::contains($lower, [strtolower($action) . 'response', strtolower($action)])) {
            return ['success' => true];
        }

        // HTTP 200 with a non-empty body but no recognizable markers — treat
        // as success (some routers return a bare envelope with no body element).
        if (Str::contains($lower, ['envelope', 'body'])) {
            return ['success' => true];
        }

        return ['success' => false, 'fault' => 'Unrecognized response'];
    }

    /**
     * Detect the local IP used to reach the router by opening a UDP socket.
     *
     * This is a robust fallback when Guzzle's transferStats handler stats
     * don't expose a 'local_ip' entry (common with the cURL handler).
     */
    protected function detectLocalIp(): ?string
    {
        $host = parse_url($this->controlUrl ?? '', PHP_URL_HOST);
        $port = parse_url($this->controlUrl ?? '', PHP_URL_PORT) ?: 80;
        if (!$host) {
            return null;
        }

        $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock === false) {
            return null;
        }

        // socket_connect on a UDP socket doesn't send packets but lets us
        // query the local address chosen by the routing table.
        if (!@socket_connect($sock, $host, $port)) {
            socket_close($sock);
            return null;
        }

        $localIp = '';
        if (@socket_getsockname($sock, $localIp)) {
            socket_close($sock);
            return $localIp ?: null;
        }

        socket_close($sock);
        return null;
    }

    /**
     * Get the router's configured IP from config.
     */
    abstract protected function getRouterIp(): ?string;

    /**
     * Get the list of description XML paths to probe on the router's IP.
     */
    abstract protected function getDescriptionPaths(): array;
}