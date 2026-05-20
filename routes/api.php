<?php

use App\Http\Controllers\Api\BeachController;
use App\Models\Beach;
use App\Models\BeachOperator;
use App\Models\WaveForecast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::post('/operator/login', function (Request $request) {
    $validated = $request->validate([
        'login' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    $operator = BeachOperator::query()
        ->where('login', $validated['login'])
        ->first();

    if (!$operator || !Hash::check($validated['password'], $operator->password)) {
        return response()->json(['success' => false], 403);
    }

    return response()
        ->json([
            'success' => true,
            'beach_id' => $operator->beach_id,
        ])
        ->cookie('operator_hash', $operator->operator_hash, 60 * 24);
});

Route::post('/operator/logout', function () {
    return response()
        ->json(['success' => true])
        ->withoutCookie('operator_hash');
});

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
});

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

Route::get('/beach-info-legacy/{id}', function ($id) {
    $beach = Beach::with('latestForecast')->find($id);

    if (!$beach) {
        return response()->json(['error' => 'Beach not found'], 404);
    }

    return response()->json($beach);
});

Route::post('/force-fetch', function (Request $request) {
    abort_unless(
        BeachOperator::query()
            ->where('operator_hash', $request->cookie('operator_hash'))
            ->exists(),
        403
    );

    try {
        Artisan::call('wave:fetch');

        $payload = ['message' => 'DWD data updated'];

        if ((bool) config('app.debug') || app()->environment(['local', 'development'])) {
            $payload['dwd_debug_summary'] = WaveForecast::query()
                ->whereNotNull('parsed_at')
                ->latest('parsed_at')
                ->limit(10)
                ->get([
                    'beach_id',
                    'model_run_hour',
                    'parsed_at',
                    'forecast_time',
                    'model_run_at',
                    'wave_height',
                    'wave_period',
                    'wave_direction',
                    'air_temp',
                    'water_temp',
                    'source_files',
                ])
                ->map(fn (WaveForecast $forecast) => [
                    'source_folder' => str_pad((string) $forecast->model_run_hour, 2, '0', STR_PAD_LEFT),
                    'source_files' => $forecast->source_files,
                    'parsed_at' => $forecast->parsed_at,
                    'beach_id' => $forecast->beach_id,
                    'wave_height' => $forecast->wave_height,
                    'wave_period' => $forecast->wave_period,
                    'wave_direction' => $forecast->wave_direction,
                    'air_temp' => $forecast->air_temp,
                    'water_temp' => $forecast->water_temp,
                    'forecast_time' => $forecast->forecast_time,
                    'model_run_at' => $forecast->model_run_at,
                ]);
        }

        return response()->json($payload);
    } catch (Exception $e) {
        return response()->json(['error' => 'Error: ' . $e->getMessage()], 500);
    }
});

Route::post('/toggle-parsing', function (Request $request) {
    abort_unless(
        BeachOperator::query()
            ->where('operator_hash', $request->cookie('operator_hash'))
            ->exists(),
        403
    );

    $newStatus = !Cache::get('parsing_enabled', true);
    Cache::put('parsing_enabled', $newStatus);

    return response()->json([
        'message' => $newStatus ? 'Parsing enabled' : 'Parsing disabled',
    ]);
});
