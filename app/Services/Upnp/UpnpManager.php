<?php

namespace App\Services\Upnp;

use App\Dicts\SettingIds;
use App\Http\H;
use App\Services\Upnp\Handlers\AsusHandler;
use App\Services\Upnp\Handlers\GenericHandler;
use App\Services\Upnp\Handlers\TplinkHandler;
use Illuminate\Support\Facades\Log;

class UpnpManager
{
    private SsdpDiscovery $discovery;
    private bool $pfm;

    /**
     * Ordered list of router handlers to try.
     * The generic handler is always last as a fallback.
     *
     * @var array<int, class-string<AbstractUpnpHandler>>
     */
    private array $handlerClasses = [
        GenericHandler::class,
        AsusHandler::class,
        TplinkHandler::class,
    ];

    /**
     * The active handler that successfully discovered a router.
     */
    private ?AbstractUpnpHandler $activeHandler = null;

    public function __construct(bool $pfm)
    {
        $this->pfm = $pfm;
        $this->discovery = new SsdpDiscovery($pfm);
    }

    /**
     * Discover the router and set up the active handler.
     *
     * SSDP multicast is performed exactly once; the resulting LOCATION URLs
     * are shared with every handler so we don't re-multicast per handler.
     * Tries each handler in order until one succeeds.
     */
    public function discover(): bool
    {
        $locations = $this->discovery->discoverGatewayDevices();
        Log::debug('UPNP manager: SSDP discovery results', [
            'count' => count($locations),
            'locations' => $locations,
        ]);

        foreach ($this->handlerClasses as $handlerClass) {
            /** @var AbstractUpnpHandler $handler */
            $handler = new $handlerClass($this->discovery, $this->pfm);
            H::pfm('UpnpHandler Name: '.$handler->getName(), pfm: $this->pfm);
            if ($handler->discoverWithLocations($locations) !== null) {
                $this->activeHandler = $handler;
                return true;
            }
        }

        return false;
    }

    /**
     * Get the active handler.
     */
    public function getActiveHandler(): ?AbstractUpnpHandler
    {
        return $this->activeHandler;
    }

    /**
     * Get the name of the active router handler.
     */
    public function getActiveRouterName(): ?string
    {
        return $this->activeHandler?->getName();
    }

    /**
     * Get the external IP address of the router.
     */
    public function getExternalIpAddress(): ?string
    {
        return $this->activeHandler?->getExternalIpAddress();
    }

    /**
     * Set up a port forward.
     */
    public function setupPortForward(
        int $externalPort,
        int $internalPort,
        int $durationSeconds = 0,
        bool $pfm = false
    ): array {
        if (! $this->activeHandler) {
            return ['retriable' => false, 'success' => false, 'stage' => 'discovery'];
        }

        // Get external IP (also captures local IP for internal client).
        $externalIp = $this->activeHandler->getExternalIpAddress();
        H::pfm('$externalIp: '.$externalIp, pfm: $pfm);
        if (! $externalIp && $pfm) {
            return ['retriable' => false, 'success' => false, 'stage' => 'ExternalIpFromSsdp'];
        }

        $defaults = config('upnp.defaults', []);
        $defaults['description'] = 'Serveronet '.H::getSettVal(SettingIds::peer_random_id);
        H::pfm('Trying to add port mapping', pfm: $pfm);
        return $this->activeHandler->addPortMapping(
            externalPort: (string) $externalPort,
            internalPort: (string) $internalPort,
            protocol: $defaults['protocol'] ?? 'TCP',
            description: $defaults['description'] ?? 'Serveronet',
            duration: $durationSeconds,
            pfm: $pfm,
        );
    }

    /**
     * Remove a port forward.
     */
    public function removePortForward(int $externalPort, string $protocol = 'TCP'): array
    {
        if (!$this->activeHandler) {
            return ['retriable' => false, 'success' => false, 'stage' => 'discovery'];
        }

        return $this->activeHandler->deletePortMapping((string) $externalPort, $protocol);
    }
}