<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Beach;
use App\Models\BeachOperatorLog;
use App\Services\BeachInteractionService;
use App\Services\VisitorResolver;
use App\Services\WaveForecastSelector;
use Illuminate\Support\Facades\File;

class BeachController extends Controller
{
    public function getInfo(
        WaveForecastSelector $forecastSelector,
        VisitorResolver $visitorResolver,
        BeachInteractionService $interactionService,
        $id
    )
    {
        $beach = Beach::findOrFail($id);
        $visitor = $visitorResolver->current(request());
        $forecast = config('dwd.forecast_enabled')
            ? $forecastSelector->forBeach((int) $id, now('UTC'))
            : null;

        $latestForecast = $forecast ? [
            'wave_height' => $forecast->wave_height,
            'wave_period' => $forecast->wave_period,
            'wave_direction' => $forecast->wave_direction,
            'air_temp' => $forecast->air_temp,
            'water_temp' => $forecast->water_temp,
            'forecast_time' => $forecast->forecast_time,
            'model_run_at' => $forecast->model_run_at,
            'model_run_hour' => $forecast->model_run_hour,
            'forecast_hour' => $forecast->forecast_hour,
            'parsed_at' => $forecast->parsed_at,
        ] : null;

        $latestOperatorLog = $this->latestCompleteOperatorLog((int) $id);
        $latestAnyOperatorLog = $this->latestCompleteOperatorLog((int) $id, false);
        $operatorLogPayload = $latestOperatorLog
            ? $this->operatorLogPayload($latestOperatorLog)
            : null;
        $operatorDataIsFresh = $latestOperatorLog !== null;
        $operatorDataIsStale = $latestAnyOperatorLog !== null && $latestOperatorLog === null;
        $operatorExpiresAt = $latestOperatorLog?->expires_at ?? $latestAnyOperatorLog?->expires_at;
        $operatorStatus = $latestOperatorLog?->operator_status;
        $operatorCategoryKey = $operatorStatus !== null ? $this->operatorCategoryKey($operatorStatus) : null;

        $payload = [
            'id' => $beach->id,
            'name' => $beach->name,
            'wave_level' => $beach->wave_level,
            'operator_status' => $latestOperatorLog?->operator_status,
            'operator_warning' => $latestOperatorLog?->operator_warning,
            'operator_wave_direction' => $latestOperatorLog?->operator_wave_direction,
            'operator_wave_azimuth' => $latestOperatorLog?->operator_wave_azimuth,
            'operator_wave_period' => $latestOperatorLog?->operator_wave_period,
            'operator_access_status' => $latestOperatorLog?->operator_access_status,
            'operator_updated_at' => $latestOperatorLog?->submitted_at,
            'operator_expires_at' => $operatorExpiresAt,
            'expires_at' => $operatorExpiresAt,
            'operator_category_key' => $operatorCategoryKey,
            'operator_category_label' => $operatorCategoryKey ? $this->operatorCategoryLabel($operatorCategoryKey) : null,
            'operator_status_text' => $latestOperatorLog ? $this->operatorStatusText($latestOperatorLog->operator_status) : null,
            'operator_direction_text' => $latestOperatorLog ? $this->operatorDirectionText($latestOperatorLog) : null,
            'operator_access_label' => $latestOperatorLog ? $this->operatorAccessLabel($latestOperatorLog->operator_access_status) : null,
            'operator_data_is_fresh' => $operatorDataIsFresh,
            'operator_data_is_stale' => $operatorDataIsStale,
            'operator_first_name' => $latestOperatorLog?->operator?->first_name,
            'operator_work_phone' => $latestOperatorLog?->operator?->work_phone,
            'effective_wave_level' => $latestOperatorLog?->operator_status ?? $beach->wave_level,
            'category_key' => $latestOperatorLog
                ? $this->operatorCategoryKey($latestOperatorLog->operator_status)
                : $beach->category_key,
            'category_label' => $latestOperatorLog
                ? $this->operatorCategoryLabel($this->operatorCategoryKey($latestOperatorLog->operator_status))
                : $beach->category_label,

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
            'forecast_hour' => $forecast ? $forecast->forecast_hour : null,
            'latest_forecast' => $latestForecast,
            'latest_operator_log' => $operatorLogPayload,
            'reaction_stats' => $interactionService->reactionStats((int) $id),
            'is_favorite' => $interactionService->isFavorite($visitor, (int) $id),
            ...$interactionService->reactionAvailability($visitor, (int) $id),
        ];

        if ($this->dwdDebugEnabled() && $forecast) {
            $payload['dwd_debug'] = [
                'source_folder' => str_pad((string) $forecast->model_run_hour, 2, '0', STR_PAD_LEFT),
                'source_files' => $forecast->source_files,
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
                'forecast_hour' => $forecast->forecast_hour,
            ];
        }

        return response()->json($payload);
    }

    private function latestCompleteOperatorLog(int $beachId, bool $activeOnly = true): ?BeachOperatorLog
    {
        $query = BeachOperatorLog::query()
            ->with('operator')
            ->where('beach_id', $beachId)
            ->whereNotNull('operator_status')
            ->whereNotNull('operator_wave_direction')
            ->whereNotNull('operator_wave_period')
            ->whereNotNull('operator_access_status');

        if ($activeOnly) {
            $query
                ->whereNotNull('expires_at')
                ->where('expires_at', '>=', now());
        }

        return $query
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
            'expires_at' => $log->expires_at,
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
            'is_stale' => $log->expires_at === null || $log->expires_at->lt(now()),
            'operator_first_name' => $log->operator?->first_name,
            'operator_work_phone' => $log->operator?->work_phone,
        ];
    }

    private function operatorCategoryKey(int|string|null $status): string
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

    private function operatorCategoryLabel(string $key): string
    {
        return match ($key) {
            'safe' => 'Купание допустимо',
            'caution' => 'Нужна осторожность',
            default => 'Купание запрещено',
        };

        return match ($key) {
            'safe' => 'РљСѓРїР°РЅРёРµ РґРѕРїСѓСЃС‚РёРјРѕ',
            'caution' => 'РќСѓР¶РЅР° РѕСЃС‚РѕСЂРѕР¶РЅРѕСЃС‚СЊ',
            default => 'РљСѓРїР°РЅРёРµ Р·Р°РїСЂРµС‰РµРЅРѕ',
        };
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
