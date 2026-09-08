<?php

namespace App\Services\Upnp\Handlers;

use App\Services\Upnp\AbstractUpnpHandler;
use App\Services\Upnp\SsdpDiscovery;

class GenericHandler extends AbstractUpnpHandler
{
    public function getName(): string
    {
        return 'generic';
    }

    public function matches(string $descriptionUrl, ?string $deviceXml = null): bool
    {
        // The generic handler accepts any device that exposes a WAN connection service.
        // The actual service check happens in resolveControlUrl() via findWanService().
        return true;
    }

    protected function getRouterIp(): ?string
    {
        // No known IP for a generic router.
        return null;
    }

    protected function getDescriptionPaths(): array
    {
        return [];
    }
}