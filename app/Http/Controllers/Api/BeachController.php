<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Beach;
use App\Models\WaveForecast; // Обязательно подключаем модель прогнозов!
use Illuminate\Support\Facades\File; // Меняем Storage на File

class BeachController extends Controller
{
    public function getInfo($id)
    {
        $beach = Beach::findOrFail($id);

        // ВОССТАНАВЛИВАЕМ СВЯЗЬ: Ищем самый свежий прогноз для этого пляжа
        $forecast = WaveForecast::where('beach_id', $id)
            ->orderBy('forecast_time', 'desc')
            ->first();

        return response()->json([
            'id' => $beach->id,
            'name' => $beach->name,
            'wave_level_text' => $beach->wave_level_text,
            'category_label' => $beach->category_label,
            'wave_level' => $beach->wave_level,

            // Отдаем на фронтенд данные из таблицы wave_forecasts
            'wave_height' => $forecast ? $forecast->wave_height : null,
            'wave_direction' => $forecast ? $forecast->wave_direction : null,
            'forecast_time' => $forecast ? $forecast->forecast_time : null,
        ]);
    }

    public function getPhoto($id)
    {
        // Ищем папку напрямую в корневой директории public
        $directory = public_path('фотографии пляжей');

        if (!File::exists($directory)) {
            return response()->json(['urls' => []]);
        }

        $files = File::files($directory);

        $beachPhotos = array_filter($files, function ($file) use ($id) {
            return str_starts_with($file->getFilename(), $id . '-');
        });

        // Создаем правильные URL-ссылки для фронтенда с помощью asset()
        $urls = array_map(function ($file) {
            return asset('фотографии пляжей/' . $file->getFilename());
        }, $beachPhotos);

        return response()->json(['urls' => array_values($urls)]);
    }
}