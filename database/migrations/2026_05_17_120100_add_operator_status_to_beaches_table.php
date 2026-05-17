<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('beaches', 'operator_status')) {
            return;
        }

        Schema::table('beaches', function (Blueprint $table) {
            $table->string('operator_status')->nullable()->after('wave_level');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('beaches', 'operator_status')) {
            return;
        }

        Schema::table('beaches', function (Blueprint $table) {
            $table->dropColumn('operator_status');
        });
    }
};
