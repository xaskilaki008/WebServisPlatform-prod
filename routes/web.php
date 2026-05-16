<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BeachController;

// Главная страница (Frontend)
Route::get('/', function () {
    return view('map');
});

// АПИ для фронтенда (Backend)
Route::get('/api/beach-info/{id}', [BeachController::class, 'getInfo']);
Route::get('/api/beach-photos/{id}', [BeachController::class, 'getPhoto']);