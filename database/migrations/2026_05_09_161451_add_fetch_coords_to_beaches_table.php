<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('beaches', function (Blueprint $table) {
            $table->decimal('fetch_latitude', 10, 8)->nullable()->comment('Координаты в море для парсера');
            $table->decimal('fetch_longitude', 11, 8)->nullable()->comment('Координаты в море для парсера');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('beaches', function (Blueprint $table) {
            //
        });
    }
};
