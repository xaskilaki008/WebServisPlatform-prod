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
            'operator_status' => $beach->operator_status,
            'operator_warning' => $beach->operator_warning,
            'operator_wave_direction' => $beach->operator_wave_direction,
            'operator_wave_azimuth' => $beach->operator_wave_azimuth,
            'operator_wave_period' => $beach->operator_wave_period,
            'operator_access_status' => $beach->operator_access_status,
            'operator_updated_at' => $beach->operator_updated_at,
            'operator_category_key' => $beach->operator_category_key,
            'operator_category_label' => $beach->operator_category_label,
            'operator_status_text' => $beach->operator_status_text,
            'operator_direction_text' => $beach->operator_direction_text,
            'operator_access_label' => $beach->operator_access_label,
            'operator_data_is_fresh' => $beach->operator_data_is_fresh,
            'operator_data_is_stale' => $beach->operator_data_is_stale,
            'effective_wave_level' => $beach->effective_wave_level,
            'category_key' => $beach->category_key,
            'category_label' => $beach->category_label,

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
