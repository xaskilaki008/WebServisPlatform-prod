<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('beach_operators', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('beach_id')->constrained('beaches')->onDelete('cascade');
            $blueprint->string('operator_hash')->unique(); // Тот самый хэш, который пишется в Cookie
            $blueprint->string('name')->nullable();
            $blueprint->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beach_operators');
    }
};