<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'operator_status',
        'operator_warning',
        'operator_wave_direction',
        'operator_wave_azimuth',
        'operator_wave_period',
        'operator_access_status',
        'operator_updated_at',
        'fetch_longitude',
        'fetch_latitude',
    ];

    protected $casts = [
        'number' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'wave_level' => 'integer',
        'operator_status' => 'string',
        'operator_warning' => 'string',
        'operator_wave_direction' => 'string',
        'operator_wave_azimuth' => 'integer',
        'operator_wave_period' => 'integer',
        'operator_access_status' => 'string',
        'operator_updated_at' => 'datetime',
    ];

    // 2. Указываем, какие виртуальные поля должны добавляться в JSON для фронтенда
    protected $appends = [
        'wave_level_text',
        'category_key',
        'category_label',
        'effective_wave_level',
        'operator_category_key',
        'operator_category_label',
        'operator_status_text',
        'operator_direction_text',
        'operator_access_label',
        'operator_data_is_fresh',
        'operator_data_is_stale',
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
        return $this->categoryKeyFromStatus($this->effective_wave_level);
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category_key) {
            'safe' => 'Купание допустимо',
            'caution' => 'Нужна осторожность',
            default => 'Купание запрещено',
        };
    }

    public function getEffectiveWaveLevelAttribute(): int|string|null
    {
        if ($this->operator_data_is_fresh && $this->operator_status !== null) {
            return $this->operator_status;
        }

        return $this->wave_level;
    }

    public function getOperatorCategoryKeyAttribute(): ?string
    {
        if ($this->operator_status === null) {
            return null;
        }

        return $this->categoryKeyFromStatus($this->operator_status);
    }

    public function getOperatorCategoryLabelAttribute(): ?string
    {
        return match ($this->operator_category_key) {
            'safe' => 'Купание допустимо',
            'caution' => 'Нужна осторожность',
            'danger' => 'Купание запрещено',
            default => null,
        };
    }

    public function getOperatorStatusTextAttribute(): ?string
    {
        return match ((string) $this->operator_status) {
            '0' => 'Зеркальный штиль',
            '1' => 'Легкая рябь',
            '2' => 'Небольшое волнение',
            '3' => 'Умеренное волнение',
            '4' => 'Крупные волны',
            '5' => 'Сильные волны',
            'hazard' => 'Особая опасность',
            default => null,
        };
    }

    public function getOperatorDirectionTextAttribute(): ?string
    {
        return match ($this->operator_wave_direction) {
            'direct' => 'Прямо на пляж',
            'left' => 'Слева на пляж',
            'right' => 'Справа на пляж',
            'azimuth' => $this->operator_wave_azimuth !== null
                ? "Азимут {$this->operator_wave_azimuth}°"
                : 'Под конкретным направлением',
            'chaotic' => 'Не определить (толчея)',
            default => null,
        };
    }

    public function getOperatorAccessLabelAttribute(): ?string
    {
        return match ($this->operator_access_status) {
            'open' => 'Пляж открыт для всех',
            'limited' => 'Пляж ограниченно открыт',
            'closed' => 'Пляж полностью закрыт для купания',
            default => null,
        };
    }

    public function getOperatorDataIsFreshAttribute(): bool
    {
        return $this->operator_updated_at !== null
            && $this->operator_updated_at->greaterThanOrEqualTo(now()->subHour());
    }

    public function getOperatorDataIsStaleAttribute(): bool
    {
        return $this->operator_updated_at !== null && ! $this->operator_data_is_fresh;
    }

    private function categoryKeyFromStatus(int|string|null $status): string
    {
        if ($status === 'hazard') {
            return 'danger';
        }

        $level = (int) $status;

        return match (true) {
            $level <= 1 => 'safe',
            $level <= 3 => 'caution',
            default => 'danger',
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
    // Внутри класса Beach в файле app/Models/Beach.php
    // app/Models/Beach.php
    public function latestForecast()
    {
        return $this->hasOne(WaveForecast::class)->latestOfMany('forecast_time');
    }
}
