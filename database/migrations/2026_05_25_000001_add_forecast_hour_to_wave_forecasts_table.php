<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wave_forecasts', function (Blueprint $table) {
            if (!Schema::hasColumn('wave_forecasts', 'forecast_hour')) {
                $table->unsignedTinyInteger('forecast_hour')->default(0)->after('forecast_time');
            }
        });

        DB::statement('UPDATE wave_forecasts SET forecast_hour = 0 WHERE forecast_hour IS NULL');
        DB::statement("
            UPDATE wave_forecasts
            SET
                model_run_at = timezone('UTC', model_run_at AT TIME ZONE 'Europe/Berlin'),
                forecast_time = timezone('UTC', forecast_time AT TIME ZONE 'Europe/Berlin')
        ");

        Schema::table('wave_forecasts', function (Blueprint $table) {
            $table->dropUnique('wave_forecasts_beach_model_run_unique');
            $table->unique(['beach_id', 'model_run_at', 'forecast_hour'], 'wave_forecasts_beach_model_run_hour_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wave_forecasts', function (Blueprint $table) {
            $table->dropUnique('wave_forecasts_beach_model_run_hour_unique');
            $table->unique(['beach_id', 'model_run_at'], 'wave_forecasts_beach_model_run_unique');

            if (Schema::hasColumn('wave_forecasts', 'forecast_hour')) {
                $table->dropColumn('forecast_hour');
            }
        });
    }
};
