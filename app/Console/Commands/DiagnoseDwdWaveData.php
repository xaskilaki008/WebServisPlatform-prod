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
        ];

        foreach (['12', '00'] as $runDir) {
            foreach (['swh', 'tm10', 'mwd'] as $parameter) {
                $results[] = $this->checkDwdIndex($runDir, $parameter);
            }
        }

        $results[] = $this->checkBeaches();

        $waveFetchService->saveDiagnostic($results);

        foreach ($results as $result) {
            $label = strtoupper($result['level'] ?? (!empty($result['ok']) ? 'ok' : 'error'));
            $line = sprintf('[%s] %s: %s', $label, $result['name'], $result['message']);

            match ($result['level'] ?? 'error') {
                'ok' => $this->info($line),
                'warning' => $this->warn($line),
                default => $this->error($line),
            };
        }

        return collect($results)->contains(fn (array $result) => ($result['level'] ?? 'error') === 'error')
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function checkWgrib2(): array
    {
        $path = env('WGRIB2_PATH', 'wgrib2');
        $resolvedPath = $this->resolveExecutable($path);

        if (!$resolvedPath) {
            return $this->result('error', 'wgrib2', "Файл wgrib2 не найден: {$path}");
        }

        try {
            $process = new Process([$resolvedPath, '-version']);
            $process->run();
            $output = trim($process->getOutput() ?: $process->getErrorOutput());

            if (!$process->isSuccessful() && $output === '') {
                return $this->result('error', 'wgrib2', trim($process->getErrorOutput()) ?: 'wgrib2 -version завершился с ошибкой.');
            }

            return $this->result('ok', 'wgrib2', $output ?: "Найден: {$resolvedPath}");
        } catch (Throwable $e) {
            return $this->result('error', 'wgrib2', $e->getMessage());
        }
    }

    private function checkBzip2(): array
    {
        return function_exists('bzdecompress')
            ? $this->result('ok', 'PHP BZIP2', 'Расширение BZIP2 включено.')
            : $this->result('error', 'PHP BZIP2', 'Функция bzdecompress недоступна.');
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return $this->result('ok', 'PostgreSQL', 'Подключение к БД работает.');
        } catch (Throwable $e) {
            return $this->result('error', 'PostgreSQL', $e->getMessage());
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
                ? $this->result('ok', 'Cache', 'Запись и чтение cache работают.')
                : $this->result('error', 'Cache', 'Cache не вернул тестовое значение.');
        } catch (Throwable $e) {
            return $this->result('error', 'Cache', $e->getMessage());
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

        if (!$options['curl_resolve_valid']) {
            return $this->result('error', 'DWD HTTP options', $message);
        }

        $level = $options['connection_mode'] === 'dns' ? 'ok' : 'warning';

        return $this->result($level, 'DWD HTTP options', $message);
    }

    private function checkDwdDns(): array
    {
        $host = parse_url((string) config('dwd.ewam_base_url'), PHP_URL_HOST) ?: 'opendata.dwd.de';
        $ips = gethostbynamel($host);
        $mode = $this->dwdHttpClient->diagnosticOptions()['connection_mode'];

        if (!$ips) {
            $level = $mode === 'dns' ? 'error' : 'warning';

            return $this->result($level, 'DWD DNS', "DNS не смог разрешить {$host}; режим HTTP={$mode}.");
        }

        return $this->result('ok', 'DWD DNS', "{$host} => " . implode(', ', $ips));
    }

    private function checkDwdIndex(string $runDir, string $parameter): array
    {
        $url = $this->dwdHttpClient->baseUrlForRun($runDir) . "{$parameter}/";
        $options = $this->dwdHttpClient->diagnosticOptions();
        $mode = $options['connection_mode'];
        $resolveStatus = $options['curl_resolve_status'];
        $autoResolveStatus = $options['auto_curl_resolve_status'];
        $proxyStatus = $options['proxy']
            ? 'proxy=' . $options['masked_proxy']
            : 'proxy не используется';
        $name = "DWD index {$runDir}/{$parameter}";

        if ($options['curl_resolve'] && !$options['curl_resolve_valid']) {
            return $this->result('error', $name, $options['curl_resolve_error']);
        }

        try {
            $response = $this->dwdHttpClient->get($url, (int) config('dwd.timeout', 30));

            if ($response->failed()) {
                return $this->result(
                    'error',
                    $name,
                    "HTTP {$response->status()} при GET {$url}; режим={$mode}; {$proxyStatus}; {$resolveStatus}; {$autoResolveStatus}"
                );
            }

            $body = $response->body();
            $containsIndex = str_contains($body, 'EWAM_')
                || str_contains($body, '.grib2.bz2')
                || str_contains($body, '<html');

            if (!$containsIndex) {
                return $this->result(
                    'error',
                    $name,
                    "GET {$url}: HTTP {$response->status()}, но ответ не похож на индекс DWD; режим={$mode}; {$proxyStatus}; {$resolveStatus}; {$autoResolveStatus}"
                );
            }

            return $this->result(
                'ok',
                $name,
                "GET {$url}: HTTP {$response->status()}; режим={$mode}; {$proxyStatus}; {$resolveStatus}; {$autoResolveStatus}"
            );
        } catch (Throwable $e) {
            return $this->result(
                'error',
                $name,
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
                ? $this->result('ok', 'Пляжи', "Пляжей с координатами DWD: {$count}.")
                : $this->result('error', 'Пляжи', 'Нет пляжей с fetch_latitude/fetch_longitude.');
        } catch (Throwable $e) {
            return $this->result('error', 'Пляжи', $e->getMessage());
        }
    }

    private function resolveExecutable(string $path): ?string
    {
        if (str_contains($path, '/') || str_contains($path, '\\')) {
            return is_file($path) ? $path : null;
        }

        return (new ExecutableFinder())->find($path);
    }

    private function result(string $level, string $name, string $message): array
    {
        $level = in_array($level, ['ok', 'warning', 'error'], true) ? $level : 'error';

        return [
            'ok' => $level !== 'error',
            'level' => $level,
            'name' => $name,
            'message' => $message,
        ];
    }
}
