<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('start_latitude', 9, 6)->nullable()->after('end_date');
            $table->decimal('start_longitude', 9, 6)->nullable()->after('start_latitude');
        });

        DB::statement('ALTER TABLE trips ADD COLUMN start_location geography(Point,4326)');
        DB::statement('CREATE INDEX IF NOT EXISTS trips_start_location_gix ON trips USING GIST (start_location)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS trips_start_location_gix');
        DB::statement('ALTER TABLE trips DROP COLUMN IF EXISTS start_location');

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['start_latitude', 'start_longitude']);
        });
    }
};
