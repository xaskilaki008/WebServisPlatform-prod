<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaveForecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'beach_id',
        'forecast_time',
        'forecast_hour',
        'wave_height',
        'wave_period',
        'wave_direction',
        'air_temp',
        'water_temp',
        'model_run_at',
        'model_run_hour',
        'parsed_at',
        'source_files',
    ];

    protected $casts = [
        'beach_id' => 'integer',
        'forecast_time' => 'datetime',
        'forecast_hour' => 'integer',
        'wave_height' => 'float',
        'wave_period' => 'float',
        'wave_direction' => 'float',
        'air_temp' => 'float',
        'water_temp' => 'float',
        'model_run_at' => 'datetime',
        'model_run_hour' => 'integer',
        'parsed_at' => 'datetime',
        'source_files' => 'array',
    ];

    public function beach(): BelongsTo
    {
        return $this->belongsTo(Beach::class);
    }
    public function getWaveLevelAttribute()
    {
        $height = $this->wave_height;

        if ($height < 0.5)
            return 1; // Низкий (Зеленый)
        if ($height < 1.2)
            return 2; // Умеренный (Желтый)
        return 3; // Высокий (Красный)
    }
    public function calculateWaveLevel(): int
    {
        $height = $this->wave_height;

        // Автоматическое определение балла (0-10) на основе высоты волны
        // и пороговых значений из "Правил охраны жизни людей на водных объектах" [32, 33]
        return match (true) {
            $height < 0.1 => 0, // Слабое волнение
            $height < 0.5 => 2, // Небольшое (запрет сап-бордов для новичков)
            $height < 1.2 => 4, // Умеренное (зелёный флаг)
            $height < 1.5 => 6, // Заметное (жёлтый флаг — опасно для детей и не умеющих плавать)
            $height < 2.5 => 9, // Сильное (красный/чёрный флаг — опасно для всех)
            default => 10, // Очень сильное
        };
    }
}
