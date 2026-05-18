<?php

use App\Models\Beach;
use App\Models\BeachOperator;
use App\Models\WaveForecast;
use App\Http\Controllers\Api\BeachController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

Route::post('/operator/login', function (Request $request) {
    $validated = $request->validate([
        'hash' => ['required', 'string'],
    ]);

    $operator = BeachOperator::query()
        ->where('operator_hash', $validated['hash'])
        ->first();

    if (!$operator) {
        return response()->json(['success' => false], 403);
    }

    return response()
        ->json([
            'success' => true,
            'beach_id' => $operator->beach_id,
        ])
        ->cookie('operator_hash', $operator->operator_hash, 60 * 24);
});

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
Route::get('/beach-info/{id}', [BeachController::class, 'getInfo']);
Route::get('/beach-info-legacy/{id}', function ($id) {
    // Ищем по первичному ключу ID, который присылает карта
    $beach = \App\Models\Beach::with('latestForecast')->find($id);

    if (!$beach) {
        return response()->json(['error' => 'Beach not found'], 404);
    }

    return response()->json($beach);
});


// 5. Принудительный запуск парсера (Взять данные сейчас)
Route::post('/force-fetch', function () {
    try {
        // Эта команда программно запускает твой php artisan wave:fetch
        Artisan::call('wave:fetch');
        return response()->json(['message' => 'Данные успешно обновлены с серверов DWD!']);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Ошибка: ' . $e->getMessage()], 500);
    }
});

// 6. Переключатель парсера (Вкл / Выкл)
Route::post('/toggle-parsing', function () {
    // Используем кэш Laravel для хранения состояния (по умолчанию считаем, что парсер включен)
    $currentStatus = Cache::get('parsing_enabled', true);

    // Переворачиваем значение (true -> false, false -> true)
    $newStatus = !$currentStatus;
    Cache::put('parsing_enabled', $newStatus);

    $statusText = $newStatus ? 'ВКЛЮЧЕН' : 'ВЫКЛЮЧЕН';
    return response()->json(['message' => "Ежечасный сбор данных теперь $statusText."]);
});
