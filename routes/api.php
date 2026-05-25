<?php

use App\Http\Controllers\Api\BeachController;
use App\Http\Controllers\Api\BeachInteractionController;
use App\Models\Beach;
use App\Models\BeachOperator;
use App\Services\BrowserLoginThrottle;
use App\Services\WaveForecastSelector;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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
