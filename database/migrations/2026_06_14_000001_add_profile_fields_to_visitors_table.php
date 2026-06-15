<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            if (!Schema::hasColumn('visitors', 'nickname_key')) {
                $table->string('nickname_key')->nullable()->after('nickname');
                $table->unique('nickname_key', 'visitors_nickname_key_unique');
            }

            if (!Schema::hasColumn('visitors', 'last_name')) {
                $table->string('last_name')->nullable()->after('email');
            }

            if (!Schema::hasColumn('visitors', 'first_name')) {
                $table->string('first_name')->nullable()->after('last_name');
            }

            if (!Schema::hasColumn('visitors', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('visitors', function (Blueprint $table) {
            if (Schema::hasColumn('visitors', 'nickname_key')) {
                $table->dropUnique('visitors_nickname_key_unique');
                $table->dropColumn('nickname_key');
            }

            foreach (['middle_name', 'first_name', 'last_name'] as $column) {
                if (Schema::hasColumn('visitors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
