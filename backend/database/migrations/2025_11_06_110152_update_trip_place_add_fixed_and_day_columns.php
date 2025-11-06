<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_place', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_place', 'is_fixed')) {
                $table->boolean('is_fixed')->default(false)->after('status');
            }
            if (!Schema::hasColumn('trip_place', 'day')) {
                $table->unsignedSmallInteger('day')->nullable()->after('is_fixed');
            }
            if (!Schema::hasColumn('trip_place', 'added_by')) {
                $table->foreignId('added_by')->nullable()
                    ->after('day')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_place', function (Blueprint $table) {
            if (Schema::hasColumn('trip_place', 'added_by')) {
                $table->dropConstrainedForeignId('added_by');
            }
            if (Schema::hasColumn('trip_place', 'day')) {
                $table->dropColumn('day');
            }
            if (Schema::hasColumn('trip_place', 'is_fixed')) {
                $table->dropColumn('is_fixed');
            }
        });
    }
};

