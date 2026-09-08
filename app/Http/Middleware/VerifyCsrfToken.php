<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'complete_first_run',
        'p2p_api/*',
        'internal/*',
        'site_api/v1/api_*',

        'dev/*',
        
        'test',
    ];
}
