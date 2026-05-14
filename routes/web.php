<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('map');
});
use Illuminate\Support\Facades\File;

Route::get('/api/beach-photo/{id}', function ($id) {
    $directory = public_path('фотографии пляжей');
    
    // Защита, если папки вдруг нет
    if (!is_dir($directory)) {
        return response()->json(['photo_url' => null, 'photo_urls' => []]);
    }

    // Читаем ВООБЩЕ ВСЕ файлы в папке напрямую (этот метод не ломается от скобок)
    $allFiles = scandir($directory);
    $validUrls = [];

    foreach ($allFiles as $file) {
        // Пропускаем системные скрытые файлы
        if ($file === '.' || $file === '..') continue;

        // Ищем файлы, которые начинаются строго с "1-", "1." или "1("
        // Используем современную функцию PHP 8 str_starts_with
        if (
            str_starts_with($file, $id . '-') || 
            str_starts_with($file, $id . '.') || 
            str_starts_with($file, $id . '(')
        ) {
            $validUrls[] = asset('фотографии пляжей/' . $file);
        }
    }

    // Если ничего не нашли
    if (empty($validUrls)) {
        return response()->json([
            'photo_url' => null,
            'photo_urls' => []
        ]);
    }

    return response()->json([
        'photo_url' => array_values($validUrls)[0], // Отдаем первое фото как главное
        'photo_urls' => array_values($validUrls)    // Отдаем весь массив для слайдера
    ]);
});
// routes/web.php
use App\Models\Beach;

Route::get('/api/beach-info/{id}', function ($id) {
    // Загружаем пляж вместе с его последним прогнозом
    // Ищем пляж именно по колонке 'number', которая приходит с карты
    $beach = Beach::with('latestForecast')->where('number', $id)->first();

    if (!$beach) {
        return response()->json(['error' => 'Beach not found'], 404);
    }

    return response()->json($beach);
});