<?php

namespace App\Http\Middleware;

use App\Http\H;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SingleSiteId
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (H::inSingleSiteMode($request)) {
            $request->merge([
                'site_id' => config('single_site_mode.sites')[$request->getHost()]
            ]);
        }
        return $next($request);
    }
}
