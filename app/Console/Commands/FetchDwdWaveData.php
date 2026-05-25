<?php

namespace App\Console\Commands;

use App\Models\Beach;
use App\Models\WaveForecast;
use App\Services\WaveFetchService;
use App\Services\WaveForecastSelector;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class FetchDwdWaveData extends Command
{
    protected $signature = 'wave:fetch';
    protected $description = 'Parsing wave forecast data from DWD EWAM GRIB2 files';

    private const FORECAST_HOUR_LIMIT = 24;
    private const MODEL_TIMEZONE = 'Europe/Berlin';

    private array $parameters = [
        'swh' => 'wave_height',
        'tm10' => 'wave_period',
        'mwd' => 'wave_direction',
    ];

    public function handle(WaveForecastSelector $forecastSelector, WaveFetchService $waveFetchService): int
    {
        $this->info('Starting DWD EWAM data fetch...');

        $wgrib2Path = env('WGRIB2_PATH', 'wgrib2');

        try {
            $dwdRun = $this->getLatestAvailableDwdRun();
        } catch (\RuntimeException $e) {
            Log::error('DWD EWAM: model run discovery failed', [
                'error' => $e->getMessage(),
            ]);
            $this->error($e->getMessage());
            $waveFetchService->markFailed($e->getMessage());

            return self::FAILURE;
        }

        $modelRunHour = $dwdRun['model_run_hour'];
        $modelRunDir = $dwdRun['model_run_dir'];
        $modelRunAt = $dwdRun['model_run_at'];
        $baseDwdUrl = $dwdRun['base_url'];
        $selectedFilesByHour = $dwdRun['files_by_hour'];
        $savedCount = 0;
        $wgribErrors = 0;

        $this->info("DWD EWAM: selected folder {$modelRunDir} ({$baseDwdUrl})");
        $this->info("DWD EWAM: model_run_at {$modelRunAt->toDateTimeString()} UTC");
        $this->info("DWD EWAM: forecast hours 0-" . self::FORECAST_HOUR_LIMIT);
        $this->info("DWD EWAM: wgrib2 path {$wgrib2Path}");

        Log::info('DWD EWAM: selected model run folder', [
            'folder' => $modelRunDir,
            'base_url' => $baseDwdUrl,
            'server_time' => now('UTC')->toDateTimeString(),
            'model_run_at' => $modelRunAt->toDateTimeString(),
            'forecast_hours' => array_keys($selectedFilesByHour),
            'wgrib2_path' => $wgrib2Path,
        ]);

        if (!$this->wgrib2Exists($wgrib2Path)) {
            Log::error('DWD EWAM: wgrib2 binary was not found', [
                'wgrib2_path' => $wgrib2Path,
            ]);
            $this->error("wgrib2 binary was not found at {$wgrib2Path}. Check WGRIB2_PATH.");
            $waveFetchService->markFailed('wgrib2 binary was not found.');

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
            $this->error('No beaches with coordinates found in database.');
            $waveFetchService->markFailed('No beaches with coordinates found in database.');

            return self::FAILURE;
        }

        $parsedData = [];
        $sourceFilesByHour = [];
        $storageDir = storage_path('app');

        foreach ($selectedFilesByHour as $forecastHour => $filesByParameter) {
            foreach ($this->parameters as $dwdDir => $dbColumn) {
                $latestFileName = $filesByParameter[$dwdDir] ?? null;

                if (!$latestFileName) {
                    Log::warning('DWD EWAM: missing selected source file', [
                        'forecast_hour' => $forecastHour,
                        'parameter' => $dwdDir,
                    ]);
                    continue;
                }

                $sourceFilesByHour[$forecastHour][$dwdDir] = $latestFileName;
                $fileUrl = "{$baseDwdUrl}{$dwdDir}/{$latestFileName}";
                $gribFileName = "latest_{$dwdDir}_{$forecastHour}.grib2";
                $filePath = storage_path("app/{$gribFileName}");

                $this->line(" -> Downloading {$latestFileName}...");

                try {
                    $fileResponse = Http::withoutVerifying()->timeout(120)->get($fileUrl);

                    if ($fileResponse->failed()) {
                        Log::error('DWD EWAM: source download failed', [
                            'url' => $fileUrl,
                            'status' => $fileResponse->status(),
                            'body_sample' => substr($fileResponse->body(), 0, 500),
                        ]);
                        continue;
                    }

                    if (!function_exists('bzdecompress')) {
                        $this->error('PHP BZIP2 extension is not enabled.');
                        $waveFetchService->markFailed('PHP BZIP2 extension is not enabled.');

                        return self::FAILURE;
                    }

                    $gribContent = bzdecompress($fileResponse->body());

                    if (!$gribContent) {
                        Log::error('DWD EWAM: bz2 decompression failed', [
                            'parameter' => $dwdDir,
                            'url' => $fileUrl,
                            'status' => $fileResponse->status(),
                            'body_size' => strlen($fileResponse->body()),
                        ]);
                        continue;
                    }

                    file_put_contents($filePath, $gribContent);
                } catch (\Exception $e) {
                    Log::error('DWD EWAM: source download/decompression exception', [
                        'parameter' => $dwdDir,
                        'url' => $fileUrl,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }

                foreach ($beaches as $beach) {
                    if (empty($beach->fetch_longitude) || empty($beach->fetch_latitude)) {
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
                        Log::warning('DWD EWAM: wgrib2 failed for beach', [
                            'beach_id' => $beach->id,
                            'beach_name' => $beach->name,
                            'forecast_hour' => $forecastHour,
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

                    if ($process->getOutput() && preg_match('/val=([0-9\.\-]+)/', $process->getOutput(), $valMatches)) {
                        $parsedData[$forecastHour][$beach->id][$dbColumn] = (float) $valMatches[1];
                    }
                }

                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        if (!empty($parsedData)) {
            $this->info('Saving parsed data...');
            $parsedAt = now('UTC');

            foreach ($parsedData as $forecastHour => $beachesData) {
                $forecastTime = $modelRunAt->copy()->addHours((int) $forecastHour);

                foreach ($beachesData as $beachId => $data) {
                    $forecast = WaveForecast::updateOrCreate(
                        [
                            'beach_id' => $beachId,
                            'model_run_at' => $modelRunAt,
                            'forecast_hour' => (int) $forecastHour,
                        ],
                        array_merge($data, [
                            'forecast_time' => $forecastTime,
                            'model_run_hour' => $modelRunHour,
                            'parsed_at' => $parsedAt,
                            'source_files' => $sourceFilesByHour[$forecastHour] ?? [],
                        ])
                    );

                    Log::debug('DWD EWAM: parsed forecast saved', [
                        'beach_id' => $forecast->beach_id,
                        'source_folder' => $modelRunDir,
                        'forecast_hour' => $forecast->forecast_hour,
                        'source_files' => $forecast->source_files,
                        'parsed_at' => $forecast->parsed_at?->toDateTimeString(),
                        'model_run_at' => $forecast->model_run_at?->toDateTimeString(),
                        'forecast_time' => $forecast->forecast_time?->toDateTimeString(),
                        'wave_height' => $forecast->wave_height,
                        'wave_period' => $forecast->wave_period,
                        'wave_direction' => $forecast->wave_direction,
                    ]);
                    $savedCount++;
                }
            }
        }

        $updatedLevels = $forecastSelector->refreshBeachWaveLevels(now('UTC'));
        Cache::put('wave_forecast_cache_version', now('UTC')->timestamp);

        Log::info('DWD EWAM: fetch completed', [
            'base_url' => $baseDwdUrl,
            'wgrib2_path' => $wgrib2Path,
            'model_run_at' => $modelRunAt->toDateTimeString(),
            'saved_forecasts' => $savedCount,
            'updated_beach_wave_levels' => $updatedLevels,
            'wgrib_errors' => $wgribErrors,
            'parsed_forecast_hours' => array_keys($parsedData),
            'source_files' => $sourceFilesByHour,
        ]);
        $this->info("DWD EWAM: saved forecasts {$savedCount}, updated beach levels {$updatedLevels}, wgrib2 errors {$wgribErrors}.");
        $this->info('DWD EWAM data fetch completed.');
        $waveFetchService->markCompleted();

        return self::SUCCESS;
    }

    public function parseModelRunFromFilename(string $filename): Carbon
    {
        if (!preg_match('/_(\d{10})_(\d{3})\.grib2\.bz2$/i', $filename, $matches)) {
            throw new \InvalidArgumentException("Invalid DWD EWAM filename: {$filename}");
        }

        return Carbon::createFromFormat('YmdH', $matches[1], self::MODEL_TIMEZONE)
            ->setTimezone('UTC');
    }

    public function parseForecastHourFromFilename(string $filename): int
    {
        if (!preg_match('/_(\d{10})_(\d{3})\.grib2\.bz2$/i', $filename, $matches)) {
            throw new \InvalidArgumentException("Invalid DWD EWAM filename: {$filename}");
        }

        return (int) $matches[2];
    }

    private function getLatestAvailableDwdRun(): array
    {
        $serverNow = now('UTC');
        $candidates = [];

        foreach ([12, 0] as $runHour) {
            $runDir = str_pad((string) $runHour, 2, '0', STR_PAD_LEFT);
            $baseUrl = "https://opendata.dwd.de/weather/maritime/wave_models/ewam/grib/{$runDir}/";
            $filesByHour = [];
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

                $pattern = '/(EWAM_[A-Z0-9_]+_(\d{8}' . $runDir . ')_(\d{3})\.grib2\.bz2)/i';

                if (!preg_match_all($pattern, $indexResponse->body(), $matches, PREG_SET_ORDER)) {
                    Log::warning('DWD EWAM: no files for candidate run folder', [
                        'folder' => $runDir,
                        'parameter' => $dwdDir,
                        'url' => $indexUrl,
                        'pattern' => $pattern,
                    ]);
                    continue 2;
                }

                foreach ($matches as $match) {
                    $fileName = $match[1];
                    $forecastHour = $this->parseForecastHourFromFilename($fileName);

                    if ($forecastHour > self::FORECAST_HOUR_LIMIT) {
                        continue;
                    }

                    $filesByHour[$forecastHour][$dwdDir] = $fileName;
                    $runTimes[$forecastHour][$dwdDir] = $this->parseModelRunFromFilename($fileName);
                }
            }

            $completeFilesByHour = [];
            $completeRunTimes = [];

            foreach ($filesByHour as $forecastHour => $files) {
                if (count(array_intersect(array_keys($this->parameters), array_keys($files))) !== count($this->parameters)) {
                    continue;
                }

                $hourRunTimes = collect($runTimes[$forecastHour] ?? [])
                    ->map(fn (Carbon $runTime) => $runTime->toDateTimeString())
                    ->unique()
                    ->values();

                if ($hourRunTimes->count() !== 1) {
                    Log::warning('DWD EWAM: parameter files do not belong to the same model run', [
                        'folder' => $runDir,
                        'forecast_hour' => $forecastHour,
                        'files' => $files,
                        'run_times' => collect($runTimes[$forecastHour] ?? [])->map->toDateTimeString()->all(),
                    ]);
                    continue;
                }

                $completeFilesByHour[(int) $forecastHour] = $files;
                $completeRunTimes[] = $hourRunTimes->first();
            }

            if (empty($completeFilesByHour)) {
                continue;
            }

            $uniqueRunTimes = collect($completeRunTimes)->unique()->values();

            if ($uniqueRunTimes->count() !== 1) {
                Log::warning('DWD EWAM: forecast hours do not belong to the same model run', [
                    'folder' => $runDir,
                    'run_times' => $uniqueRunTimes->all(),
                ]);
                continue;
            }

            $modelRunAt = Carbon::parse($uniqueRunTimes->first(), 'UTC');

            if ($modelRunAt->greaterThan($serverNow)) {
                Log::warning('DWD EWAM: skipping future model run', [
                    'folder' => $runDir,
                    'server_time' => $serverNow->toDateTimeString(),
                    'model_run_at' => $modelRunAt->toDateTimeString(),
                ]);
                continue;
            }

            ksort($completeFilesByHour);

            $candidates[] = [
                'model_run_hour' => $runHour,
                'model_run_dir' => $runDir,
                'model_run_at' => $modelRunAt,
                'base_url' => $baseUrl,
                'files_by_hour' => $completeFilesByHour,
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
