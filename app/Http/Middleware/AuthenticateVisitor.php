<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class AuthenticateVisitor extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        $site_id = $request->site_id;

        if (! $request->expectsJson()) {
            return domainRoute('login', ['site_id' => $site_id]);
        }
    }
}
