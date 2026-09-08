<?php

namespace App\Guards;

use App\Dicts\CachePrefixes;
use App\Http\H;
use App\Models\Owner;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cookie;

class StaticCookieGuard implements Guard
{
    protected ?Authenticatable $user = null;

    function hasUser()
    {
        return !is_null($this->user());
    }

    public function check()
    {
        return !is_null($this->user());
    }

    public function guest()
    {
        return !$this->check();
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $cookie = Cookie::get('owner_auth_token');

        if (!$cookie) {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($cookie);
        } catch (\Exception $e) {
            return null;
        }

        $owner = H::getCached(CachePrefixes::owner, Owner::query()->select('auth_token'));
        
        $expectedToken = $owner->auth_token;

        if ($decrypted === $expectedToken) {
            /* Fake "user" object */
            $this->user = new class implements Authenticatable {
                public function getAuthIdentifierName() { return 'id'; }
                public function getAuthIdentifier() { return 1; }
                public function getAuthPassword() { return null; }
                public function getRememberToken() {}
                public function setRememberToken($value) {}
                public function getRememberTokenName() { return null; }
                public function getAuthPasswordName() { return null; }
            };

            return $this->user;
        }

        return null;
    }

    public function id()
    {
        return $this->user() ? $this->user()->getAuthIdentifier() : null;
    }

    public function validate(array $credentials = [])
    {
        /* manual login not used */
        return false; 
    }

    public function setUser(Authenticatable $user)
    {
        $this->user = $user;
        return $this;
    }
}
