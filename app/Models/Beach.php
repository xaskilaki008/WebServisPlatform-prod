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
    // Добавляем этот метод в модель Beach.php
    public function getPhotoUrlAttribute()
    {
        // Указываем путь к папке (public/фотографии пляжей/)
        $directory = public_path('фотографии пляжей');
        
        // Ищем файл, который начинается с ID пляжа (например: "32-*.*" или "32.*")
        $files = glob($directory . '/' . $this->id . '-*.*');
        
        // Если с дефисом не нашли, ищем просто по номеру и любому символу дальше
        if (empty($files)) {
            $files = glob($directory . '/' . $this->id . '*.*');
        }

        // Если файл найден, возвращаем ссылку на него
        if (!empty($files)) {
            $fileName = basename($files[0]);
            return asset('фотографии пляжей/' . $fileName);
        }

        // Если картинки у пляжа нет, можно вернуть заглушку
        return asset('images/no-photo.png'); 
    }
}