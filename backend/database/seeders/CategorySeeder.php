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
            ],
            [
                'slug' => 'food',
                'translations' => json_encode([
                    'en' => 'Food',
                    'pl' => 'Jedzenie',
                ]),
            ],
            [
                'slug' => 'nature',
                'translations' => json_encode([
                    'en' => 'Nature',
                    'pl' => 'Natura',
                ]),
            ],
            [
                'slug' => 'nightlife',
                'translations' => json_encode([
                    'en' => 'Nightlife',
                    'pl' => 'Życie nocne',
                ]),
            ],
        ];

        DB::table('categories')->upsert($rows, ['slug']);
    }
}
