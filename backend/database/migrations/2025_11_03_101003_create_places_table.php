<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('name');                // Название места
            $table->string('category_slug')->nullable(); // Категория (museum, food, etc.)
            $table->float('rating')->nullable();   // Средняя оценка (например, из Google)
            $table->jsonb('meta')->nullable();     // Дополнительные данные (часы, фото, и т.д.)
            $table->timestamps();
        });

        DB::statement('ALTER TABLE places ADD COLUMN location geography(Point, 4326)');

        DB::statement('CREATE INDEX places_location_gix ON places USING GIST(location)');
    }

    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
