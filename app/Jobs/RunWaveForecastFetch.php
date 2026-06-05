<?php

namespace App\Jobs;

use App\Services\WaveFetchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class RunWaveForecastFetch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function handle(WaveFetchService $waveFetchService): void
    {
        if (($waveFetchService->status()['status'] ?? null) !== 'queued') {
            return;
        }

        $started = false;
        try {
            $waveFetchService->markRunning();
            $started = true;

            Artisan::call('wave:fetch');
        } catch (Throwable $e) {
            $waveFetchService->markFailed($e->getMessage(), 'queue_exception');

            throw $e;
        } finally {
            if ($started) {
                $waveFetchService->releaseLock();
            }
        }
    }
}
