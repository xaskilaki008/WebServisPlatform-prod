<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Beach;
use App\Models\WaveForecast;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class BeachController extends Controller
{
    public function getInfo($id)
    {
        $beach = Beach::findOrFail($id);

        $currentModelRunAt = $this->currentDwdModelRunAt();

        $forecast = WaveForecast::query()
            ->where('beach_id', $id)
            ->where('model_run_at', '<=', $currentModelRunAt)
            ->orderBy('model_run_at', 'desc')
            ->orderBy('forecast_time', 'desc')
            ->first();

        if (!$forecast) {
            $forecast = WaveForecast::query()
                ->where('beach_id', $id)
                ->orderBy('model_run_at', 'desc')
                ->orderBy('forecast_time', 'desc')
                ->first();
        }

        $latestForecast = $forecast ? [
            'wave_height' => $forecast->wave_height,
            'wave_period' => $forecast->wave_period,
            'wave_direction' => $forecast->wave_direction,
            'air_temp' => $forecast->air_temp,
            'water_temp' => $forecast->water_temp,
            'forecast_time' => $forecast->forecast_time,
            'model_run_at' => $forecast->model_run_at,
            'model_run_hour' => $forecast->model_run_hour,
        ] : null;

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
            'air_temp' => $forecast ? $forecast->air_temp : null,
            'water_temp' => $forecast ? $forecast->water_temp : null,
            'forecast_time' => $forecast ? $forecast->forecast_time : null,
            'model_run_at' => $forecast ? $forecast->model_run_at : null,
            'model_run_hour' => $forecast ? $forecast->model_run_hour : null,
            'latest_forecast' => $latestForecast,
        ]);
    }

    private function currentDwdModelRunAt(): Carbon
    {
        $now = Carbon::now(config('app.timezone', 'UTC'));
        $modelRunHour = $now->hour < 12 ? 0 : 12;

        return $now->copy()->startOfDay()->addHours($modelRunHour);
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
