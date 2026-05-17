<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;
use App\Models\Beach;
use Illuminate\Support\Facades\DB;

// Главная страница
Route::get('/', function (Request $request) {
    $hash = Cookie::get('operator_hash');
    $operator = null;

    if ($hash) {
        $operator = DB::table('beach_operators')->where('operator_hash', $hash)->first();
    }

    return view('map', [
        'isOperator' => !is_null($operator),
        'operatorBeachId' => $operator ? $operator->beach_id : null
    ]);
});

// Реальный POST-маршрут авторизации оператора по вводимому хэшу
Route::post('/api/operator/login', function (Request $request) {
    $hash = $request->input('hash');
    $operator = DB::table('beach_operators')->where('operator_hash', $hash)->first();

    if ($operator) {
        // Устанавливаем куку на 24 часа
        Cookie::queue('operator_hash', $hash, 1440);
        return response()->json(['success' => true, 'beach_id' => $operator->beach_id]);
    }

    return response()->json(['success' => false, 'message' => 'Неверный хэш-код оператора'], 401);
});

// Страница панели оператора с жесткой привязкой к его пляжу
Route::get('/operator/{id}', function ($id) {
    $hash = Cookie::get('operator_hash');

    if (!$hash) {
        abort(403, 'Доступ запрещен. Cookie-хэш отсутствует.');
    }

    $operator = DB::table('beach_operators')
        ->where('operator_hash', $hash)
        ->where('beach_id', $id)
        ->first();

    if (!$operator) {
        abort(403, 'Вы не являетесь уполномоченным оператором данного пляжа.');
    }

    $beach = Beach::findOrFail($id);
    return view('operator', ['beach' => $beach]);
});