<?php

namespace App\Services\Upnp;

use Illuminate\Support\Facades\Http;

class SsdpDiscovery
{
    private string $multicastIp;
    private int $multicastPort;
    private int $timeout;
    private int $maxResults;

    public function __construct(bool $pfm)
    {
        $config = config('upnp.ssdp', []);
        $this->multicastIp = $config['multicast_ip'] ?? '239.255.255.250';
        $this->multicastPort = $config['multicast_port'] ?? 1900;
        $this->timeout = $config['timeout'] ?? 3;
        $this->maxResults = $config['max_results'] ?? 10;
    }

    /**
     * Discover UPnP InternetGatewayDevice devices on the network via SSDP multicast.
     * Returns an array of discovered device description URLs (LOCATION headers).
     */
    public function discoverGatewayDevices(): array
    {
        $request = implode("\r\n", [
            'M-SEARCH * HTTP/1.1',
            'Host: ' . $this->multicastIp . ':' . $this->multicastPort,
            'ST: urn:schemas-upnp-org:device:InternetGatewayDevice:1',
            'Man: "ssdp:discover"',
            'MX: 3',
            '',
            '',
        ]);

        $sock = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if ($sock === false) {
            return [];
        }

        socket_set_option($sock, SOL_SOCKET, SO_BROADCAST, 1);
        socket_set_option($sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        $sent = @socket_sendto($sock, $request, strlen($request), 0, $this->multicastIp, $this->multicastPort);
        if ($sent === false) {
            socket_close($sock);
            return [];
        }

        $locations = [];
        $deadline = microtime(true) + $this->timeout;

        while (count($locations) < $this->maxResults && microtime(true) < $deadline) {
            $fromIp = '';
            $fromPort = 0;
            $bytes = @socket_recvfrom($sock, $buf, 4096, 0, $fromIp, $fromPort);
            if ($bytes === false || $bytes === 0) {
                break;
            }
            $location = $this->extractLocation($buf);
            if ($location && !in_array($location, $locations)) {
                $locations[] = $location;
            }
        }

        socket_close($sock);
        return $locations;
    }

    /**
     * Extract the LOCATION header value from an SSDP response.
     */
    private function extractLocation(string $response): ?string
    {
        $lines = explode("\r\n", $response);
        foreach ($lines as $line) {
            if (stripos($line, 'LOCATION:') === 0) {
                return trim(substr($line, strlen('LOCATION:')));
            }
        }
        return null;
    }

    /**
     * Fetch the device description XML from a LOCATION URL.
     */
    public function fetchDescription(string $locationUrl): ?string
    {
        try {
            $response = Http::timeout(5)->get($locationUrl);
            if ($response->successful()) {
                return $response->body();
            }
        } catch (\Throwable $e) {
        }
        return null;
    }
}