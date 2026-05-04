<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beach extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'name',
        'latitude',
        'longitude',
        'wave_level',
    ];

    protected $casts = [
        'number' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'wave_level' => 'integer',
    ];

    protected $appends = [
        'wave_level_text',
        'category_key',
        'category_label',
    ];

    public function getWaveLevelTextAttribute(): string
    {
        $level = (int) $this->wave_level;

        return match (true) {
            $level === 0 => 'Слабое волнение',
            $level <= 2 => 'Небольшое волнение',
            $level <= 4 => 'Умеренное волнение',
            $level <= 6 => 'Заметное волнение',
            $level <= 9 => 'Сильное волнение',
            default => 'Очень сильное волнение',
        };
    }

    public function getCategoryKeyAttribute(): string
    {
        $level = (int) $this->wave_level;

        return match (true) {
            $level <= 2 => 'safe',
            $level <= 5 => 'caution',
            default => 'danger',
        };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category_key) {
            'safe' => 'Купание допустимо',
            'caution' => 'Нужна осторожность',
            default => 'Купание не рекомендуется',
        };
    }
}