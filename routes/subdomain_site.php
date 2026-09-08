<?php

use App\Http\H;
use Illuminate\Support\Facades\Route;

$SERVE_OWNER_ONLY = config('sn.serve_owner_only');
$owner_only_capable = $SERVE_OWNER_ONLY ? ['auth_owner:owner'] : [];

foreach (H::getSingleSiteDomains() as $key => $domain) {
    $subdomainLevel = 0;
    Route::domain($domain)->group(function ($request)
    use ($SERVE_OWNER_ONLY, $owner_only_capable, $domain, $subdomainLevel) {
        require __DIR__ . '/subdomain_include.php';
    });
}

$uiDomains = H::getUiDomains();

foreach ($uiDomains as $key => $domain) {

    $subdomainLevel = 5;
    Route::domain('{subdomain4?}.{subdomain3?}.{subdomain2?}.{subdomain1?}.{site_id}.' . $domain)->group(function ($request)
    use ($SERVE_OWNER_ONLY, $owner_only_capable, $domain, $subdomainLevel) {
        require __DIR__ . '/subdomain_include.php';
    });

    $subdomainLevel = 4;
    Route::domain('{subdomain3?}.{subdomain2?}.{subdomain1?}.{site_id}.' . $domain)->group(function ($request)
    use ($SERVE_OWNER_ONLY, $owner_only_capable, $domain, $subdomainLevel) {
        require __DIR__ . '/subdomain_include.php';
    });

    $subdomainLevel = 3;
    Route::domain('{subdomain2?}.{subdomain1?}.{site_id}.' . $domain)->group(function ($request)
    use ($SERVE_OWNER_ONLY, $owner_only_capable, $domain, $subdomainLevel) {
        require __DIR__ . '/subdomain_include.php';
    });

    $subdomainLevel = 2;
    Route::domain('{subdomain1?}.{site_id}.' . $domain)->group(function ($request)
    use ($SERVE_OWNER_ONLY, $owner_only_capable, $domain, $subdomainLevel) {
        require __DIR__ . '/subdomain_include.php';
    });

    $subdomainLevel = 1;
    Route::domain('{site_id}.' . $domain)->group(function ()
    use ($SERVE_OWNER_ONLY, $owner_only_capable, $domain, $subdomainLevel) {
        require __DIR__ . '/subdomain_include.php';
    });
    
};
