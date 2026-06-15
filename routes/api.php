<?php

use App\Http\Controllers\Api\BeachController;
use App\Http\Controllers\Api\BeachInteractionController;
use App\Mail\VisitorVerificationCodeMail;
use App\Models\Beach;
use App\Models\BeachOperator;
use App\Models\Visitor;
use App\Services\BrowserLoginThrottle;
use App\Services\VisitorResolver;
use App\Services\WaveForecastSelector;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

$visitorVerificationKey = static fn (string $email): string => 'visitor_register_code:' . sha1(Str::lower($email));
$visitorVerificationCooldownKey = static fn (Request $request, string $email): string => 'visitor_register_code_cooldown:' . sha1($request->ip() . '|' . Str::lower($email));
$visitorNicknameKey = static fn (string $nickname): string => Str::lower(trim($nickname));
$makeVisitorCookie = static function (Request $request, Visitor $visitor) {
    $token = Str::random(64);

    $visitor->forceFill([
        'visitor_hash' => VisitorResolver::hashToken($token),
    ])->save();

    return cookie(
        VisitorResolver::COOKIE_NAME,
        $token,
        VisitorResolver::COOKIE_MINUTES,
        null,
        null,
        $request->isSecure(),
        true,
        false,
        'lax'
    );
};

Route::post('/visitor/register/send-code', function (Request $request) use ($visitorVerificationKey, $visitorVerificationCooldownKey) {
    $validated = $request->validate([
        'email' => ['required', 'email', 'max:255'],
    ]);

    $email = Str::lower($validated['email']);
    $cooldownKey = $visitorVerificationCooldownKey($request, $email);

    if (Cache::has($cooldownKey)) {
        return response()->json([
            'success' => false,
            'message' => 'Код уже был отправлен. Повторная отправка будет доступна через минуту.',
        ], 429);
    }

    $code = (string) random_int(100000, 999999);

    Cache::put($visitorVerificationKey($email), [
        'code_hash' => Hash::make($code),
    ], now()->addMinutes(10));

    $isLogMailer = config('mail.default') === 'log';

    try {
        Mail::to($email)->send(new VisitorVerificationCodeMail($code));
    } catch (\Throwable $exception) {
        Cache::forget($visitorVerificationKey($email));

        report($exception);

        return response()->json([
            'success' => false,
            'message' => 'Не удалось отправить код подтверждения. Проверьте настройки почты MAIL_*.',
        ], 503);
    }

    Cache::put($cooldownKey, true, now()->addMinute());

    return response()->json([
        'success' => true,
        'message' => $isLogMailer
            ? 'Код подтверждения записан в storage/logs/laravel.log, потому что MAIL_MAILER=log. Для реальной отправки настройте SMTP.'
            : 'Код подтверждения отправлен на email. Он действует 10 минут.',
    ]);
});

Route::post('/visitor/register', function (Request $request) use ($visitorVerificationKey, $visitorNicknameKey, $makeVisitorCookie) {
    $validated = $request->validate([
        'nickname' => [
            'required',
            'string',
            'min:3',
            'max:32',
            'regex:/^[\pL\pN_-]+$/u',
        ],
        'email' => ['required', 'email', 'max:255'],
        'last_name' => ['nullable', 'string', 'max:255'],
        'first_name' => ['nullable', 'string', 'max:255'],
        'middle_name' => ['nullable', 'string', 'max:255'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'verification_code' => ['required', 'digits:6'],
    ]);

    $email = Str::lower($validated['email']);
    $nickname = trim($validated['nickname']);
    $nicknameKey = $visitorNicknameKey($nickname);
    $cachedCode = Cache::get($visitorVerificationKey($email));

    $nicknameOwner = Visitor::query()
        ->where('nickname_key', $nicknameKey)
        ->where(function ($query) use ($email) {
            $query->where('email', '!=', $email)
                ->orWhereNull('email');
        })
        ->exists();

    if ($nicknameOwner) {
        return response()->json([
            'success' => false,
            'message' => 'Этот ник уже занят.',
        ], 422);
    }

    if (!$cachedCode || !Hash::check($validated['verification_code'], $cachedCode['code_hash'] ?? '')) {
        return response()->json([
            'success' => false,
            'message' => 'Неверный или просроченный код подтверждения.',
        ], 422);
    }

    $visitor = Visitor::query()->updateOrCreate(
        ['email' => $email],
        [
            'nickname' => $nickname,
            'nickname_key' => $nicknameKey,
            'last_name' => $validated['last_name'] ?? null,
            'first_name' => $validated['first_name'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'password' => $validated['password'],
        ]
    );

    Cache::forget($visitorVerificationKey($email));

    return response()
        ->json([
            'success' => true,
            'message' => 'Регистрация завершена.',
        ])
        ->cookie($makeVisitorCookie($request, $visitor));
})->middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
]);

Route::post('/visitor/login', function (Request $request) use ($visitorNicknameKey, $makeVisitorCookie) {
    $validated = $request->validate([
        'identifier' => ['required', 'string', 'max:255'],
        'password' => ['required', 'string'],
    ]);

    $identifier = trim($validated['identifier']);
    $visitor = Visitor::query()
        ->when(str_contains($identifier, '@'), function ($query) use ($identifier) {
            $query->where('email', Str::lower($identifier));
        }, function ($query) use ($visitorNicknameKey, $identifier) {
            $query->where('nickname_key', $visitorNicknameKey($identifier));
        })
        ->first();

    if (!$visitor || !$visitor->password || !Hash::check($validated['password'], $visitor->password)) {
        return response()->json([
            'success' => false,
            'message' => 'Неверный ник, email или пароль.',
        ], 403);
    }

    return response()
        ->json([
            'success' => true,
            'message' => 'Вход выполнен.',
        ])
        ->cookie($makeVisitorCookie($request, $visitor));
})->middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
]);

Route::post('/visitor/logout', function () {
    return response()
        ->json(['success' => true])
        ->withoutCookie(VisitorResolver::COOKIE_NAME);
})->middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
]);

Route::post('/operator/login', function (Request $request, BrowserLoginThrottle $throttle) {
    $validated = $request->validate([
        'login' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    $throttleStatus = $throttle->status($request, 'operator');
    if ($throttleStatus['blocked']) {
        return response()->json([
            'success' => false,
            'message' => "Слишком много попыток входа. Попробуйте через {$throttleStatus['retry_after_seconds']} сек.",
            'retry_after_seconds' => $throttleStatus['retry_after_seconds'],
        ], 429);
    }

    $operator = BeachOperator::query()
        ->where('login', $validated['login'])
        ->first();

    if (!$operator || !Hash::check($validated['password'], $operator->password)) {
        $throttleStatus = $throttle->registerFailure($request, 'operator');

        return response()->json([
            'success' => false,
            'message' => $throttleStatus['blocked']
                ? "Слишком много попыток входа. Попробуйте через {$throttleStatus['retry_after_seconds']} сек."
                : 'Неверный логин или пароль.',
            'retry_after_seconds' => $throttleStatus['retry_after_seconds'],
        ], $throttleStatus['blocked'] ? 429 : 403);
    }

    $throttle->clear($request, 'operator');

    return response()
        ->json([
            'success' => true,
            'beach_id' => $operator->beach_id,
        ])
        ->cookie('operator_hash', $operator->operator_hash, 60 * 24);
})->middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
]);

Route::post('/operator/logout', function () {
    return response()
        ->json(['success' => true])
        ->withoutCookie('operator_hash');
})->middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
]);

Route::post('/operator/password', function (Request $request) {
    $operator = BeachOperator::query()
        ->where('operator_hash', $request->cookie('operator_hash'))
        ->first();

    abort_unless($operator, 403);

    $validated = $request->validate([
        'current_password' => ['required', 'string'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    if (!Hash::check($validated['current_password'], $operator->password)) {
        return response()->json([
            'message' => 'Current password is incorrect',
        ], 422);
    }

    $newHash = Str::random(64);

    $operator->update([
        'password' => Hash::make($validated['password']),
        'operator_hash' => $newHash,
    ]);

    return response()
        ->json(['message' => 'Password changed'])
        ->cookie('operator_hash', $newHash, 60 * 24);
})->middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
]);

Route::get('/beaches', function () {
    return Beach::query()
        ->orderBy('number')
        ->get();
});

Route::get('/beach-polygons', function () {
    $features = DB::table('beach_polygons')
        ->selectRaw("source_feature_id, properties, ST_AsGeoJSON(geom)::json AS geometry")
        ->orderBy('source_feature_id')
        ->get()
        ->map(function ($polygon) {
            $properties = is_string($polygon->properties)
                ? json_decode($polygon->properties, true)
                : (array) $polygon->properties;

            return [
                'type' => 'Feature',
                'properties' => $properties ?: ['id' => $polygon->source_feature_id],
                'geometry' => is_string($polygon->geometry)
                    ? json_decode($polygon->geometry, true)
                    : $polygon->geometry,
            ];
        });

    return response()->json([
        'type' => 'FeatureCollection',
        'features' => $features,
    ]);
});

Route::patch('/beaches/wave-level', function (Request $request) {
    $validated = $request->validate([
        'number' => ['required', 'integer', 'exists:beaches,number'],
        'wave_level' => ['required', 'integer', 'between:0,12'],
    ]);

    $beach = Beach::query()
        ->where('number', $validated['number'])
        ->firstOrFail();

    $beach->update([
        'wave_level' => $validated['wave_level'],
    ]);

    return response()->json([
        'message' => 'Wave level updated',
        'beach' => $beach->fresh(),
    ]);
});

Route::get('/beach-info/{id}', [BeachController::class, 'getInfo']);
Route::post('/beaches/{beach}/reaction', [BeachInteractionController::class, 'reaction']);
Route::get('/favorites', [BeachInteractionController::class, 'favorites']);
Route::post('/beaches/{beach}/favorite-toggle', [BeachInteractionController::class, 'favoriteToggle']);

Route::get('/beach-info-legacy/{id}', function (WaveForecastSelector $forecastSelector, $id) {
    $beach = Beach::query()->find($id);

    if (!$beach) {
        return response()->json(['error' => 'Beach not found'], 404);
    }

    $beach->setRelation('latestForecast', $forecastSelector->forBeach((int) $id, now('UTC')));

    return response()->json($beach);
});
