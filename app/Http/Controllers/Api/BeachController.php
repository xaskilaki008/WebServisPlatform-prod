<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Beach;
use App\Models\BeachOperatorLog;
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
            'parsed_at' => $forecast->parsed_at,
        ] : null;

        $latestOperatorLog = $this->latestCompleteOperatorLog((int) $id);
        $operatorLogPayload = $latestOperatorLog
            ? $this->operatorLogPayload($latestOperatorLog)
            : null;

        $payload = [
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
            'latest_operator_log' => $operatorLogPayload,
        ];

        if ($this->dwdDebugEnabled() && $forecast) {
            $payload['dwd_debug'] = [
                'source_folder' => str_pad((string) $forecast->model_run_hour, 2, '0', STR_PAD_LEFT),
                'parsed_at' => $forecast->parsed_at,
                'beach_id' => $beach->id,
                'wave_height' => $forecast->wave_height,
                'wave_period' => $forecast->wave_period,
                'wave_direction' => $forecast->wave_direction,
                // Temperatures are currently not extracted from the DWD GRIB parameters used by the parser.
                'air_temp' => $forecast->air_temp,
                'water_temp' => $forecast->water_temp,
                'forecast_time' => $forecast->forecast_time,
                'model_run_at' => $forecast->model_run_at,
            ];
        }

        return response()->json($payload);
    }

    private function currentDwdModelRunAt(): Carbon
    {
        $now = Carbon::now();
        $modelRunHour = $now->hour < 12 ? 0 : 12;

        return $now->copy()->startOfDay()->addHours($modelRunHour);
    }

    private function latestCompleteOperatorLog(int $beachId): ?BeachOperatorLog
    {
        return BeachOperatorLog::query()
            ->where('beach_id', $beachId)
            ->whereNotNull('operator_status')
            ->whereNotNull('operator_wave_direction')
            ->whereNotNull('operator_wave_period')
            ->whereNotNull('operator_access_status')
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function operatorLogPayload(BeachOperatorLog $log): array
    {
        return [
            'id' => $log->id,
            'beach_operator_id' => $log->beach_operator_id,
            'beach_id' => $log->beach_id,
            'submitted_at' => $log->submitted_at,
            'created_at' => $log->created_at,
            'updated_at' => $log->updated_at,
            'operator_status' => $log->operator_status,
            'operator_status_text' => $this->operatorStatusText($log->operator_status),
            'operator_warning' => $log->operator_warning,
            'operator_wave_direction' => $log->operator_wave_direction,
            'operator_direction_text' => $this->operatorDirectionText($log),
            'operator_wave_azimuth' => $log->operator_wave_azimuth,
            'operator_wave_period' => $log->operator_wave_period,
            'operator_access_status' => $log->operator_access_status,
            'operator_access_label' => $this->operatorAccessLabel($log->operator_access_status),
            'is_stale' => $log->submitted_at !== null && $log->submitted_at->lt(now()->subHour()),
        ];
    }

    private function operatorStatusText(?string $status): string
    {
        return match ((string) $status) {
            '0' => 'Штиль',
            '1' => 'Легкая рябь',
            '2' => 'Небольшое волнение',
            '3' => 'Умеренное волнение',
            '4' => 'Крупные волны',
            '5' => 'Сильные волны',
            'hazard' => 'Особая опасность',
            default => 'Нет данных',
        };
    }

    private function operatorDirectionText(BeachOperatorLog $log): string
    {
        return match ($log->operator_wave_direction) {
            'direct' => 'Прямо на пляж',
            'left' => 'Слева на пляж',
            'right' => 'Справа на пляж',
            'azimuth' => $log->operator_wave_azimuth !== null
                ? "Азимут {$log->operator_wave_azimuth}°"
                : 'Азимут не указан',
            'chaotic' => 'Неопределенное направление',
            default => 'Нет данных',
        };
    }

    private function operatorAccessLabel(?string $accessStatus): string
    {
        return match ($accessStatus) {
            'open' => 'Пляж открыт',
            'limited' => 'Пляж ограниченно открыт',
            'closed' => 'Пляж закрыт',
            default => 'Нет данных',
        };
    }

    private function dwdDebugEnabled(): bool
    {
        return (bool) config('app.debug') || app()->environment(['local', 'development']);
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
