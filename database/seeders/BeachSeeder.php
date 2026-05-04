<?php

namespace Database\Seeders;

use App\Models\Beach;
use Illuminate\Database\Seeder;

class BeachSeeder extends Seeder
{
    public function run(): void
    {
        $path = public_path('sevastopol_beaches.geojson');
        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (! is_array($data['features'] ?? null)) {
            return;
        }

        foreach ($data['features'] as $feature) {
            $properties = $feature['properties'] ?? [];
            $coordinates = $feature['geometry']['coordinates'] ?? null;

            if (! is_array($coordinates) || count($coordinates) < 2 || ! isset($properties['num'], $properties['name'])) {
                continue;
            }

            Beach::updateOrCreate(
                ['number' => (int) $properties['num']],
                [
                    'name' => (string) $properties['name'],
                    'latitude' => (float) $coordinates[1],
                    'longitude' => (float) $coordinates[0],
                    'wave_level' => isset($properties['wave_level']) ? (int) $properties['wave_level'] : 0,
                ]
            );
        }
    }
}
