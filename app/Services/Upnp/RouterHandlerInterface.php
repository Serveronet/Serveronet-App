<?php

namespace App\Services\Upnp;

interface RouterHandlerInterface
{
    /**
     * Attempt to discover the router's UPnP description URL.
     * Returns the description URL on success, or null on failure.
     */
    public function discover(): ?string;

    /**
     * Discovery flow that reuses a pre-fetched list of SSDP LOCATION URLs,
     * so the manager can multicast once and share results across handlers.
     */
    public function discoverWithLocations(array $locations): ?string;

    /**
     * Get the router's identifier (e.g. 'tplink', 'asus').
     */
    public function getName(): string;

    /**
     * Whether this handler can handle the given discovered device.
     * Used when SSDP discovery returns a device that matches a handler.
     */
    public function matches(string $descriptionUrl, ?string $deviceXml = null): bool;
}