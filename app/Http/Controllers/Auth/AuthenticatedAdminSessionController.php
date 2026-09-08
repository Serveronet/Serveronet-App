<?php

namespace App\Http\Controllers\Auth;

use App\Dicts\CachePrefixes;
use App\Http\Consts;
use App\Http\Controllers\Controller;
use App\Http\H;
use App\Http\Requests\Auth\AdminLoginRequest;
use App\Models\Owner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cookie;

use Illuminate\Support\Facades\Crypt;

class AuthenticatedAdminSessionController extends Controller
{
    /**
     * Display the login view.
     *
     * @return \Illuminate\View\View
     */
    public function create(Request $request)
    {
        $password = H::isDevNode() ? Consts::passwordForTests : '';
                
        return response()->view('auth.admin-login', compact('password'));
    }

    /**
     * Handle an incoming authentication request.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(AdminLoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();

        $owner = H::getCached(CachePrefixes::owner, Owner::query()->select('auth_token'));
        
        if (empty($owner->auth_token)) {
            $auth_token = Str::random(40);
            $owner = Owner::first();
            $owner->auth_token = $auth_token;
            $owner->save();
            Cache::forget(CachePrefixes::owner);
        }
        $owner_auth_token = $owner->auth_token;

        $ownerAuthCookie = cookie(
            name: 'owner_auth_token', 
            value: Crypt::encryptString($owner_auth_token), 
            minutes: 2147483647, 
            path: '/', 
            domain: '.'.getDomain(), 
            secure: false, 
            httpOnly: false
        );
        Cookie::queue($ownerAuthCookie);
        
        return redirect()->intended('/admin/dashboard');
    }

    /**
     * Destroy an authenticated session.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect(domainRoute('home'));
    }
}
