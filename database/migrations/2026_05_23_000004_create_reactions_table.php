<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reactions')) {
            return;
        }

        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('beach_id')->constrained('beaches')->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained('visitors')->cascadeOnDelete();
            $table->string('reaction_type');
            $table->timestamps();

            $table->index(['beach_id', 'reaction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
    }
};
