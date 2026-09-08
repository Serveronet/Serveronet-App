<?php

namespace App\Services\Upnp\Handlers;

use App\Services\Upnp\AbstractUpnpHandler;
use Illuminate\Support\Str;

class TplinkHandler extends AbstractUpnpHandler
{
    public function getName(): string
    {
        return 'tplink';
    }

    public function matches(string $descriptionUrl, ?string $deviceXml = null): bool
    {
        $urlLower = strtolower($descriptionUrl);

        // 1) Strong signal: brand markers in the URL or device description.
        if (Str::contains($urlLower, ['tplink', 'tp-link', 'tl-'])) {
            return true;
        }

        if ($deviceXml && Str::contains(strtolower($deviceXml), ['tplink', 'tp-link', 'archer', 'deco', 'tl-', 'tp link'])) {
            return true;
        }

        // 2) TP-Link routers commonly expose UPnP on 192.168.2.1 with no
        //    brand in the XML. Only accept the WAN-service fallback when the
        //    description URL points at the configured TP-Link IP — this avoids
        //    the handler greedily grabbing an Asus (or any other) router that
        //    also happens to expose a WANIPConnection service.
        $configuredIp = strtolower((string) $this->getRouterIp());
        if ($configuredIp !== '' && Str::contains($urlLower, '://' . $configuredIp)) {
            if ($deviceXml && Str::contains(strtolower($deviceXml), ['wanipconnection', 'wanpppconnection'])) {
                return true;
            }
            // Direct-IP probe with no XML yet: accept so trySetupFromLocation
            // can fetch and validate the description.
            if ($deviceXml === null) {
                return true;
            }
        }

        return false;
    }

    protected function getRouterIp(): ?string
    {
        return config('upnp.routers.tplink.ip');
    }

    protected function getDescriptionPaths(): array
    {
        return config('upnp.routers.tplink.description_paths', [
            // Common TP-Link (Archer / Deco) description paths.
            '/gw.xml',
            '/igd.xml',
            '/device.xml',
            '/root.xml',
            '/xml/IGD.xml',
            '/upnp/Igd.xml',
            '/upnp/control/WANIPConn1',
            '/desc.xml',
            '/rootDesc.xml',
            '/data/ModelInfo.xml',
        ]);
    }
}
