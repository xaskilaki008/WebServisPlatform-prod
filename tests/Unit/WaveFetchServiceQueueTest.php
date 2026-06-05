<?php

namespace Tests\Unit;

use App\Jobs\RunWaveForecastFetch;
use App\Services\WaveFetchService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WaveFetchServiceQueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        @unlink(storage_path('app/dwd_fetch_status.json'));
        @unlink(storage_path('logs/dwd-wave-fetch.log'));
        Cache::flush();
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/dwd_fetch_status.json'));
        @unlink(storage_path('logs/dwd-wave-fetch.log'));

        parent::tearDown();
    }

    public function test_start_queues_forecast_job_without_running_parser_in_request(): void
    {
        Queue::fake();

        $result = app(WaveFetchService::class)->start();

        $this->assertSame('queued', $result['status']);
        $this->assertTrue(app(WaveFetchService::class)->status()['queued']);
        Queue::assertPushedOn('forecast', RunWaveForecastFetch::class);
    }

    public function test_start_does_not_queue_duplicate_job_while_queued_is_active(): void
    {
        Queue::fake();
        $service = app(WaveFetchService::class);

        $service->start();
        $result = $service->start();

        $this->assertSame('queued', $result['status']);
        Queue::assertPushed(RunWaveForecastFetch::class, 1);
    }
}
