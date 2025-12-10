<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'slug' => 'museum',
                'translations' => json_encode([
                    'en' => 'Museums',
                    'pl' => 'Muzea',
                ]),
                'include_in_preferences' => true,
            ],
            [
                'slug' => 'food',
                'translations' => json_encode([
                    'en' => 'Food',
                    'pl' => 'Jedzenie',
                ]),
                'include_in_preferences' => true,
            ],
            [
                'slug' => 'nature',
                'translations' => json_encode([
                    'en' => 'Nature',
                    'pl' => 'Natura',
                ]),
                'include_in_preferences' => true,
            ],
            [
                'slug' => 'nightlife',
                'translations' => json_encode([
                    'en' => 'Nightlife',
                    'pl' => 'Życie nocne',
                ]),
                'include_in_preferences' => true,
            ],
            [
                'slug' => 'attraction',
                'translations' => json_encode([
                    'en' => 'Attractions',
                    'pl' => 'Atrakcje',
                ]),
                'include_in_preferences' => true,
            ],
            [
                'slug' => 'hotel',
                'translations' => json_encode([
                    'en' => 'Hotels',
                    'pl' => 'Hotele',
                ]),
                'include_in_preferences' => false,
            ],
            [
                'slug' => 'airport',
                'translations' => json_encode([
                    'en' => 'Airports',
                    'pl' => 'Lotniska',
                ]),
                'include_in_preferences' => false,
            ],
            [
                'slug' => 'station',
                'translations' => json_encode([
                    'en' => 'Stations',
                    'pl' => 'Stacje',
                ]),
                'include_in_preferences' => false,
            ],
            [
                'slug' => 'other',
                'translations' => json_encode([
                    'en' => 'Other',
                    'pl' => 'Inne',
                ]),
                'include_in_preferences' => false,
            ],
        ];

        DB::table('categories')->upsert(
            $rows,
            ['slug'],
            ['translations', 'include_in_preferences']
        );
    }
}
