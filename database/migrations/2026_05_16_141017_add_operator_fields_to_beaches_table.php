<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('beaches', function (Blueprint $table) {
            $table->string('operator_status')->nullable()->comment('Данные от оператора: 0-5 или hazard');
            $table->timestamp('operator_updated_at')->nullable()->comment('Время последней ручной проверки');
        });
    }

    public function down(): void
    {
        Schema::table('beaches', function (Blueprint $table) {
            $table->dropColumn(['operator_status', 'operator_updated_at']);
        });
    }
};