<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('map');
});
use Illuminate\Support\Facades\File;

Route::get('/api/beach-photo/{id}', function ($id) {
    // === АВАРИЙНЫЙ ВЫКЛЮЧАТЕЛЬ (Раскомментируй строку ниже, чтобы всё спрятать) ===
    // return response()->json(['photo_url' => null, 'photo_urls' => []]);
    $directory = public_path('фотографии пляжей');
    
    // Ищем все возможные варианты названий
    $files = array_merge(
        glob($directory . '/' . $id . '-*.*') ?: [],
        glob($directory . '/' . $id . '.*') ?: [],
        glob($directory . '/' . $id . '(*.*') ?: [],
        glob($directory . '/' . $id . ' (*.*') ?: []
    );

    // Удаляем дубликаты
    $files = array_unique($files);

    // === ВОТ ТА САМАЯ ВАЖНАЯ СТРОКА ===
    // Если картинок нет, сразу обрываем скрипт и возвращаем пустоту
    if (empty($files)) {
        return response()->json([
            'photo_url' => null, // Для обратной совместимости
            'photo_urls' => []   // Для нашего нового слайдера
        ]);
    }

    $urls = [];
    foreach ($files as $file) {
        $urls[] = asset('фотографии пляжей/' . basename($file));
    }

    return response()->json([
        'photo_url' => $urls[0], // Отдаем первое фото как главное (на всякий случай)
        'photo_urls' => array_values($urls) // И отдаем весь массив для слайдера
    ]);
});