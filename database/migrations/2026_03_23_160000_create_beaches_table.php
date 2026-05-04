<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beaches', function (Blueprint $table) {
            $table->id();
            $table->integer('number')->unique();
            $table->string('name');
            $table->double('latitude');
            $table->double('longitude');
            $table->integer('wave_level');
            $table->timestamps();
        });

        // Отдельное ограничение удобно расширять позже при интеграции с датчиками и API.
        DB::statement('ALTER TABLE beaches ADD CONSTRAINT beaches_wave_level_check CHECK (wave_level BETWEEN 0 AND 12)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE beaches DROP CONSTRAINT IF EXISTS beaches_wave_level_check');
        Schema::dropIfExists('beaches');
    }
};