<?php

namespace App\Services\Upnp\Handlers;

use App\Services\Upnp\AbstractUpnpHandler;
use App\Services\Upnp\SsdpDiscovery;
use Illuminate\Support\Str;

class AsusHandler extends AbstractUpnpHandler
{
    public function getName(): string
    {
        return 'asus';
    }

    public function matches(string $descriptionUrl, ?string $deviceXml = null): bool
    {
        // Match by URL host or by device description content.
        if (Str::contains(strtolower($descriptionUrl), ['asus', 'asuswrt'])) {
            return true;
        }

        if ($deviceXml && Str::contains(strtolower($deviceXml), ['asus', 'asuswrt', 'rt-ac', 'rt-ax'])) {
            return true;
        }

        return false;
    }

    protected function getRouterIp(): ?string
    {
        return config('upnp.routers.asus.ip');
    }

    protected function getDescriptionPaths(): array
    {
        return config('upnp.routers.asus.description_paths', [
            '/gadget.xml',
            '/upnp/control.xml',
        ]);
    }
}