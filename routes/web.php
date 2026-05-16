<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BeachController;
use App\Models\Beach;

// Главная страница (Frontend)
Route::get('/', function () {
    return view('map');
});

// АПИ для фронтенда (Backend)
Route::get('/api/beach-info/{id}', [BeachController::class, 'getInfo']);
Route::get('/api/beach-photo/{id}', [BeachController::class, 'getPhoto']);
Route::post('/api/beach-info/{id}/operator-status', [BeachController::class, 'updateOperatorStatus']);
Route::get('/operator/{id}', function ($id) {
    $beach = Beach::findOrFail($id);
    return view('operator', ['beach' => $beach]);
});