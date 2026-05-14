<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule; // Для работы планировщика
use Illuminate\Support\Facades\Cache;    // Для проверки кнопки Вкл/Выкл

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --- НАШ ПЛАНИРОВЩИК (Шаг 2) ---
Schedule::command('wave:fetch')
    ->hourly() // Запуск каждый час
    ->when(function () {
        // Команда выполнится ТОЛЬКО если в кэше статус = true (то есть парсер включен)
        return Cache::get('parsing_enabled', true);
    });