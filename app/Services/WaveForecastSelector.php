<?php

namespace App\Services;

use App\Models\Beach;
use App\Models\WaveForecast;
use Illuminate\Support\Carbon;

class WaveForecastSelector
{
    public function forBeach(int $beachId, ?Carbon $targetUtc = null): ?WaveForecast
    {
        $targetUtc = ($targetUtc ?? now('UTC'))->copy()->setTimezone('UTC');
        $hourStart = $targetUtc->copy()->startOfHour();
        $hourEnd = $hourStart->copy()->addHour();

        $forecast = WaveForecast::query()
            ->where('beach_id', $beachId)
            ->where('forecast_time', '>=', $hourStart)
            ->where('forecast_time', '<', $hourEnd)
            ->orderByDesc('model_run_at')
            ->first();

        if ($forecast) {
            return $forecast;
        }

        $previous = WaveForecast::query()
            ->where('beach_id', $beachId)
            ->where('forecast_time', '<', $targetUtc)
            ->orderByDesc('forecast_time')
            ->orderByDesc('model_run_at')
            ->first();

        $next = WaveForecast::query()
            ->where('beach_id', $beachId)
            ->where('forecast_time', '>=', $targetUtc)
            ->orderBy('forecast_time')
            ->orderByDesc('model_run_at')
            ->first();

        return $this->closestForecast($previous, $next, $targetUtc);
    }

    public function refreshBeachWaveLevels(?Carbon $targetUtc = null): int
    {
        $updated = 0;
        $targetUtc = ($targetUtc ?? now('UTC'))->copy()->setTimezone('UTC');

        Beach::query()
            ->select(['id', 'wave_level'])
            ->orderBy('id')
            ->each(function (Beach $beach) use ($targetUtc, &$updated): void {
                $forecast = $this->forBeach((int) $beach->id, $targetUtc);

                if (!$forecast) {
                    return;
                }

                $level = $forecast->calculateWaveLevel();

                if ((int) $beach->wave_level === $level) {
                    return;
                }

                $beach->update(['wave_level' => $level]);
                $updated++;
            });

        return $updated;
    }

    private function closestForecast(?WaveForecast $previous, ?WaveForecast $next, Carbon $targetUtc): ?WaveForecast
    {
        if (!$previous) {
            return $next;
        }

        if (!$next) {
            return $previous;
        }

        $previousDiff = abs($previous->forecast_time->diffInSeconds($targetUtc, false));
        $nextDiff = abs($next->forecast_time->diffInSeconds($targetUtc, false));

        if ($previousDiff < $nextDiff) {
            return $previous;
        }

        if ($nextDiff < $previousDiff) {
            return $next;
        }

        return $next->model_run_at->greaterThan($previous->model_run_at) ? $next : $previous;
    }
}
