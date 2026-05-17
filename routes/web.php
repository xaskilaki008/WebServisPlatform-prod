<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BeachController;
use App\Models\Beach;
use App\Models\BeachOperator;
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

Route::get('/operator/{id}', function (Request $request, int $id) {
    $operator = BeachOperator::query()
        ->where('operator_hash', $request->cookie('operator_hash'))
        ->where('beach_id', $id)
        ->first();

    abort_unless($operator, 403, 'Доступ запрещен');

    return view('operator', [
        'operator' => $operator,
        'beach' => Beach::query()->findOrFail($id),
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
    ]);

    $beach = Beach::query()->findOrFail($id);
    $beach->update(['operator_status' => $validated['operator_status']]);

    return redirect("/operator/{$id}")->with('status', 'Статус сохранен');
});

// АПИ для фронтенда (Backend)
Route::get('/api/beach-info/{id}', [BeachController::class, 'getInfo']);
Route::get('/api/beach-photo/{id}', [BeachController::class, 'getPhoto']);
