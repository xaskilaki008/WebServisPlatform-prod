<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class WaveFetchService
{
    private const LOCK_KEY = 'wave_fetch_running';
    private const STATUS_KEY = 'wave_fetch_status';
    private const STARTED_AT_KEY = 'wave_fetch_started_at';
    private const FINISHED_AT_KEY = 'wave_fetch_finished_at';
    private const ERROR_KEY = 'wave_fetch_error';
    private const LOCK_MINUTES = 60;
    private const LAST_LOG_LINES = 30;
    private const HEARTBEAT_STALE_MINUTES = 10;

    public function start(): array
    {
        $this->clearStaleLock();
        $status = $this->status();

        if ($status['running'] && !$status['stale']) {
            return [
                'started' => false,
                'status' => 'running',
                'message' => 'Загрузка DWD уже выполняется.',
            ];
        }

        $startedAt = now()->toDateTimeString();
        $this->writeStatus([
            'status' => 'running',
            'running' => true,
            'stage' => 'starting',
            'started_at' => $startedAt,
            'heartbeat_at' => $startedAt,
            'last_log_at' => $startedAt,
            'finished_at' => null,
            'error' => null,
        ]);
        $this->appendLog('starting', 'Запуск фоновой загрузки DWD.');

        $this->cachePut(self::LOCK_KEY, true, now()->addMinutes(self::LOCK_MINUTES));
        $this->cachePut(self::STATUS_KEY, 'running', now()->addMinutes(self::LOCK_MINUTES));
        $this->cachePut(self::STARTED_AT_KEY, $startedAt, now()->addMinutes(self::LOCK_MINUTES));
        $this->cacheForget(self::FINISHED_AT_KEY);
        $this->cacheForget(self::ERROR_KEY);

        $process = $this->backgroundProcess();
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput()) ?: 'Не удалось запустить фоновую загрузку DWD.';
            $this->markFailed($message, 'start_failed');

            return [
                'started' => false,
                'status' => 'failed',
                'message' => $message,
            ];
        }

        return [
            'started' => true,
            'status' => 'running',
            'message' => 'Загрузка DWD запущена в фоне. Откройте блок диагностики для просмотра этапов.',
        ];
    }

    public function status(): array
    {
        $fileStatus = $this->readFileStatus();
        $cacheRunning = $this->cacheHas(self::LOCK_KEY);
        $startedAt = $fileStatus['started_at'] ?? $this->cacheGet(self::STARTED_AT_KEY);
        $running = array_key_exists('running', $fileStatus)
            ? (bool) $fileStatus['running']
            : $cacheRunning;
        $heartbeatAt = $fileStatus['heartbeat_at'] ?? null;
        $lastLogAt = array_key_exists('last_log_at', $fileStatus)
            ? $fileStatus['last_log_at']
            : $this->lastLogAt();
        $heartbeatStale = $running && $this->isStaleTimestamp($heartbeatAt, self::HEARTBEAT_STALE_MINUTES);
        $logStale = $running && $this->isStaleTimestamp($lastLogAt, self::HEARTBEAT_STALE_MINUTES);
        $ttlStale = $running && $this->isStaleStartedAt($startedAt);
        $cacheFileConflict = $cacheRunning && array_key_exists('running', $fileStatus) && !$running;
        $stale = $ttlStale || $heartbeatStale || $logStale || $cacheFileConflict;
        $minutesSinceLastLog = $lastLogAt ? $this->minutesSince($lastLogAt) : null;

        return [
            'status' => $fileStatus['status'] ?? $this->cacheGet(self::STATUS_KEY, 'idle'),
            'running' => $running,
            'stage' => $fileStatus['stage'] ?? null,
            'stale' => $stale,
            'can_reset' => $stale || $cacheFileConflict,
            'heartbeat_at' => $heartbeatAt,
            'heartbeat_stale' => $heartbeatStale,
            'last_log_at' => $lastLogAt,
            'log_stale' => $logStale,
            'minutes_since_last_log' => $minutesSinceLastLog,
            'cache_running' => $cacheRunning,
            'cache_file_conflict' => $cacheFileConflict,
            'started_at' => $startedAt,
            'finished_at' => $fileStatus['finished_at'] ?? $this->cacheGet(self::FINISHED_AT_KEY),
            'error' => $fileStatus['error'] ?? $this->cacheGet(self::ERROR_KEY),
            'last_log_lines' => $this->lastLogLines(),
            'diagnostic' => $fileStatus['diagnostic'] ?? null,
            'updated_at' => $fileStatus['updated_at'] ?? null,
        ];
    }

    public function markStage(string $stage, ?string $message = null): void
    {
        $status = $this->readFileStatus();
        $this->writeStatus(array_merge($status, [
            'status' => 'running',
            'running' => true,
            'stage' => $stage,
            'started_at' => $status['started_at'] ?? now()->toDateTimeString(),
            'heartbeat_at' => now()->toDateTimeString(),
            'finished_at' => null,
            'error' => null,
        ]));
        $this->appendLog($stage, $message ?: $stage);
    }

    public function markCompleted(): void
    {
        $this->writeStatus(array_merge($this->readFileStatus(), [
            'status' => 'success',
            'running' => false,
            'stage' => 'completed',
            'heartbeat_at' => now()->toDateTimeString(),
            'finished_at' => now()->toDateTimeString(),
            'error' => null,
        ]));
        $this->appendLog('completed', 'Загрузка DWD успешно завершена.');

        $this->cachePut(self::STATUS_KEY, 'success', now()->addDay());
        $this->cachePut(self::FINISHED_AT_KEY, now()->toDateTimeString(), now()->addDay());
        $this->cacheForget(self::ERROR_KEY);
        $this->cacheForget(self::LOCK_KEY);
    }

    public function markFailed(string $message, string $stage = 'failed'): void
    {
        $this->writeStatus(array_merge($this->readFileStatus(), [
            'status' => 'failed',
            'running' => false,
            'stage' => $stage,
            'heartbeat_at' => now()->toDateTimeString(),
            'finished_at' => now()->toDateTimeString(),
            'error' => $message,
        ]));
        $this->appendLog($stage, $message);

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

        $message = 'Зависший запуск DWD был сброшен администратором.';
        $this->writeStatus(array_merge($this->readFileStatus(), [
            'status' => 'idle',
            'running' => false,
            'stage' => 'reset',
            'heartbeat_at' => now()->toDateTimeString(),
            'finished_at' => now()->toDateTimeString(),
            'error' => $message,
        ]));
        $this->appendLog('reset', $message);

        $this->cacheForget(self::LOCK_KEY);
        $this->cachePut(self::STATUS_KEY, 'idle', now()->addDay());
        $this->cachePut(self::FINISHED_AT_KEY, now()->toDateTimeString(), now()->addDay());
        $this->cachePut(self::ERROR_KEY, $message, now()->addDay());

        return [
            'reset' => true,
            'message' => 'Зависший запуск DWD сброшен.',
        ];
    }

    public function saveDiagnostic(array $results): void
    {
        $this->writeStatus(array_merge($this->readFileStatus(), [
            'diagnostic' => [
                'checked_at' => now()->toDateTimeString(),
                'results' => $results,
            ],
        ]));
    }

    public function logLines(): array
    {
        return $this->lastLogLines();
    }

    public function clearLog(): array
    {
        try {
            File::ensureDirectoryExists(dirname($this->logPath()));
            file_put_contents($this->logPath(), '');
            $this->writeStatus(array_merge($this->readFileStatus(), [
                'last_log_at' => null,
            ]));

            return [
                'cleared' => true,
                'message' => 'DWD-лог очищен.',
            ];
        } catch (Throwable $e) {
            Log::error('DWD log clear failed', ['error' => $e->getMessage()]);

            return [
                'cleared' => false,
                'message' => 'Не удалось очистить DWD-лог: ' . $e->getMessage(),
            ];
        }
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

    private function clearStaleLock(): void
    {
        $status = $this->readFileStatus();
        $running = (bool) ($status['running'] ?? $this->cacheHas(self::LOCK_KEY));

        if (!$running
            || (!$this->isStaleStartedAt($status['started_at'] ?? $this->cacheGet(self::STARTED_AT_KEY))
                && !$this->isStaleTimestamp($status['heartbeat_at'] ?? null, self::HEARTBEAT_STALE_MINUTES)
                && !$this->isStaleTimestamp($status['last_log_at'] ?? $this->lastLogAt(), self::HEARTBEAT_STALE_MINUTES))) {
            return;
        }

        $this->markFailed('Загрузка DWD зависла и была автоматически разблокирована по TTL.', 'stale_unlocked');
    }

    private function writeStatus(array $status): void
    {
        $status['updated_at'] = now()->toDateTimeString();

        try {
            File::ensureDirectoryExists(dirname($this->statusPath()));
            file_put_contents(
                $this->statusPath(),
                json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        } catch (Throwable $e) {
            Log::error('DWD file status write failed', ['error' => $e->getMessage()]);
        }
    }

    private function readFileStatus(): array
    {
        try {
            if (!is_file($this->statusPath())) {
                return [];
            }

            $status = json_decode((string) file_get_contents($this->statusPath()), true);

            return is_array($status) ? $status : [];
        } catch (Throwable $e) {
            Log::error('DWD file status read failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function appendLog(string $stage, string $message): void
    {
        $loggedAt = now()->toDateTimeString();

        try {
            File::ensureDirectoryExists(dirname($this->logPath()));
            file_put_contents(
                $this->logPath(),
                sprintf("[%s] %-18s %s%s", $loggedAt, $stage, $message, PHP_EOL),
                FILE_APPEND
            );
            $this->writeStatus(array_merge($this->readFileStatus(), [
                'last_log_at' => $loggedAt,
            ]));
        } catch (Throwable $e) {
            Log::error('DWD log write failed', ['error' => $e->getMessage()]);
        }
    }

    private function lastLogLines(): array
    {
        try {
            if (!is_file($this->logPath())) {
                return [];
            }

            $lines = file($this->logPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            return array_slice($lines ?: [], -self::LAST_LOG_LINES);
        } catch (Throwable $e) {
            Log::error('DWD log read failed', ['error' => $e->getMessage()]);

            return [];
        }
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

    private function isStaleTimestamp(?string $timestamp, int $minutes): bool
    {
        if (!$timestamp) {
            return true;
        }

        try {
            return Carbon::parse($timestamp)->lte(now()->subMinutes($minutes));
        } catch (Throwable) {
            return true;
        }
    }

    private function minutesSince(?string $timestamp): ?int
    {
        if (!$timestamp) {
            return null;
        }

        try {
            return max(0, Carbon::parse($timestamp)->diffInMinutes(now()));
        } catch (Throwable) {
            return null;
        }
    }

    private function lastLogAt(): ?string
    {
        try {
            if (!is_file($this->logPath())) {
                return null;
            }

            return Carbon::createFromTimestamp(filemtime($this->logPath()))->toDateTimeString();
        } catch (Throwable $e) {
            Log::error('DWD log mtime read failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function statusPath(): string
    {
        return storage_path('app/dwd_fetch_status.json');
    }

    private function logPath(): string
    {
        return storage_path('logs/dwd-wave-fetch.log');
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
