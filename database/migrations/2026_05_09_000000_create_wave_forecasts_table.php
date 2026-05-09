<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('wave_forecasts', function (Blueprint $table) {
            $table->id();
            // Внешний ключ связывает прогноз с таблицей beaches
            $table->foreignId('beach_id')->constrained()->onDelete('cascade');
            $table->timestamp('forecast_time')->comment('Время прогноза');

            // Прогнозируемые гидрометеорологические данные
            $table->float('wave_height')->nullable()->comment('Высота волн (м)');
            $table->float('wave_period')->nullable()->comment('Период волны (с)');
            $table->float('wave_direction')->nullable()->comment('Направление (градусы)');
            $table->float('air_temp')->nullable()->comment('Температура воздуха');
            $table->float('water_temp')->nullable()->comment('Температура воды');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('wave_forecasts');
    }
};