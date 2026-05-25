<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
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
        if (Cache::has(self::LOCK_KEY)) {
            return [
                'started' => false,
                'status' => 'running',
                'message' => 'Загрузка DWD уже выполняется.',
            ];
        }

        Cache::put(self::LOCK_KEY, true, now()->addMinutes(self::LOCK_MINUTES));
        Cache::put(self::STATUS_KEY, 'running', now()->addMinutes(self::LOCK_MINUTES));
        Cache::put(self::STARTED_AT_KEY, now()->toDateTimeString(), now()->addMinutes(self::LOCK_MINUTES));
        Cache::forget(self::FINISHED_AT_KEY);
        Cache::forget(self::ERROR_KEY);

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
        return [
            'status' => Cache::get(self::STATUS_KEY, 'idle'),
            'running' => Cache::has(self::LOCK_KEY),
            'started_at' => Cache::get(self::STARTED_AT_KEY),
            'finished_at' => Cache::get(self::FINISHED_AT_KEY),
            'error' => Cache::get(self::ERROR_KEY),
        ];
    }

    public function markCompleted(): void
    {
        Cache::put(self::STATUS_KEY, 'success', now()->addDay());
        Cache::put(self::FINISHED_AT_KEY, now()->toDateTimeString(), now()->addDay());
        Cache::forget(self::ERROR_KEY);
        Cache::forget(self::LOCK_KEY);
    }

    public function markFailed(string $message): void
    {
        Cache::put(self::STATUS_KEY, 'failed', now()->addDay());
        Cache::put(self::FINISHED_AT_KEY, now()->toDateTimeString(), now()->addDay());
        Cache::put(self::ERROR_KEY, $message, now()->addDay());
        Cache::forget(self::LOCK_KEY);
    }
}
