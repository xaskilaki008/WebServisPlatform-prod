<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Throwable;
use Symfony\Component\Process\Process;

class WaveFetchService
{
    private const LOCK_KEY = 'wave_fetch_running';
    private const STATUS_KEY = 'wave_fetch_status';
    private const STARTED_AT_KEY = 'wave_fetch_started_at';
    private const FINISHED_AT_KEY = 'wave_fetch_finished_at';
    private const ERROR_KEY = 'wave_fetch_error';
    private const LOCK_MINUTES = 60;

    public function start(): array
    {
        $this->clearStaleLock();

        if ($this->cacheHas(self::LOCK_KEY)) {
            return [
                'started' => false,
                'status' => 'running',
                'message' => 'Загрузка DWD уже выполняется.',
            ];
        }

        $this->cachePut(self::LOCK_KEY, true, now()->addMinutes(self::LOCK_MINUTES));
        $this->cachePut(self::STATUS_KEY, 'running', now()->addMinutes(self::LOCK_MINUTES));
        $this->cachePut(self::STARTED_AT_KEY, now()->toDateTimeString(), now()->addMinutes(self::LOCK_MINUTES));
        $this->cacheForget(self::FINISHED_AT_KEY);
        $this->cacheForget(self::ERROR_KEY);

        $process = $this->backgroundProcess();
        $process->run();

        return [
            'started' => true,
            'status' => 'running',
            'message' => 'Загрузка DWD запущена в фоне.',
        ];
    }

    private function backgroundProcess(): Process
    {
        $php = PHP_BINARY;

        if (PHP_OS_FAMILY === 'Windows') {
            return new Process([
                'cmd',
                '/C',
                'start',
                '/B',
                '',
                $php,
                'artisan',
                'wave:fetch',
            ], base_path());
        }

        $command = sprintf(
            'nohup %s artisan wave:fetch > /dev/null 2>&1 &',
            escapeshellarg($php)
        );

        return Process::fromShellCommandline($command, base_path());
    }

    public function status(): array
    {
        $startedAt = $this->cacheGet(self::STARTED_AT_KEY);
        $running = $this->cacheHas(self::LOCK_KEY);
        $stale = $running && $this->isStaleStartedAt($startedAt);

        return [
            'status' => $this->cacheGet(self::STATUS_KEY, 'idle'),
            'running' => $running,
            'stale' => $stale,
            'can_reset' => $stale,
            'started_at' => $startedAt,
            'finished_at' => $this->cacheGet(self::FINISHED_AT_KEY),
            'error' => $this->cacheGet(self::ERROR_KEY),
        ];
    }

    public function markCompleted(): void
    {
        $this->cachePut(self::STATUS_KEY, 'success', now()->addDay());
        $this->cachePut(self::FINISHED_AT_KEY, now()->toDateTimeString(), now()->addDay());
        $this->cacheForget(self::ERROR_KEY);
        $this->cacheForget(self::LOCK_KEY);
    }

    public function markFailed(string $message): void
    {
        $this->cachePut(self::STATUS_KEY, 'failed', now()->addDay());
        $this->cachePut(self::FINISHED_AT_KEY, now()->toDateTimeString(), now()->addDay());
        $this->cachePut(self::ERROR_KEY, $message, now()->addDay());
        $this->cacheForget(self::LOCK_KEY);
    }

    public function resetStaleLock(): array
    {
        $status = $this->status();

        if (!$status['can_reset']) {
            return [
                'reset' => false,
                'message' => 'Сброс недоступен: активная загрузка не выглядит зависшей.',
            ];
        }

        $this->cacheForget(self::LOCK_KEY);
        $this->cachePut(self::STATUS_KEY, 'idle', now()->addDay());
        $this->cachePut(self::FINISHED_AT_KEY, now()->toDateTimeString(), now()->addDay());
        $this->cachePut(self::ERROR_KEY, 'Зависший запуск DWD был сброшен администратором.', now()->addDay());

        return [
            'reset' => true,
            'message' => 'Зависший запуск DWD сброшен.',
        ];
    }

    private function clearStaleLock(): void
    {
        if (!$this->cacheHas(self::LOCK_KEY)) {
            return;
        }

        if (!$this->isStaleStartedAt($this->cacheGet(self::STARTED_AT_KEY))) {
            return;
        }

        $this->cacheForget(self::LOCK_KEY);
        $this->cachePut(self::STATUS_KEY, 'failed', now()->addDay());
        $this->cachePut(self::FINISHED_AT_KEY, now()->toDateTimeString(), now()->addDay());
        $this->cachePut(self::ERROR_KEY, 'Загрузка DWD зависла и была автоматически разблокирована по TTL.', now()->addDay());
    }

    private function isStaleStartedAt(?string $startedAt): bool
    {
        if (!$startedAt) {
            return true;
        }

        try {
            return Carbon::parse($startedAt)->lte(now()->subMinutes(self::LOCK_MINUTES));
        } catch (Throwable) {
            return true;
        }
    }

    private function cacheGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (Throwable $e) {
            Log::error('DWD cache read failed', ['key' => $key, 'error' => $e->getMessage()]);

            return $default;
        }
    }

    private function cacheHas(string $key): bool
    {
        try {
            return Cache::has($key);
        } catch (Throwable $e) {
            Log::error('DWD cache has failed', ['key' => $key, 'error' => $e->getMessage()]);

            return false;
        }
    }

    private function cachePut(string $key, mixed $value, mixed $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (Throwable $e) {
            Log::error('DWD cache write failed', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }

    private function cacheForget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (Throwable $e) {
            Log::error('DWD cache forget failed', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }
}
