<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Beach;
use App\Models\WaveForecast;
use Carbon\Carbon;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

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
        try {
            $dwdRun = $this->getLatestAvailableDwdRun();
        } catch (\RuntimeException $e) {
            Log::error('DWD EWAM: model run discovery failed', [
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $now = $dwdRun['server_now'];
        $modelRunHour = $dwdRun['model_run_hour'];
        $modelRunDir = $dwdRun['model_run_dir'];
        $modelRunAt = $dwdRun['model_run_at'];
        $baseDwdUrl = $dwdRun['base_url'];
        $selectedFiles = $dwdRun['files'];
        $savedCount = 0;
        $wgribErrors = 0;

        $this->info("DWD EWAM: выбрана папка {$modelRunDir} ({$baseDwdUrl})");
        $this->info("DWD EWAM: фактический model_run_at {$modelRunAt->toDateTimeString()} UTC");
        $this->info("DWD EWAM: wgrib2 path {$wgrib2Path}");
        Log::info('DWD EWAM: selected model run folder', [
            'folder' => $modelRunDir,
            'base_url' => $baseDwdUrl,
            'server_time' => $now->toDateTimeString(),
            'model_run_at' => $modelRunAt->toDateTimeString(),
            'selected_files' => $selectedFiles,
            'wgrib2_path' => $wgrib2Path,
        ]);

        if (!$this->wgrib2Exists($wgrib2Path)) {
            Log::error('DWD EWAM: wgrib2 binary was not found', [
                'wgrib2_path' => $wgrib2Path,
            ]);
            $this->error("wgrib2 binary was not found at {$wgrib2Path}. Check WGRIB2_PATH.");
            return self::FAILURE;
        }

        $wgribVersion = new Process([$wgrib2Path, '-version']);
        $wgribVersion->run();
        Log::info('DWD EWAM: wgrib2 version check', [
            'wgrib2_path' => $wgrib2Path,
            'exit_code' => $wgribVersion->getExitCode(),
            'stdout' => trim($wgribVersion->getOutput()),
            'stderr' => trim($wgribVersion->getErrorOutput()),
        ]);

        $beaches = Beach::all();

        if ($beaches->isEmpty()) {
            $this->error("В базе нет пляжей с координатами!");
            return self::FAILURE;
        }

        $parsedData = [];
        $sourceFiles = [];

        foreach ($this->parameters as $dwdDir => $dbColumn) {
            $this->info("Обработка параметра: {$dwdDir}...");

            $indexUrl = "{$baseDwdUrl}{$dwdDir}/";
            $latestFileName = $selectedFiles[$dwdDir] ?? null;
            if (!$latestFileName) {
                $message = "DWD EWAM: нет выбранного файла для параметра {$dwdDir}";
                $this->warn(" -> {$message}: {$indexUrl}");
                Log::warning($message, [
                    'folder' => $modelRunDir,
                    'parameter' => $dwdDir,
                    'url' => $indexUrl,
                    'selected_files' => $selectedFiles,
                ]);
                continue;
            }

            $sourceFiles[$dwdDir] = $latestFileName;
            $fileUrl = $indexUrl . $latestFileName;
            Log::info('DWD EWAM: selected source file', [
                'parameter' => $dwdDir,
                'url' => $fileUrl,
                'file' => $latestFileName,
            ]);

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
                    Log::error('DWD EWAM: bz2 decompression failed', [
                        'parameter' => $dwdDir,
                        'url' => $fileUrl,
                        'status' => $fileResponse->status(),
                        'body_size' => strlen($fileResponse->body()),
                    ]);
                    continue;
                }

                // 5. Сохраняем чистый GRIB2 файл напрямую по физическому пути
                file_put_contents($filePath, $gribContent);
            } catch (\Exception $e) {
                $this->error(" -> Исключение при скачивании/распаковке: " . $e->getMessage());
                Log::error('DWD EWAM: source download/decompression exception', [
                    'parameter' => $dwdDir,
                    'url' => $fileUrl,
                    'error' => $e->getMessage(),
                ]);
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

                $process = new Process([
                    $wgrib2Path,
                    $gribFileName,
                    '-lon',
                    (string) $beach->fetch_longitude,
                    (string) $beach->fetch_latitude,
                ], $storageDir);
                $process->run();

                if (!$process->isSuccessful()) {
                    $wgribErrors++;
                    $this->warn(" -> wgrib2 error for '{$beach->name}': " . trim($process->getErrorOutput()));
                    Log::warning('DWD EWAM: wgrib2 failed for beach', [
                        'beach_id' => $beach->id,
                        'beach_name' => $beach->name,
                        'parameter' => $dwdDir,
                        'wgrib2_path' => $wgrib2Path,
                        'working_directory' => $storageDir,
                        'file' => $gribFileName,
                        'longitude' => $beach->fetch_longitude,
                        'latitude' => $beach->fetch_latitude,
                        'exit_code' => $process->getExitCode(),
                        'stdout' => trim($process->getOutput()),
                        'stderr' => trim($process->getErrorOutput()),
                    ]);
                    continue;
                }

                $output = $process->getOutput();

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
            $parsedAt = now();

            foreach ($parsedData as $beachId => $data) {
                $forecast = WaveForecast::updateOrCreate(
                    [
                        'beach_id' => $beachId,
                        'model_run_at' => $modelRunAt,
                    ],
                    array_merge($data, [
                        'forecast_time' => $forecastTime,
                        'model_run_hour' => $modelRunHour,
                        'parsed_at' => $parsedAt,
                        'source_files' => $sourceFiles,
                    ])
                );

                Log::debug('DWD EWAM: parsed forecast saved', [
                    'beach_id' => $forecast->beach_id,
                    'source_folder' => $modelRunDir,
                    'source_files' => $forecast->source_files,
                    'parsed_at' => $forecast->parsed_at?->toDateTimeString(),
                    'model_run_at' => $forecast->model_run_at?->toDateTimeString(),
                    'forecast_time' => $forecast->forecast_time?->toDateTimeString(),
                    'wave_height' => $forecast->wave_height,
                    'wave_period' => $forecast->wave_period,
                    'wave_direction' => $forecast->wave_direction,
                    // These values are currently not extracted from the DWD GRIB parameters used by this parser.
                    'air_temp' => $forecast->air_temp,
                    'water_temp' => $forecast->water_temp,
                ]);
                $savedCount++;
            }
        }

        Log::info('DWD EWAM: fetch completed', [
            'base_url' => $baseDwdUrl,
            'wgrib2_path' => $wgrib2Path,
            'model_run_at' => $modelRunAt->toDateTimeString(),
            'saved_forecasts' => $savedCount,
            'wgrib_errors' => $wgribErrors,
            'parsed_beaches' => count($parsedData),
            'source_files' => $sourceFiles,
        ]);
        $this->info("DWD EWAM: saved forecasts {$savedCount}, wgrib2 errors {$wgribErrors}.");
        $this->info("Сбор и обработка данных успешно завершены!");
        return self::SUCCESS;
    }

    private function getLatestAvailableDwdRun(): array
    {
        $serverNow = Carbon::now();
        $candidates = [];

        foreach ([12, 0] as $runHour) {
            $runDir = str_pad((string) $runHour, 2, '0', STR_PAD_LEFT);
            $baseUrl = "https://opendata.dwd.de/weather/maritime/wave_models/ewam/grib/{$runDir}/";
            $files = [];
            $runTimes = [];

            foreach (array_keys($this->parameters) as $dwdDir) {
                $indexUrl = "{$baseUrl}{$dwdDir}/";
                Log::info('DWD EWAM: fetching parameter index', [
                    'parameter' => $dwdDir,
                    'url' => $indexUrl,
                ]);

                try {
                    $indexResponse = Http::withoutVerifying()->get($indexUrl);
                    if ($indexResponse->failed()) {
                        Log::error('DWD EWAM: parameter index request failed', [
                            'parameter' => $dwdDir,
                            'url' => $indexUrl,
                            'status' => $indexResponse->status(),
                            'body_sample' => substr($indexResponse->body(), 0, 500),
                        ]);
                        continue 2;
                    }
                } catch (\Exception $e) {
                    Log::error('DWD EWAM: parameter index request exception', [
                        'parameter' => $dwdDir,
                        'url' => $indexUrl,
                        'error' => $e->getMessage(),
                    ]);
                    continue 2;
                }

                $pattern = '/(EWAM_[A-Z0-9_]+_(\d{8})' . $runDir . '_000\.grib2\.bz2)/i';
                if (!preg_match_all($pattern, $indexResponse->body(), $matches, PREG_SET_ORDER)) {
                    Log::warning('DWD EWAM: no files for candidate run folder', [
                        'folder' => $runDir,
                        'parameter' => $dwdDir,
                        'url' => $indexUrl,
                        'pattern' => $pattern,
                    ]);
                    continue 2;
                }

                $latestMatch = end($matches);
                $files[$dwdDir] = $latestMatch[1];
                $runTimes[$dwdDir] = Carbon::createFromFormat('Ymd H', "{$latestMatch[2]} {$runDir}", 'UTC');
            }

            $uniqueRunTimes = collect($runTimes)
                ->map(fn (Carbon $runTime) => $runTime->toDateTimeString())
                ->unique()
                ->values();

            if ($uniqueRunTimes->count() !== 1) {
                Log::warning('DWD EWAM: parameter files do not belong to the same model run', [
                    'folder' => $runDir,
                    'files' => $files,
                    'run_times' => collect($runTimes)->map->toDateTimeString()->all(),
                ]);
                continue;
            }

            $modelRunAt = reset($runTimes);
            if ($modelRunAt->greaterThan($serverNow)) {
                Log::warning('DWD EWAM: skipping future model run', [
                    'folder' => $runDir,
                    'server_time' => $serverNow->toDateTimeString(),
                    'model_run_at' => $modelRunAt->toDateTimeString(),
                    'files' => $files,
                ]);
                continue;
            }

            $candidates[] = [
                'server_now' => $serverNow,
                'model_run_hour' => (int) $modelRunAt->hour,
                'model_run_dir' => $runDir,
                'model_run_at' => $modelRunAt,
                'base_url' => $baseUrl,
                'files' => $files,
            ];
        }

        if (empty($candidates)) {
            throw new \RuntimeException('DWD EWAM: no complete model run found in 00 or 12 folders.');
        }

        usort(
            $candidates,
            fn (array $left, array $right) => $right['model_run_at']->timestamp <=> $left['model_run_at']->timestamp
        );

        return $candidates[0];
    }

    private function wgrib2Exists(string $wgrib2Path): bool
    {
        if (str_contains($wgrib2Path, '/') || str_contains($wgrib2Path, '\\')) {
            return is_file($wgrib2Path);
        }

        return (bool) (new ExecutableFinder())->find($wgrib2Path);
    }
}
