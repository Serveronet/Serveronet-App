<?php

use App\Http\H;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

if (! function_exists('tfyn')) { 
    function tfyn(bool|object|null $b) {
        $b ? $s = 'Yes' : $s = 'No';
        return $s;
    }
}

if (! function_exists('ty')) { 
    function ty(bool|null $b) {
        $b ? $s = 'Yes' : $s = '';
        return $s;
    }
}

if (! function_exists('getDomain')) {
    function getDomain() : string {
        $uiDomains = H::getUiDomains();
        $host = request()->getHost();

        foreach ($uiDomains as $key => $uiDomain) {
            if (Str::endsWith($host, $uiDomain))
            return $uiDomain;
        }
        $host = (filter_var($host, FILTER_VALIDATE_IP) ? '' : $host);
        return $host;
    }
}

if (! function_exists('domainRoute')) {
    function domainRoute($route, $parameters = [], $status = 302, $headers = [], $subdomainLevelOverride = null) 
    {
        if (H::inSingleSiteMode(request())) {
            return route('0'.request()->getHost().$route, $parameters);
        }
        $hostIsIp = filter_var(request()->getHost(), FILTER_VALIDATE_IP);
        if ($hostIsIp) {
            return route($route, $parameters);
        } else {
            $isValidDomain = filter_var(request()->getHost(), FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
            if ($isValidDomain) {
                $prefix = H::subdomainLevel($subdomainLevelOverride);
                $routeName = $prefix.getDomain().''.$route;
    
                if (Route::has($routeName)) { 
                    return route($routeName, $parameters);
                } else {
                    $parameters = [];
                    return route('ui_fallback', $parameters);
                }
            } else {
                return route($route, $parameters);
            }
        }
    } 
}

if (! function_exists('domainRouteIs')) {
    function domainRouteIs($routeName) 
    {
        return domainRoute($routeName) === request()->fullUrl();
    }
}

