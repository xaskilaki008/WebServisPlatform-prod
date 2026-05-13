<?php

namespace Database\Seeders;

use App\Models\Beach;
use App\Models\WaveForecast; // <-- Добавь этот use
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class BeachSeeder extends Seeder
{
    public function run(): void
    {
        // === ЖЕСТКАЯ ОЧИСТКА БАЗЫ С ОБНУЛЕНИЕМ ID ===
        Schema::disableForeignKeyConstraints(); // Отключаем защиту внешних ключей на пару секунд

        WaveForecast::truncate(); // Удаляет всё и сбрасывает ID на 1
        Beach::truncate();        // Удаляет всё и сбрасывает ID на 1

        Schema::enableForeignKeyConstraints();  // Включаем защиту обратно
        // ============================================

        $shorePath = public_path('sevastopol_beaches.geojson');
        $seaPath = public_path('sevastopol_beaches(near point)(0007).geojson');

        $shoreData = json_decode(file_get_contents($shorePath), true);
        $seaData = json_decode(file_get_contents($seaPath), true);

        $missingNumberCounter = -1;

        // 1. Береговые координаты
        foreach ($shoreData['features'] as $feature) {
            if (empty($feature['properties']['name'])) {
                continue;
            }

            $number = $feature['properties']['number'] ?? null;
            if (empty($number)) {
                $number = $missingNumberCounter--;
            }

            Beach::updateOrCreate(
                ['name' => $feature['properties']['name']],
                [
                    'longitude' => $feature['geometry']['coordinates'][0],
                    'latitude' => $feature['geometry']['coordinates'][1],
                    'number' => $number,
                    'wave_level' => 0,
                ]
            );
        }

        // 2. Морские координаты
        foreach ($seaData['features'] as $feature) {
            if (empty($feature['properties']['name'])) {
                continue;
            }

            Beach::where('name', $feature['properties']['name'])
                ->update([
                    'fetch_longitude' => $feature['geometry']['coordinates'][0],
                    'fetch_latitude' => $feature['geometry']['coordinates'][1],
                ]);
        }
    }
}