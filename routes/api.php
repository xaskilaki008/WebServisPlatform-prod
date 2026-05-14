<?php

use App\Models\Beach;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

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
        'message' => 'Уровень волнения обновлен',
        'beach' => $beach->fresh(),
    ]);
});
use App\Models\WaveForecast;

// Маршрут для получения данных о волнах конкретного пляжа
Route::get('/beach-info/{id}', function ($id) {
    // Ищем в таблице wave_forecasts самую свежую запись для этого пляжа.
    // latest('forecast_time') отсортирует прогнозы по времени (от новых к старым).
    $forecast = WaveForecast::where('beach_id', $id)
        ->latest('forecast_time')
        ->first();

    // Возвращаем данные в формате JSON. 
    // Мы оборачиваем их в ключ 'latest_forecast', так как твой JS ищет именно его.
    return response()->json([
        'latest_forecast' => $forecast
    ]);
});