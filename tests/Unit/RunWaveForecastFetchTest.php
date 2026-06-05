<?php

namespace Tests\Unit;

use App\Jobs\RunWaveForecastFetch;
use App\Services\WaveFetchService;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

class RunWaveForecastFetchTest extends TestCase
{
    public function test_job_does_not_overwrite_final_status_after_command_returns_normally(): void
    {
        $service = Mockery::mock(WaveFetchService::class);
        $service->shouldReceive('status')->once()->andReturn(['status' => 'queued']);
        $service->shouldReceive('markRunning')->once();
        $service->shouldReceive('releaseLock')->once();
        $service->shouldNotReceive('markCompleted');
        $service->shouldNotReceive('markFailed');

        Artisan::shouldReceive('call')->once()->with('wave:fetch')->andReturn(0);

        (new RunWaveForecastFetch())->handle($service);
    }
}
