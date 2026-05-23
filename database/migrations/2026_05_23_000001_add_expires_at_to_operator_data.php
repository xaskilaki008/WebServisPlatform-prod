<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beaches', function (Blueprint $table) {
            if (!Schema::hasColumn('beaches', 'operator_expires_at')) {
                $table->timestamp('operator_expires_at')->nullable();
            }
        });

        Schema::table('beach_operator_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('beach_operator_logs', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('beach_operator_logs', function (Blueprint $table) {
            if (Schema::hasColumn('beach_operator_logs', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });

        Schema::table('beaches', function (Blueprint $table) {
            if (Schema::hasColumn('beaches', 'operator_expires_at')) {
                $table->dropColumn('operator_expires_at');
            }
        });
    }
};
