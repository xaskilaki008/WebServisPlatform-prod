<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('email_verification_codes')) {
            Schema::create('email_verification_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('code_hash');
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'expires_at'], 'email_codes_user_expires_idx');
            });
        }

        if (!Schema::hasTable('user_action_logs')) {
            Schema::create('user_action_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->string('entity_type')->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->text('description')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'created_at'], 'user_action_logs_user_created_idx');
                $table->index(['entity_type', 'entity_id'], 'user_action_logs_entity_idx');
            });
        }

        if (!Schema::hasTable('operators')) {
            Schema::create('operators', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('work_phone', 32)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('operator_beach')) {
            Schema::create('operator_beach', function (Blueprint $table) {
                $table->id();
                $table->foreignId('operator_id')->constrained('operators')->cascadeOnDelete();
                $table->foreignId('beach_id')->constrained('beaches')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['operator_id', 'beach_id'], 'operator_beach_operator_beach_unique');
                $table->index('beach_id', 'operator_beach_beach_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operator_beach');
        Schema::dropIfExists('operators');
        Schema::dropIfExists('user_action_logs');
        Schema::dropIfExists('email_verification_codes');
    }
};
