<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlaceSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('places')->truncate();

        $centerLat = 51.1079;
        $centerLon = 17.0385;

        $places = [
            ['name' => 'Panorama Sky Bar', 'category_slug' => 'nightlife', 'rating' => 4.7],
            ['name' => 'Muzeum Narodowe', 'category_slug' => 'museum', 'rating' => 4.6],
            ['name' => 'ZOO Wrocław', 'category_slug' => 'nature', 'rating' => 4.8],
            ['name' => 'Rynek Restaurant', 'category_slug' => 'food', 'rating' => 4.5],
            ['name' => 'Hydropolis', 'category_slug' => 'museum', 'rating' => 4.7],
            ['name' => 'Pergola Garden', 'category_slug' => 'nature', 'rating' => 4.4],
            ['name' => 'Browar Stu Mostów', 'category_slug' => 'food', 'rating' => 4.6],
            ['name' => 'Vertigo Jazz Club', 'category_slug' => 'nightlife', 'rating' => 4.8],
            ['name' => 'Sky Tower Viewpoint', 'category_slug' => 'museum', 'rating' => 4.3],
            ['name' => 'Szczytnicki Park', 'category_slug' => 'nature', 'rating' => 4.9],
        ];

        foreach ($places as $i => $place) {
            $lat = $centerLat + (rand(-30, 30) / 1000.0);
            $lon = $centerLon + (rand(-30, 30) / 1000.0);

            DB::table('places')->insert([
                'name' => $place['name'],
                'category_slug' => $place['category_slug'],
                'rating' => $place['rating'],
                'meta' => json_encode(['source' => 'seeder']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::statement("
                UPDATE places
                SET location = ST_SetSRID(ST_MakePoint($lon, $lat), 4326)::geography
                WHERE name = '{$place['name']}'
            ");
        }
    }
}
