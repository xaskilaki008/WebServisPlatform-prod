<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wave_forecasts', function (Blueprint $table) {
            if (!Schema::hasColumn('wave_forecasts', 'source_files')) {
                $table->json('source_files')->nullable()->after('parsed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wave_forecasts', function (Blueprint $table) {
            if (Schema::hasColumn('wave_forecasts', 'source_files')) {
                $table->dropColumn('source_files');
            }
        });
    }
};
