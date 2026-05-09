<?php

namespace Database\Seeders;

use App\Models\Beach;
use Illuminate\Database\Seeder;

class BeachSeeder extends Seeder
{
    public function run(): void
    {
        $shorePath = public_path('sevastopol_beaches.geojson');
        $seaPath = public_path('sevastopol_beaches(near point).geojson');

        $shoreData = json_decode(file_get_contents($shorePath), true);
        $seaData = json_decode(file_get_contents($seaPath), true);

        // 1. Береговые координаты
        foreach ($shoreData['features'] as $feature) {
            if (empty($feature['properties']['name'])) {
                continue;
            }

            Beach::updateOrCreate(
                [
                    'name' => $feature['properties']['name']
                ],
                [
                    'longitude' => $feature['geometry']['coordinates'][0],
                    'latitude' => $feature['geometry']['coordinates'][1],
                    'number' => $feature['properties']['number'] ?? 0,
                    'wave_level' => 0, // <--- Добавили дефолтное значение
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