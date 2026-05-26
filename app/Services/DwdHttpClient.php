<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DwdHttpClient
{
    public function __construct(private readonly WaveFetchService $waveFetchService)
    {
    }

    public function get(string $url, int $timeout = 30): Response
    {
        $attempts = max(1, (int) config('dwd.http_retries', 3));
        $lastException = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->request($timeout, $url)->get($url);

                if (!$this->shouldRetryResponse($response) || $attempt === $attempts) {
                    return $response;
                }

                $this->logRetry($url, $attempt, $attempts, 'HTTP ' . $response->status());
                $this->sleepBeforeRetry($response);
            } catch (ConnectionException $e) {
                $lastException = $e;

                if ($attempt === $attempts || !$this->shouldRetryException($e)) {
                    throw $e;
                }

                $this->logRetry($url, $attempt, $attempts, $e->getMessage());
                $this->sleepBeforeRetry();
            }
        }

        throw $lastException ?? new ConnectionException("DWD request failed: {$url}");
    }

    public function baseUrlForRun(string $runDir): string
    {
        return rtrim((string) config(
            'dwd.ewam_base_url',
            'https://opendata.dwd.de/weather/maritime/wave_models/ewam/grib'
        ), '/') . "/{$runDir}/";
    }

    public function testUrl(): string
    {
        return $this->baseUrlForRun('12') . 'swh/';
    }

    public function diagnosticOptions(): array
    {
        $proxy = $this->proxy();
        $curlResolve = $this->curlResolve();
        $resolveValidation = $this->validateCurlResolve($curlResolve);

        return [
            'proxy' => $proxy,
            'masked_proxy' => $proxy ? $this->maskProxy($proxy) : null,
            'curl_resolve' => $curlResolve,
            'curl_resolve_valid' => $resolveValidation['valid'],
            'curl_resolve_error' => $resolveValidation['error'],
            'curl_resolve_applied' => !$proxy && $curlResolve && $resolveValidation['valid'],
            'curl_resolve_status' => $this->curlResolveStatus($proxy, $curlResolve, $resolveValidation),
            'auto_curl_resolve' => (bool) config('dwd.auto_curl_resolve', true),
            'auto_curl_resolve_status' => $this->autoCurlResolveStatus($proxy, $curlResolve, $resolveValidation),
            'base_url' => config('dwd.ewam_base_url') ?: 'https://opendata.dwd.de/weather/maritime/wave_models/ewam/grib',
            'connect_timeout' => (int) config('dwd.connect_timeout', 10),
            'timeout' => (int) config('dwd.timeout', 30),
            'retries' => max(1, (int) config('dwd.http_retries', 3)),
            'retry_delay_ms' => max(0, (int) config('dwd.retry_delay_ms', 2000)),
            'connection_mode' => $this->connectionMode(),
        ];
    }

    public function connectionMode(): string
    {
        $options = $this->diagnosticOptionsWithoutMode();

        if ($options['proxy']) {
            return 'proxy';
        }

        if ($options['curl_resolve'] && $options['curl_resolve_valid']) {
            return 'curl_resolve';
        }

        if ($options['auto_curl_resolve']) {
            return 'auto_curl_resolve';
        }

        return 'dns';
    }

    public function connectionSummary(): string
    {
        $options = $this->diagnosticOptions();

        if ($options['proxy']) {
            return 'proxy=' . $options['masked_proxy'] . '; CURLOPT_RESOLVE не применён, потому что используется proxy';
        }

        if ($options['curl_resolve']) {
            return $options['curl_resolve_status'];
        }

        if ($options['auto_curl_resolve']) {
            return $options['auto_curl_resolve_status'];
        }

        return 'обычный DNS; proxy не используется; CURLOPT_RESOLVE не задан';
    }

    public function curlResolveEntries(): array
    {
        $curlResolve = $this->curlResolve();
        $validation = $this->validateCurlResolve($curlResolve);

        if ($this->proxy() || !$curlResolve || !$validation['valid']) {
            return [];
        }

        return array_map('trim', array_filter(explode(',', $curlResolve)));
    }

    public function maskProxy(?string $proxy): ?string
    {
        if (!$proxy) {
            return null;
        }

        $parts = parse_url($proxy);

        if (!$parts || !isset($parts['user'])) {
            return $proxy;
        }

        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $user = $parts['user'];
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $parts['path'] ?? '';

        return "{$scheme}{$user}:***@{$host}{$port}{$path}";
    }

    private function request(int $timeout, string $url): PendingRequest
    {
        $request = Http::withoutVerifying()
            ->connectTimeout((int) config('dwd.connect_timeout', 10))
            ->timeout($timeout ?: (int) config('dwd.timeout', 30));
        $options = [];

        if ($proxy = $this->proxy()) {
            $options['proxy'] = $proxy;
        } elseif ($this->curlResolve() && !$this->validateCurlResolve($this->curlResolve())['valid']) {
            throw new \InvalidArgumentException($this->validateCurlResolve($this->curlResolve())['error']);
        } elseif ($curlResolveEntries = $this->curlResolveEntries()) {
            $options['curl'][CURLOPT_RESOLVE] = $curlResolveEntries;
        } elseif ($autoResolveEntries = $this->autoCurlResolveEntries($url)) {
            $options['curl'][CURLOPT_RESOLVE] = $autoResolveEntries;
        }

        return $options ? $request->withOptions($options) : $request;
    }

    private function proxy(): ?string
    {
        return config('dwd.http_proxy') ?: null;
    }

    private function curlResolve(): ?string
    {
        return config('dwd.curl_resolve') ?: null;
    }

    private function autoCurlResolveEntries(string $url): array
    {
        if (!(bool) config('dwd.auto_curl_resolve', true) || $this->proxy() || $this->curlResolve()) {
            return [];
        }

        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $port = parse_url($url, PHP_URL_PORT) ?: ($scheme === 'https' ? 443 : 80);

        if (!$host) {
            return [];
        }

        $baseHost = parse_url((string) config('dwd.ewam_base_url'), PHP_URL_HOST);

        if ($baseHost && $host !== $baseHost) {
            return [];
        }

        $ips = gethostbynamel($host) ?: [];
        $ip = $ips[0] ?? null;

        return $ip ? ["{$host}:{$port}:{$ip}"] : [];
    }

    private function validateCurlResolve(?string $curlResolve): array
    {
        if (!$curlResolve) {
            return ['valid' => true, 'error' => null];
        }

        foreach (array_map('trim', array_filter(explode(',', $curlResolve))) as $entry) {
            if (!preg_match('/^[A-Za-z0-9.-]+:\d{1,5}:[A-Fa-f0-9:.]+$/', $entry)) {
                return [
                    'valid' => false,
                    'error' => 'Неверный формат DWD_CURL_RESOLVE. Ожидается host:port:ip',
                ];
            }

            $port = (int) explode(':', $entry, 3)[1];

            if ($port < 1 || $port > 65535) {
                return [
                    'valid' => false,
                    'error' => 'Неверный порт в DWD_CURL_RESOLVE. Ожидается host:port:ip',
                ];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    private function curlResolveStatus(?string $proxy, ?string $curlResolve, array $validation): string
    {
        if (!$curlResolve) {
            return 'CURLOPT_RESOLVE не задан';
        }

        if (!$validation['valid']) {
            return $validation['error'];
        }

        if ($proxy) {
            return 'CURLOPT_RESOLVE не применён, потому что используется proxy';
        }

        return 'CURLOPT_RESOLVE применён: ' . $curlResolve;
    }

    private function autoCurlResolveStatus(?string $proxy, ?string $curlResolve, array $validation): string
    {
        if (!(bool) config('dwd.auto_curl_resolve', true)) {
            return 'auto CURLOPT_RESOLVE отключён';
        }

        if ($proxy) {
            return 'auto CURLOPT_RESOLVE не применён, потому что используется proxy';
        }

        if ($curlResolve && $validation['valid']) {
            return 'auto CURLOPT_RESOLVE не применён, потому что задан DWD_CURL_RESOLVE';
        }

        if ($curlResolve && !$validation['valid']) {
            return 'auto CURLOPT_RESOLVE не применён, потому что DWD_CURL_RESOLVE неверен';
        }

        $host = parse_url((string) config('dwd.ewam_base_url'), PHP_URL_HOST) ?: 'opendata.dwd.de';
        $ips = gethostbynamel($host) ?: [];
        $ip = $ips[0] ?? null;

        return $ip
            ? "auto CURLOPT_RESOLVE применён: {$host}:443:{$ip}"
            : "auto CURLOPT_RESOLVE не применён: DNS не вернул IP для {$host}";
    }

    private function diagnosticOptionsWithoutMode(): array
    {
        $proxy = $this->proxy();
        $curlResolve = $this->curlResolve();
        $resolveValidation = $this->validateCurlResolve($curlResolve);

        return [
            'proxy' => $proxy,
            'curl_resolve' => $curlResolve,
            'curl_resolve_valid' => $resolveValidation['valid'],
            'auto_curl_resolve' => (bool) config('dwd.auto_curl_resolve', true),
        ];
    }

    private function shouldRetryResponse(Response $response): bool
    {
        return $response->status() === 429 || $response->serverError();
    }

    private function shouldRetryException(Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'cURL error 6')
            || str_contains($message, 'cURL error 7')
            || str_contains($message, 'timed out')
            || str_contains($message, 'Connection timed out')
            || str_contains($message, 'Could not resolve')
            || str_contains($message, 'Failed to connect');
    }

    private function sleepBeforeRetry(?Response $response = null): void
    {
        $seconds = null;

        if ($response && $response->status() === 429 && $response->header('Retry-After')) {
            $retryAfter = $response->header('Retry-After');
            $seconds = is_numeric($retryAfter)
                ? (int) $retryAfter
                : max(0, strtotime($retryAfter) - time());
        }

        if ($seconds === null) {
            $seconds = max(0, (int) config('dwd.retry_delay_ms', 2000)) / 1000;
        }

        if ($seconds > 0) {
            usleep((int) ($seconds * 1000000));
        }
    }

    private function logRetry(string $url, int $attempt, int $attempts, string $error): void
    {
        $message = "attempt {$attempt}/{$attempts}; {$url}; {$error}";

        Log::warning('DWD HTTP retry', [
            'url' => $url,
            'attempt' => $attempt,
            'attempts' => $attempts,
            'error' => $error,
            'connection' => $this->connectionSummary(),
        ]);

        $this->waveFetchService->appendLog('http_retry', $message);
    }
}
