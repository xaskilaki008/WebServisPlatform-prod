<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Beach extends Model
{
    use HasFactory;

    // 1. Разрешаем массовое заполнение всех нужных полей, включая координаты для DWD
    protected $fillable = [
        'name',
        'longitude',
        'latitude',
        'number',
        'wave_level',
        'fetch_longitude',
        'fetch_latitude',
    ];

    protected $casts = [
        'number' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'wave_level' => 'integer',
    ];

    // 2. Указываем, какие виртуальные поля должны добавляться в JSON для фронтенда
    protected $appends = [
        'wave_level_text',
        'category_key',
        'category_label',
        'photo_url',
    ];

    // --- ЛОГИКА ОПИСАНИЯ ВОЛНЕНИЯ ---
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

    // --- ЛОГИКА КАТЕГОРИЙ БЕЗОПАСНОСТИ ---
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

    // --- ЛОГИКА ПОИСКА ФОТОГРАФИЙ ---
    public function getPhotoUrlAttribute()
    {
        $directory = public_path('фотографии пляжей');
        // Используем абсолютное значение номера, так как в именах файлов могут быть минусы
        $displayNumber = abs($this->number);

        $files = glob($directory . '/' . $displayNumber . '-*.*');
        if (empty($files)) {
            $files = glob($directory . '/' . $displayNumber . '.*');
        }

        if (!empty($files)) {
            $fileName = basename($files[0]);
            return asset('фотографии пляжей/' . $fileName);
        }

        return asset('images/no-photo.png');
    }

    /**
     * Связь: получить самый свежий прогноз для этого пляжа
     */
    public function latestForecast(): HasOne
    {
        // Используем forecast_time, чтобы брать данные на самый актуальный час
        return $this->hasOne(WaveForecast::class)->latestOfMany('forecast_time');
    }
}