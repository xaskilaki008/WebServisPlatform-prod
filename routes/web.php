<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BeachController;
use App\Models\Beach;
use App\Models\BeachOperator;
use App\Models\BeachOperatorLog;
use App\Services\WaveForecastSelector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

// Главная страница (Frontend)
Route::get('/', function (Request $request) {
    $operator = Schema::hasTable('beach_operators')
        ? BeachOperator::query()
            ->where('operator_hash', $request->cookie('operator_hash'))
            ->first()
        : null;

    return view('map', [
        'isOperator' => (bool) $operator,
        'operatorBeachId' => $operator?->beach_id,
    ]);
});

Route::get('/operator/{id}', function (Request $request, WaveForecastSelector $forecastSelector, int $id) {
    $operator = BeachOperator::query()
        ->where('operator_hash', $request->cookie('operator_hash'))
        ->where('beach_id', $id)
        ->first();

    abort_unless($operator, 403, 'Доступ запрещен');

    $beach = Beach::query()->findOrFail($id);
    $beach->setRelation('latestForecast', $forecastSelector->forBeach($id, now('UTC')));

    return view('operator', [
        'operator' => $operator,
        'beach' => $beach,
    ]);
});

Route::post('/operator/{id}', function (Request $request, int $id) {
    $operator = BeachOperator::query()
        ->where('operator_hash', $request->cookie('operator_hash'))
        ->where('beach_id', $id)
        ->first();

    abort_unless($operator, 403, 'Доступ запрещен');

    $validated = $request->validate([
        'operator_status' => ['required', 'in:0,1,2,3,4,5,hazard'],
        'operator_warning' => ['nullable', 'string', 'max:250'],
        'operator_wave_direction' => ['required', 'in:direct,left,right,azimuth,chaotic'],
        'operator_wave_azimuth' => ['nullable', 'required_if:operator_wave_direction,azimuth', 'integer', 'between:0,360'],
        'operator_wave_period' => ['required', 'integer', 'between:2,12'],
        'operator_access_status' => ['required', 'in:open,limited,closed'],
        'operator_validity' => ['required', 'in:30m,1h,3h,24h,until_disabled'],
    ]);

    $submittedAt = now();
    $expiresAt = match ($validated['operator_validity']) {
        '30m' => $submittedAt->copy()->addMinutes(30),
        '3h' => $submittedAt->copy()->addHours(3),
        '24h' => $submittedAt->copy()->addDay(),
        'until_disabled' => $submittedAt->copy()->addMonth(),
        default => $submittedAt->copy()->addHour(),
    };

    $beach = Beach::query()->findOrFail($id);
    $beach->update([
        'operator_status' => $validated['operator_status'],
        'operator_warning' => $validated['operator_warning'] ?? null,
        'operator_wave_direction' => $validated['operator_wave_direction'],
        'operator_wave_azimuth' => $validated['operator_wave_direction'] === 'azimuth'
            ? ($validated['operator_wave_azimuth'] ?? null)
            : null,
        'operator_wave_period' => $validated['operator_wave_period'],
        'operator_access_status' => $validated['operator_access_status'],
        'operator_updated_at' => $submittedAt,
        'operator_expires_at' => $expiresAt,
    ]);

    BeachOperatorLog::query()->create([
        'beach_operator_id' => $operator->id,
        'beach_id' => $beach->id,
        'submitted_at' => $submittedAt,
        'expires_at' => $expiresAt,
        'operator_status' => $validated['operator_status'],
        'operator_warning' => $validated['operator_warning'] ?? null,
        'operator_wave_direction' => $validated['operator_wave_direction'],
        'operator_wave_azimuth' => $validated['operator_wave_direction'] === 'azimuth'
            ? ($validated['operator_wave_azimuth'] ?? null)
            : null,
        'operator_wave_period' => $validated['operator_wave_period'],
        'operator_access_status' => $validated['operator_access_status'],
    ]);

    return redirect("/operator/{$id}")->with('status', 'Данные сохранены и опубликованы');
});

// АПИ для фронтенда (Backend)
Route::get('/api/beach-info/{id}', [BeachController::class, 'getInfo']);
Route::get('/api/beach-photo/{id}', [BeachController::class, 'getPhoto']);
