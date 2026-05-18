<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wave_forecasts', function (Blueprint $table) {
            if (!Schema::hasColumn('wave_forecasts', 'model_run_at')) {
                $table->timestamp('model_run_at')->nullable()->after('forecast_time');
            }

            if (!Schema::hasColumn('wave_forecasts', 'model_run_hour')) {
                $table->unsignedTinyInteger('model_run_hour')->nullable()->after('model_run_at');
            }
        });

        DB::statement("
            UPDATE wave_forecasts
            SET
                model_run_hour = CASE
                    WHEN EXTRACT(HOUR FROM forecast_time) < 12 THEN 0
                    ELSE 12
                END,
                model_run_at = date_trunc('day', forecast_time) + (
                    CASE
                        WHEN EXTRACT(HOUR FROM forecast_time) < 12 THEN interval '0 hours'
                        ELSE interval '12 hours'
                    END
                )
            WHERE model_run_at IS NULL
        ");

        DB::statement("
            DELETE FROM wave_forecasts wf
            USING wave_forecasts duplicate
            WHERE wf.beach_id = duplicate.beach_id
                AND wf.model_run_at = duplicate.model_run_at
                AND wf.id < duplicate.id
        ");

        Schema::table('wave_forecasts', function (Blueprint $table) {
            $table->unique(['beach_id', 'model_run_at'], 'wave_forecasts_beach_model_run_unique');
        });
    }

    public function down(): void
    {
        Schema::table('wave_forecasts', function (Blueprint $table) {
            $table->dropUnique('wave_forecasts_beach_model_run_unique');

            if (Schema::hasColumn('wave_forecasts', 'model_run_hour')) {
                $table->dropColumn('model_run_hour');
            }

            if (Schema::hasColumn('wave_forecasts', 'model_run_at')) {
                $table->dropColumn('model_run_at');
            }
        });
    }
};
