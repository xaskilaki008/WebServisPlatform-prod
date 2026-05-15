<?php

namespace App\Observers;

use App\Models\WaveForecast;
use App\Models\Beach;

class WaveForecastObserver
{
    public function created(WaveForecast $waveForecast): void
    {
        $beach = $waveForecast->beach;

        if ($beach) {
            // Вычисляем балл уровня через метод модели
            $calculatedLevel = $waveForecast->calculateWaveLevel();

            // Обновляем уровень в таблице пляжей
            $beach->update([
                'wave_level' => $calculatedLevel,
            ]);
        }
    }
}