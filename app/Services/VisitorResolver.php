<?php

namespace App\Services;

use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

class VisitorResolver
{
    public const COOKIE_NAME = 'visitor_token';
    public const COOKIE_MINUTES = 60 * 24 * 365;

    public function current(Request $request): ?Visitor
    {
        $token = $request->cookie(self::COOKIE_NAME);

        if (!$token) {
            return null;
        }

        return Visitor::query()
            ->where('visitor_hash', self::hashToken($token))
            ->first();
    }

    public function currentOrCreate(Request $request): Visitor
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

        return Visitor::query()->firstOrCreate([
            'visitor_hash' => self::hashToken($token),
        ]);
    }

    public static function hashToken(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
    }
}
