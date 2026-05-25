<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class BrowserLoginThrottle
{
    private const COOKIE_NAME = 'login_throttle_token';
    private const COOKIE_MINUTES = 60 * 24 * 30;
    private const MAX_ATTEMPTS = 5;
    private const BLOCK_SECONDS = 60;

    public function status(Request $request, string $scope): array
    {
        $token = $this->token($request);
        $blockedUntil = Cache::get($this->blockedKey($scope, $token));
        $retryAfter = $blockedUntil ? max(0, $blockedUntil - now()->timestamp) : 0;

        return [
            'blocked' => $retryAfter > 0,
            'retry_after_seconds' => $retryAfter,
        ];
    }

    public function registerFailure(Request $request, string $scope): array
    {
        $token = $this->token($request);
        $attemptsKey = $this->attemptsKey($scope, $token);
        $attempts = (int) Cache::get($attemptsKey, 0) + 1;

        Cache::put($attemptsKey, $attempts, now()->addSeconds(self::BLOCK_SECONDS));

        if ($attempts >= self::MAX_ATTEMPTS) {
            $blockedUntil = now()->addSeconds(self::BLOCK_SECONDS)->timestamp;
            Cache::put($this->blockedKey($scope, $token), $blockedUntil, now()->addSeconds(self::BLOCK_SECONDS));

            return [
                'blocked' => true,
                'retry_after_seconds' => self::BLOCK_SECONDS,
            ];
        }

        return [
            'blocked' => false,
            'retry_after_seconds' => 0,
        ];
    }

    public function clear(Request $request, string $scope): void
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (!$token) {
            return;
        }

        Cache::forget($this->attemptsKey($scope, $token));
        Cache::forget($this->blockedKey($scope, $token));
    }

    private function token(Request $request): string
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (!$token) {
            $token = Str::random(64);
            Cookie::queue(cookie(
                self::COOKIE_NAME,
                $token,
                self::COOKIE_MINUTES,
                null,
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            ));
        }

        return $token;
    }

    private function attemptsKey(string $scope, string $token): string
    {
        return "login_throttle:{$scope}:attempts:" . $this->tokenHash($token);
    }

    private function blockedKey(string $scope, string $token): string
    {
        return "login_throttle:{$scope}:blocked:" . $this->tokenHash($token);
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
