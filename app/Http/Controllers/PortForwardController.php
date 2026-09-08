<?php

namespace App\Http\Controllers;

use App\Http\H;
use App\Dicts\SettingIds;
use Illuminate\Support\Facades\App;
use Spatie\Url\Url;

class PortForwardController extends Controller
{
    public function getPort($clientAdress)
    {
        $url = Url::fromString($clientAdress);
        $port = $url->getPort();
        $scheme = $url->getScheme();

        if (! $port) {
            if ($scheme == 'http') {
                $port = '80'; 
            } elseif ($scheme == 'https') {
                $port = '443';
            } else {
                $port = '80';
            }
        }

        return $port;
    }

    public function updateExternalPort(bool|null $pfm, $force = false)
    {
        info('updateExternalPort');
        $request = request();
        $requestPfm = $request->pfm;
        $requestForce = $request->force;
        
        $request->merge(['force' => $force, 'pfm' => $pfm]);
        $request->validate([
            'force' => ['boolean', 'nullable'],
            'pfm' => ['boolean', 'nullable'],
        ]);

        if (! $pfm) $pfm = $requestPfm ?? false;

        if (! $force && ! $requestForce) {
            $last_external_port_updated_ts = H::getSettVal(SettingIds::last_external_port_updated_ts);
            if ($last_external_port_updated_ts->diffInMinutes(now()) < 1) {
                H::pfm('Forwarding attempt too soon ', pfm: $pfm);
                return false;
            }
        }

        $clientPortHttp = H::getSettVal(SettingIds::client_internal_port_http);
        $clientPortHttps = H::getSettVal(SettingIds::client_internal_port_https);
        
        H::pfm('client_address_http '.$clientPortHttp, pfm: $pfm);
        H::pfm('client_address_https '.$clientPortHttps, pfm: $pfm);

        $defaultExternalPortsHttp = array_merge([8080, 80], array_values(range(15080,15089)) );
        $defaultExternalPortsHttps = array_merge([443], [8443], array_values(range(15443,15452)) );

        $forwardedHttp['success'] = false;
        $forwardedHttps['success'] = false;

        if (H::getSettVal(SettingIds::port_forwarding_enabled)) {
            foreach ($defaultExternalPortsHttp as $key => $port) {
                H::pfm('Http Trying - External: '.$port.' | Internal: '.$clientPortHttp, pfm: $pfm);
                $forwardedHttp = (new PortForwardController())->portForward(externalPort: $port, internalPort: $clientPortHttp, pfm: $pfm);
                if ($forwardedHttp['success'] === true) {
                    H::pfm('Success: Http forwarded - External: '.$port.' | Internal: '.$clientPortHttp, pfm: $pfm);
                    H::setSettingValue(SettingIds::client_external_port_http, $port);
                    H::setSettingValue(SettingIds::http_port_forwarded, true);
                    break;
                } else {
                    if ($forwardedHttp['retriable'] !== true) {
                        H::pfm('Failure: Http Not forwarded. Giving up.', pfm: $pfm);
                        break;
                    }
                }
            }
            foreach ($defaultExternalPortsHttps as $key => $port) {
                H::pfm('Https Trying - External: '.$port.' | Internal: '.$clientPortHttps, pfm: $pfm);
                $forwardedHttps = (new PortForwardController())->portForward($port, $clientPortHttps, pfm: $pfm);
                if ($forwardedHttps['success'] === true) {
                    H::pfm('Success: Https forwarded - External: '.$port.' | Internal: '.$clientPortHttps, pfm: $pfm);
                    H::setSettingValue(SettingIds::client_external_port_https, $port);
                    H::setSettingValue(SettingIds::https_port_forwarded, true);
                    break;
                } else {
                    if ($forwardedHttps['retriable'] !== true) {
                        H::pfm('Failure: Https Not forwarded. Giving up.', pfm: $pfm);
                        break;
                    }
                }
            }
        } else {
            H::pfm('Forwarding disabled by Setting', pfm: $pfm);
        }

        if (! $forwardedHttp['success'] === true) {
            H::setSettingValue(SettingIds::client_external_port_http, $clientPortHttp);
            H::setSettingValue(SettingIds::http_port_forwarded, false);
            H::pfm('Port forward unsuccessful', pfm: $pfm);
        }

        if (! $forwardedHttps['success'] === true) {
            H::setSettingValue(SettingIds::client_external_port_https, $clientPortHttps);
            H::setSettingValue(SettingIds::https_port_forwarded, false);
            H::pfm('Https Port forward unsuccessful', pfm: $pfm);
        }

        H::setSettingValue(SettingIds::last_external_port_updated_ts, now());
    }

    public function portForward($externalPort, $internalPort, $pfm = false)
    {
        $durationSeconds = 600 + 160;

        if (config('sn.is_off_network_node'))
        $durationSeconds = 3;

        $portForwardResult = (new SSDPController(pfm: $pfm))->setupPortForward($externalPort, $internalPort, $durationSeconds, pfm: $pfm);
        return $portForwardResult;
    }
}
