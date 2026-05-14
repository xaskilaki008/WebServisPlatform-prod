<?php

use App\Models\Beach;
use App\Models\WaveForecast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// 1. Получение списка пляжей
Route::get('/beaches', function () {
    return Beach::query()
        ->orderBy('number')
        ->get();
});

// 2. Получение полигонов для карты
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

// 3. Обновление уровня волнения (для админки)
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
        'message' => 'Уровень волнения обновлен',
        'beach' => $beach->fresh(),
    ]);
});

// 4. Получение подробной информации (включая волны)
Route::get('/beach-info/{id}', function ($id) {
    // Ищем по первичному ключу ID, который присылает карта
    $beach = Beach::with('latestForecast')->find($id);

    if (!$beach) {
        return response()->json(['error' => 'Beach not found'], 404);
    }

    return response()->json($beach);
});