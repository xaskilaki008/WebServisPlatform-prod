<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

        DB::statement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS beach_polygons (
                id BIGSERIAL PRIMARY KEY,
                source_feature_id INTEGER NOT NULL UNIQUE,
                properties JSONB NOT NULL DEFAULT '{}'::jsonb,
                geom geometry(MultiPolygon, 4326) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL
            )
        SQL);

        DB::statement('CREATE INDEX IF NOT EXISTS beach_polygons_geom_gist ON beach_polygons USING GIST (geom)');

        $path = public_path('sevastopol_beaches_renumbered.geojson');
        if (! is_file($path)) {
            return;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data['features'] ?? null)) {
            return;
        }

        foreach ($data['features'] as $index => $feature) {
            $properties = $feature['properties'] ?? [];
            $geometry = $feature['geometry'] ?? null;
            $geometryType = $geometry['type'] ?? null;

            if (! is_array($geometry) || ! in_array($geometryType, ['Polygon', 'MultiPolygon'], true)) {
                continue;
            }

            $sourceFeatureId = (int) ($properties['id'] ?? $properties['num'] ?? ($index + 1));

            DB::statement(
                <<<'SQL'
                    INSERT INTO beach_polygons (source_feature_id, properties, geom, created_at, updated_at)
                    VALUES (
                        ?,
                        ?::jsonb,
                        ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)),
                        NOW(),
                        NOW()
                    )
                    ON CONFLICT (source_feature_id) DO UPDATE SET
                        properties = EXCLUDED.properties,
                        geom = EXCLUDED.geom,
                        updated_at = NOW()
                SQL,
                [
                    $sourceFeatureId,
                    json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    json_encode($geometry, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('beach_polygons');
    }
};
