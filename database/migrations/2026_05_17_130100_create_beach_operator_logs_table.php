<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('beach_operator_logs')) {
            return;
        }

        Schema::create('beach_operator_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beach_operator_id')->constrained('beach_operators')->cascadeOnDelete();
            $table->foreignId('beach_id')->constrained('beaches')->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beach_operator_logs');
    }
};
