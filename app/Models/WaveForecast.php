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
        'wave_height',
        'wave_period',
        'wave_direction',
    ];

    protected $casts = [
        'beach_id' => 'integer',
        'forecast_time' => 'datetime',
        'wave_height' => 'float',
        'wave_period' => 'float',
        'wave_direction' => 'float',
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

        // Автоматическое определение балла (0-9+) на основе высоты
        return match (true) {
            $height < 0.1 => 0, // Слабое волнение
            $height < 0.5 => 2, // Небольшое
            $height < 1.25 => 4, // Умеренное
            $height < 2.5 => 6, // Заметное
            $height < 4.0 => 9, // Сильное
            default => 10, // Очень сильное
        };
    }
}
