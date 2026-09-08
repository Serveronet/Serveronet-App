<?php

namespace App\Http\Middleware;

use App\Http\H;
use Closure;
use Illuminate\Http\Request;

class EnsureFirstRunCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request):
     *  (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (! H::isFirstRunCompleted()) {
            abort(503);
        }

        return $next($request);
    }
}
