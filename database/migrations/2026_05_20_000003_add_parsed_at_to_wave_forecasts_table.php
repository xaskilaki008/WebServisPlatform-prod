<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wave_forecasts', function (Blueprint $table) {
            if (!Schema::hasColumn('wave_forecasts', 'parsed_at')) {
                $table->timestamp('parsed_at')->nullable()->after('model_run_hour');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wave_forecasts', function (Blueprint $table) {
            if (Schema::hasColumn('wave_forecasts', 'parsed_at')) {
                $table->dropColumn('parsed_at');
            }
        });
    }
};
