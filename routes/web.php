<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Http\Request;
use App\Models\Beach;
use Illuminate\Support\Facades\DB;

// Главная страница (Карта)
Route::get('/', function (Request $request) {
    // Проверяем, авторизован ли какой-нибудь оператор вообще
    $operatorCookie = Cookie::get('operator_session');
    $authOperator = null;

    if ($operatorCookie) {
        $authOperator = DB::table('beach_operators')
            ->where('operator_hash', $operatorCookie)
            ->first();
    }

    return view('map', [
        'isOperator' => !is_null($authOperator),
        'operatorBeachId' => $authOperator ? $authOperator->beach_id : null
    ]);
});

// Секретный вход для оператора (имитация сканирования пропуска или авторизации)
Route::post('/secret-login', function () {
    // В реальной системе тут генерация, для ВКР сделаем фиксацию тестового оператора для пляжа №2
    $testHash = 'test_operator_hash_123';

    // Проверим или создадим тестовую запись, чтобы система сразу работала
    $exists = DB::table('beach_operators')->where('operator_hash', $testHash)->exists();
    if (!$exists) {
        $firstBeach = Beach::first() ?? (object) ['id' => 2];
        DB::table('beach_operators')->insert([
            'beach_id' => $firstBeach->id ?? 2,
            'operator_hash' => $testHash,
            'name' => 'Иванов И.И. (Оператор)',
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    // Записываем куку на 1 день
    Cookie::queue('operator_session', $testHash, 1440);

    return response()->json(['success' => true]);
});

// Страница панели оператора с жесткой проверкой прав
Route::get('/operator/{id}', function ($id) {
    $operatorCookie = Cookie::get('operator_session');

    if (!$operatorCookie) {
        abort(403, 'Доступ запрещен. Не найдены параметры авторизации.');
    }

    $authOperator = DB::table('beach_operators')
        ->where('operator_hash', $operatorCookie)
        ->where('beach_id', $id)
        ->first();

    if (!$authOperator) {
        abort(403, 'Вы не являетесь оператором данного пляжа.');
    }

    $beach = Beach::findOrFail($id);
    return view('operator', ['beach' => $beach]);
});