<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;
use App\Models\Beach;
use Illuminate\Support\Facades\DB;

// 1. ГЛАВНАЯ СТРАНИЦА (Твоя карта)
Route::get('/', function (Request $request) {
    $operatorCookie = Cookie::get('operator_session');
    $authOperator = null;

    if ($operatorCookie) {
        $authOperator = DB::table('beach_operators')
            ->where('operator_hash', $operatorCookie)
            ->first();
    }

    // Передаем маркеры авторизации в твой существующий map.blade.php
    return view('map', [
        'isOperator' => !is_null($authOperator),
        'operatorBeachId' => $authOperator ? $authOperator->beach_id : null
    ]);
});

// 2. СЕКРЕТНЫЙ ВХОД (Для кнопки id="secret-login-btn")
Route::post('/secret-login', function () {
    $testHash = 'operator_sevastopol_secure_2026';

    // Автоматически привяжем тестового оператора к пляжу с ID = 2, если записи нет
    $exists = DB::table('beach_operators')->where('operator_hash', $testHash)->exists();
    if (!$exists) {
        DB::table('beach_operators')->insert([
            'beach_id' => 2, // Пляж оператора
            'operator_hash' => $testHash,
            'name' => 'Дежурный оператор',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    // Пишем куку в браузер на 1 день
    Cookie::queue('operator_session', $testHash, 1440);
    return response()->json(['success' => true]);
});

// 3. СТРАНИЦА ОПЕРАТОРА (С жесткой верификацией прав)
Route::get('/operator/{id}', function ($id) {
    $operatorCookie = Cookie::get('operator_session');

    if (!$operatorCookie) {
        abort(403, 'Доступ запрещен. Не авторизован.');
    }

    $authOperator = DB::table('beach_operators')
        ->where('operator_hash', $operatorCookie)
        ->where('beach_id', $id)
        ->first();

    if (!$authOperator) {
        abort(403, 'У вас нет прав для управления этим пляжем.');
    }

    $beach = Beach::findOrFail($id);
    return view('operator', ['beach' => $beach]);
});