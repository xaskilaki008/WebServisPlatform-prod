<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table) {
            if (!Schema::hasColumn('email_verification_codes', 'purpose')) {
                $table->string('purpose', 32)->default('registration')->after('user_id');
            }
        });

        DB::table('email_verification_codes')
            ->whereNull('purpose')
            ->update(['purpose' => 'registration']);

        Schema::table('email_verification_codes', function (Blueprint $table) {
            $table->index(['user_id', 'purpose', 'expires_at'], 'email_codes_user_purpose_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::table('email_verification_codes', function (Blueprint $table) {
            $table->dropIndex('email_codes_user_purpose_expires_idx');

            if (Schema::hasColumn('email_verification_codes', 'purpose')) {
                $table->dropColumn('purpose');
            }
        });
    }
};
