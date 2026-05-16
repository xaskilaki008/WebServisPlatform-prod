<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Beach;
use App\Models\WaveForecast;
use Illuminate\Support\Facades\File;

class BeachController extends Controller
{
    public function getInfo($id)
    {
        $beach = Beach::findOrFail($id);

        $forecast = WaveForecast::where('beach_id', $id)
            ->orderBy('forecast_time', 'desc')
            ->first();

        return response()->json([
            'id' => $beach->id,
            'name' => $beach->name,
            'wave_level' => $beach->wave_level,

            // Твой JS сам разбирается, если данные лежат прямо в корне:
            // const forecast = data.latest_forecast || data;
            'wave_height' => $forecast ? $forecast->wave_height : null,
            'wave_period' => $forecast ? $forecast->wave_period : null, // Добавили период!
            'wave_direction' => $forecast ? $forecast->wave_direction : null,
            'forecast_time' => $forecast ? $forecast->forecast_time : null,
        ]);
    }

    public function getPhoto($id)
    {
        $directory = public_path('фотографии пляжей');

        if (!File::exists($directory)) {
            // ИСПРАВЛЕНО: JS ждет ключ 'photo_urls', а не 'urls'
            return response()->json(['photo_urls' => []]);
        }

        $files = File::files($directory);

        $beachPhotos = array_filter($files, function ($file) use ($id) {
            return str_starts_with($file->getFilename(), $id . '-');
        });

        $urls = array_map(function ($file) {
            return asset('фотографии пляжей/' . $file->getFilename());
        }, $beachPhotos);

        // ИСПРАВЛЕНО: Отдаем под ключом 'photo_urls'
        return response()->json(['photo_urls' => array_values($urls)]);
    }
}