<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beaches', function (Blueprint $table) {
            if (!Schema::hasColumn('beaches', 'operator_warning')) {
                $table->string('operator_warning', 250)->nullable()->after('operator_status');
            }
            if (!Schema::hasColumn('beaches', 'operator_wave_direction')) {
                $table->string('operator_wave_direction')->nullable()->after('operator_warning');
            }
            if (!Schema::hasColumn('beaches', 'operator_wave_azimuth')) {
                $table->unsignedSmallInteger('operator_wave_azimuth')->nullable()->after('operator_wave_direction');
            }
            if (!Schema::hasColumn('beaches', 'operator_wave_period')) {
                $table->unsignedTinyInteger('operator_wave_period')->nullable()->after('operator_wave_azimuth');
            }
            if (!Schema::hasColumn('beaches', 'operator_access_status')) {
                $table->string('operator_access_status')->nullable()->after('operator_wave_period');
            }
            if (!Schema::hasColumn('beaches', 'operator_updated_at')) {
                $table->timestamp('operator_updated_at')->nullable()->after('operator_access_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('beaches', function (Blueprint $table) {
            foreach ([
                'operator_warning',
                'operator_wave_direction',
                'operator_wave_azimuth',
                'operator_wave_period',
                'operator_access_status',
                'operator_updated_at',
            ] as $column) {
                if (Schema::hasColumn('beaches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
