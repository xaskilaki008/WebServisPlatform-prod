<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beach_operator_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('beach_operator_logs', 'operator_status')) {
                $table->string('operator_status')->nullable()->after('submitted_at');
            }

            if (!Schema::hasColumn('beach_operator_logs', 'operator_warning')) {
                $table->string('operator_warning', 250)->nullable()->after('operator_status');
            }

            if (!Schema::hasColumn('beach_operator_logs', 'operator_wave_direction')) {
                $table->string('operator_wave_direction')->nullable()->after('operator_warning');
            }

            if (!Schema::hasColumn('beach_operator_logs', 'operator_wave_azimuth')) {
                $table->unsignedSmallInteger('operator_wave_azimuth')->nullable()->after('operator_wave_direction');
            }

            if (!Schema::hasColumn('beach_operator_logs', 'operator_wave_period')) {
                $table->unsignedTinyInteger('operator_wave_period')->nullable()->after('operator_wave_azimuth');
            }

            if (!Schema::hasColumn('beach_operator_logs', 'operator_access_status')) {
                $table->string('operator_access_status')->nullable()->after('operator_wave_period');
            }
        });
    }

    public function down(): void
    {
        Schema::table('beach_operator_logs', function (Blueprint $table) {
            foreach ([
                'operator_access_status',
                'operator_wave_period',
                'operator_wave_azimuth',
                'operator_wave_direction',
                'operator_warning',
                'operator_status',
            ] as $column) {
                if (Schema::hasColumn('beach_operator_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
