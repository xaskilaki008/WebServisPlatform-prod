<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('beach_operator_logs')->delete();
        DB::table('beach_operators')->delete();

        Schema::table('beach_operators', function (Blueprint $table) {
            if (!Schema::hasColumn('beach_operators', 'login')) {
                $table->string('login')->unique()->after('beach_id');
            }

            if (!Schema::hasColumn('beach_operators', 'password')) {
                $table->string('password')->after('login');
            }

            if (!Schema::hasColumn('beach_operators', 'last_name')) {
                $table->string('last_name')->after('password');
            }

            if (!Schema::hasColumn('beach_operators', 'first_name')) {
                $table->string('first_name')->after('last_name');
            }

            if (!Schema::hasColumn('beach_operators', 'middle_name')) {
                $table->string('middle_name')->after('first_name');
            }

            if (!Schema::hasColumn('beach_operators', 'work_phone')) {
                $table->string('work_phone', 11)->after('middle_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('beach_operators', function (Blueprint $table) {
            if (Schema::hasColumn('beach_operators', 'work_phone')) {
                $table->dropColumn('work_phone');
            }

            if (Schema::hasColumn('beach_operators', 'middle_name')) {
                $table->dropColumn('middle_name');
            }

            if (Schema::hasColumn('beach_operators', 'first_name')) {
                $table->dropColumn('first_name');
            }

            if (Schema::hasColumn('beach_operators', 'last_name')) {
                $table->dropColumn('last_name');
            }

            if (Schema::hasColumn('beach_operators', 'password')) {
                $table->dropColumn('password');
            }

            if (Schema::hasColumn('beach_operators', 'login')) {
                $table->dropUnique(['login']);
                $table->dropColumn('login');
            }
        });
    }
};
