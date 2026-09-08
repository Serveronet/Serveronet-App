<?php

namespace App\Http\Controllers;

use App\Services\Upnp\UpnpManager;

class SSDPController extends Controller
{
    private UpnpManager $upnpManager;
    private bool $ssdpSetupSuccessful;

    public function __construct(bool $pfm)
    {
        $this->upnpManager = new UpnpManager($pfm);
        $this->ssdpSetupSuccessful = false;

        try {
            $this->ssdpSetupSuccessful = $this->upnpManager->discover();
        } catch (\Throwable $th) {
            $this->ssdpSetupSuccessful = false;
        }
    }

    /**
     * Set up a UPnP port forward on the discovered router.
     *
     * Keeps the original method signature for backward compatibility.
     */
    public function setupPortForward($externalPort, $internalPort, $durationSeconds, $pfm = false): array
    {
        if (! $this->ssdpSetupSuccessful) {
            return ['retriable' => false, 'success' => false, 'stage' => 'ssdpSetup'];
        }

        return $this->upnpManager->setupPortForward(
            externalPort: (int) $externalPort,
            internalPort: (int) $internalPort,
            durationSeconds: (int) $durationSeconds,
            pfm: $pfm,
        );
    }

    /**
     * Remove a previously created port forward.
     */
    public function removePortForward($externalPort, $protocol = 'TCP'): array
    {
        if (!$this->ssdpSetupSuccessful) {
            return ['retriable' => false, 'success' => false, 'stage' => 'ssdpSetup'];
        }

        return $this->upnpManager->removePortForward((int) $externalPort, $protocol);
    }

    /**
     * Get the external IP address of the router.
     */
    public function getExternalIp(): ?string
    {
        if (!$this->ssdpSetupSuccessful) {
            return null;
        }

        return $this->upnpManager->getExternalIpAddress();
    }

    /**
     * Get the name of the active router handler (e.g. 'tplink', 'asus', 'generic').
     */
    public function getActiveRouterName(): ?string
    {
        if (!$this->ssdpSetupSuccessful) {
            return null;
        }

        return $this->upnpManager->getActiveRouterName();
    }
}