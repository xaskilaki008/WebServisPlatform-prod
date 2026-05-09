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
}
