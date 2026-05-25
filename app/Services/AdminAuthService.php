<?php

namespace App\Services;

use App\Models\Administrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminAuthService
{
    private const COOKIE_NAME = 'admin_auth';
    private const SESSION_MINUTES = 60 * 8;

    public function admin(Request $request): ?Administrator
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (!$token) {
            return null;
        }

        $adminId = Cache::get($this->cacheKey($token));

        if (!$adminId) {
            return null;
        }

        return Administrator::query()->find($adminId);
    }

    public function attempt(Request $request, string $login, string $password): ?Administrator
    {
        $admin = Administrator::query()
            ->where('login', $login)
            ->first();

        if (!$admin || !Hash::check($password, $admin->password)) {
            return null;
        }

        $this->logout($request);

        $token = Str::random(64);
        Cache::put($this->cacheKey($token), $admin->id, now()->addMinutes(self::SESSION_MINUTES));
        Cookie::queue(cookie(
            self::COOKIE_NAME,
            $token,
            self::SESSION_MINUTES,
            null,
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        ));

        return $admin;
    }

    public function logout(Request $request): void
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if ($token) {
            Cache::forget($this->cacheKey($token));
        }

        Cookie::queue(Cookie::forget(self::COOKIE_NAME));
    }

    private function cacheKey(string $token): string
    {
        return 'admin_session:' . hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
