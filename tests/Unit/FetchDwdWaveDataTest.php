<?php

namespace Tests\Unit;

use App\Console\Commands\FetchDwdWaveData;
use PHPUnit\Framework\TestCase;

class FetchDwdWaveDataTest extends TestCase
{
    public function test_model_run_parsing_uses_berlin_summer_time(): void
    {
        $command = new FetchDwdWaveData();

        $modelRunAt = $command->parseModelRunFromFilename('EWAM_SWH_2026052512_003.grib2.bz2');

        $this->assertSame('2026-05-25 10:00:00', $modelRunAt->format('Y-m-d H:i:s'));
        $this->assertSame(3, $command->parseForecastHourFromFilename('EWAM_SWH_2026052512_003.grib2.bz2'));
    }

    public function test_model_run_parsing_uses_berlin_winter_time(): void
    {
        $command = new FetchDwdWaveData();

        $modelRunAt = $command->parseModelRunFromFilename('EWAM_SWH_2026122512_003.grib2.bz2');

        $this->assertSame('2026-12-25 11:00:00', $modelRunAt->format('Y-m-d H:i:s'));
        $this->assertSame(3, $command->parseForecastHourFromFilename('EWAM_SWH_2026122512_003.grib2.bz2'));
    }
}
