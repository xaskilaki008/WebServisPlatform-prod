<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Beach;
use App\Models\WaveForecast;
use Carbon\Carbon;

class FetchDwdWaveData extends Command
{
    protected $signature = 'wave:fetch';
    protected $description = 'Парсинг данных о волнении из GRIB2 (модель DWD EWAM)';

    // Настройки для модели EWAM (European Wave Model - идеальна для Крыма)
    private $parameters = [
        'swh' => 'wave_height',
        'tm10' => 'wave_period',
        'mwd' => 'wave_direction'
    ];

    public function handle()
    {
        $this->info("Начинаем получение данных DWD EWAM (Европейская модель волнения)...");

        $wgrib2Path = env('WGRIB2_PATH', 'wgrib2');
        $now = Carbon::now(config('app.timezone', 'UTC'));
        $modelRunHour = $now->hour < 12 ? 0 : 12;
        $modelRunDir = str_pad((string) $modelRunHour, 2, '0', STR_PAD_LEFT);
        $modelRunAt = $now->copy()->startOfDay()->addHours($modelRunHour);
        $beaches = Beach::all();

        if ($beaches->isEmpty()) {
            $this->error("В базе нет пляжей с координатами!");
            return;
        }

        $parsedData = [];

        foreach ($this->parameters as $dwdDir => $dbColumn) {
            $this->info("Обработка параметра: {$dwdDir}...");

            // 1. Получаем список файлов (HTML-код каталога)
            $indexUrl = "https://opendata.dwd.de/weather/maritime/wave_models/ewam/grib/{$modelRunDir}/{$dwdDir}/";

            try {
                $indexResponse = Http::withoutVerifying()->get($indexUrl);
                if ($indexResponse->failed()) {
                    $this->error(" -> Ошибка доступа к каталогу DWD: {$indexUrl}");
                    continue;
                }
            } catch (\Exception $e) {
                $this->error(" -> Сетевая ошибка: " . $e->getMessage());
                continue;
            }

            // 2. Ищем самый свежий архив на нулевой час прогноза (000)
            $pattern = '/(EWAM_[A-Z0-9_]+_\d{8}' . $modelRunDir . '_000\.grib2\.bz2)/i';
            if (!preg_match_all($pattern, $indexResponse->body(), $matches)) {
                $this->error(" -> Файлы по паттерну не найдены на сервере.");
                continue;
            }

            // Забираем последний файл из массива (он же самый свежий по дате)
            $latestFileName = end($matches[0]);
            $fileUrl = $indexUrl . $latestFileName;

            $gribFileName = "latest_{$dwdDir}.grib2";
            $filePath = storage_path("app/{$gribFileName}");

            // 3. Скачиваем .bz2 архив
            $this->line(" -> Скачивание: {$latestFileName}...");
            try {
                $fileResponse = Http::withoutVerifying()->timeout(120)->get($fileUrl);

                // 4. Распаковываем bzip2 на лету
                $this->line(" -> Распаковка BZIP2-архива...");
                if (!function_exists('bzdecompress')) {
                    $this->error("ОШИБКА: Расширение BZIP2 не включено в PHP!");
                    $this->error("Открой php.ini, раскомментируй строку 'extension=bz2' и перезапусти консоль.");
                    return;
                }

                $gribContent = bzdecompress($fileResponse->body());
                if (!$gribContent) {
                    $this->error(" -> Ошибка: битый архив.");
                    continue;
                }

                // 5. Сохраняем чистый GRIB2 файл напрямую по физическому пути
                file_put_contents($filePath, $gribContent);
            } catch (\Exception $e) {
                $this->error(" -> Исключение при скачивании/распаковке: " . $e->getMessage());
                continue;
            }

            // 6. Вызываем утилиту wgrib2 для извлечения данных по координатам пляжей
            $this->line(" -> Геопространственный парсинг wgrib2...");

            // Получаем чистый путь к папке storage/app
            $storageDir = storage_path('app');

            foreach ($beaches as $beach) {
                // ЗАЩИТА: Если у пляжа нет морских координат, просто пропускаем его, чтобы не сломать wgrib2
                if (empty($beach->fetch_longitude) || empty($beach->fetch_latitude)) {
                    $this->warn(" -> Пропуск пляжа '{$beach->name}': нет морских координат.");
                    continue;
                }

                $command = "cd /d \"{$storageDir}\" && \"{$wgrib2Path}\" \"{$gribFileName}\" -lon {$beach->fetch_longitude} {$beach->fetch_latitude}";
                $output = shell_exec($command);

                // --- ВОТ ЭТОТ БЛОК БЫЛ УТЕРЯН (Вытаскиваем число из ответа и кладем в массив) ---
                if ($output && preg_match('/val=([0-9\.\-]+)/', $output, $valMatches)) {
                    $value = (float) $valMatches[1];

                    if (!isset($parsedData[$beach->id])) {
                        $parsedData[$beach->id] = [];
                    }
                    $parsedData[$beach->id][$dbColumn] = $value;
                }
                // ---------------------------------------------------------------------------------
            }

            // 7. Удаляем временный файл, освобождаем память (Используем чистый PHP unlink)
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            $this->line(" -> Временный файл удален.");
        }

        // 8. Массовое сохранение в БД
        if (!empty($parsedData)) {
            $this->info("Сохранение данных в БД...");
            $forecastTime = $modelRunAt->copy();

            foreach ($parsedData as $beachId => $data) {
                WaveForecast::updateOrCreate(
                    [
                        'beach_id' => $beachId,
                        'model_run_at' => $modelRunAt,
                    ],
                    array_merge($data, [
                        'forecast_time' => $forecastTime,
                        'model_run_hour' => $modelRunHour,
                    ])
                );
            }
        }

        $this->info("Сбор и обработка данных успешно завершены!");
    }
}
