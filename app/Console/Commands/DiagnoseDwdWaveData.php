<?php

namespace App\Console\Commands;

use App\Models\Beach;
use App\Services\DwdHttpClient;
use App\Services\WaveFetchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class DiagnoseDwdWaveData extends Command
{
    protected $signature = 'wave:diagnose';
    protected $description = 'Check DWD EWAM parser dependencies without changing forecasts';

    private DwdHttpClient $dwdHttpClient;

    public function handle(WaveFetchService $waveFetchService, DwdHttpClient $dwdHttpClient): int
    {
        $this->dwdHttpClient = $dwdHttpClient;
        $results = [
            $this->checkWgrib2(),
            $this->checkBzip2(),
            $this->checkDatabase(),
            $this->checkCache(),
            $this->checkDwdHttpOptions(),
            $this->checkDwdDns(),
            $this->checkDwdIndex('12'),
            $this->checkDwdIndex('00'),
            $this->checkBeaches(),
        ];

        $waveFetchService->saveDiagnostic($results);

        foreach ($results as $result) {
            $line = sprintf(
                '[%s] %s: %s',
                $result['ok'] ? 'OK' : 'FAIL',
                $result['name'],
                $result['message']
            );

            $result['ok'] ? $this->info($line) : $this->error($line);
        }

        return collect($results)->every(fn (array $result) => $result['ok'])
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function checkWgrib2(): array
    {
        $path = env('WGRIB2_PATH', 'wgrib2');
        $resolvedPath = $this->resolveExecutable($path);

        if (!$resolvedPath) {
            return $this->result(false, 'wgrib2', "Файл wgrib2 не найден: {$path}");
        }

        try {
            $process = new Process([$resolvedPath, '-version']);
            $process->run();
            $output = trim($process->getOutput() ?: $process->getErrorOutput());

            if (!$process->isSuccessful() && $output === '') {
                return $this->result(false, 'wgrib2', trim($process->getErrorOutput()) ?: 'wgrib2 -version завершился с ошибкой.');
            }

            return $this->result(true, 'wgrib2', $output ?: "Найден: {$resolvedPath}");
        } catch (Throwable $e) {
            return $this->result(false, 'wgrib2', $e->getMessage());
        }
    }

    private function checkBzip2(): array
    {
        return function_exists('bzdecompress')
            ? $this->result(true, 'PHP BZIP2', 'Расширение BZIP2 включено.')
            : $this->result(false, 'PHP BZIP2', 'Функция bzdecompress недоступна.');
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->result(true, 'PostgreSQL', 'Подключение к БД работает.');
        } catch (Throwable $e) {
            return $this->result(false, 'PostgreSQL', $e->getMessage());
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'wave_diagnose_' . now()->timestamp;
            Cache::put($key, 'ok', now()->addMinute());
            $ok = Cache::get($key) === 'ok';
            Cache::forget($key);

            return $ok
                ? $this->result(true, 'Cache', 'Запись и чтение cache работают.')
                : $this->result(false, 'Cache', 'Cache не вернул тестовое значение.');
        } catch (Throwable $e) {
            return $this->result(false, 'Cache', $e->getMessage());
        }
    }

    private function checkDwdHttpOptions(): array
    {
        $options = $this->dwdHttpClient->diagnosticOptions();
        $message = 'DWD_EWAM_BASE_URL=' . $options['base_url'];
        $message .= '; connect_timeout=' . $options['connect_timeout'];
        $message .= '; timeout=' . $options['timeout'];
        $message .= '; retries=' . $options['retries'];
        $message .= '; retry_delay_ms=' . $options['retry_delay_ms'];
        $message .= $options['proxy']
            ? '; DWD_HTTP_PROXY включён: ' . $options['masked_proxy']
            : '; DWD_HTTP_PROXY не задан';
        $message .= $options['curl_resolve']
            ? '; DWD_CURL_RESOLVE=' . $options['curl_resolve']
            : '; DWD_CURL_RESOLVE не задан';
        $message .= '; ' . $options['curl_resolve_status'];
        $message .= '; ' . $options['auto_curl_resolve_status'];
        $message .= '; режим=' . $options['connection_mode'];

        return $this->result($options['curl_resolve_valid'], 'DWD HTTP options', $message);
    }

    private function checkDwdDns(): array
    {
        $host = parse_url((string) config('dwd.ewam_base_url'), PHP_URL_HOST) ?: 'opendata.dwd.de';
        $ips = gethostbynamel($host);

        if (!$ips) {
            return $this->result(false, 'DWD DNS', "DNS не смог разрешить {$host}.");
        }

        return $this->result(true, 'DWD DNS', "{$host} => " . implode(', ', $ips));
    }

    private function checkDwdIndex(string $runDir): array
    {
        $url = $this->dwdHttpClient->baseUrlForRun($runDir) . 'swh/';
        $options = $this->dwdHttpClient->diagnosticOptions();
        $mode = $options['connection_mode'];
        $resolveStatus = $options['curl_resolve_status'];
        $autoResolveStatus = $options['auto_curl_resolve_status'];
        $proxyStatus = $options['proxy']
            ? 'proxy=' . $options['masked_proxy']
            : 'proxy не используется';

        if ($options['curl_resolve'] && !$options['curl_resolve_valid']) {
            return $this->result(false, "DWD index {$runDir}/swh", $options['curl_resolve_error']);
        }

        try {
            $response = $this->dwdHttpClient->get($url, (int) config('dwd.timeout', 30));

            if ($response->failed()) {
                return $this->result(
                    false,
                    "DWD index {$runDir}/swh",
                    "HTTP {$response->status()} при GET {$url}; режим={$mode}; {$proxyStatus}; {$resolveStatus}; {$autoResolveStatus}"
                );
            }

            $body = $response->body();
            $containsIndex = str_contains($body, 'EWAM_')
                || str_contains($body, '.grib2.bz2')
                || str_contains($body, '<html');

            if (!$containsIndex) {
                return $this->result(
                    false,
                    "DWD index {$runDir}/swh",
                    "GET {$url}: HTTP {$response->status()}, но ответ не похож на индекс DWD; режим={$mode}; {$proxyStatus}; {$resolveStatus}; {$autoResolveStatus}"
                );
            }

            return $this->result(
                true,
                "DWD index {$runDir}/swh",
                "GET {$url}: HTTP {$response->status()}; режим={$mode}; {$proxyStatus}; {$resolveStatus}; {$autoResolveStatus}"
            );
        } catch (Throwable $e) {
            return $this->result(
                false,
                "DWD index {$runDir}/swh",
                "{$e->getMessage()}; режим={$mode}; {$proxyStatus}; {$resolveStatus}; {$autoResolveStatus}"
            );
        }
    }

    private function checkBeaches(): array
    {
        try {
            $count = Beach::query()
                ->whereNotNull('fetch_latitude')
                ->whereNotNull('fetch_longitude')
                ->count();

            return $count > 0
                ? $this->result(true, 'Пляжи', "Пляжей с координатами DWD: {$count}.")
                : $this->result(false, 'Пляжи', 'Нет пляжей с fetch_latitude/fetch_longitude.');
        } catch (Throwable $e) {
            return $this->result(false, 'Пляжи', $e->getMessage());
        }
    }

    private function resolveExecutable(string $path): ?string
    {
        if (str_contains($path, '/') || str_contains($path, '\\')) {
            return is_file($path) ? $path : null;
        }

        return (new ExecutableFinder())->find($path);
    }

    private function result(bool $ok, string $name, string $message): array
    {
        return [
            'ok' => $ok,
            'name' => $name,
            'message' => $message,
        ];
    }
}
