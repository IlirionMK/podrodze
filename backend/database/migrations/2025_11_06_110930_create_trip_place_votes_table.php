<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_place_votes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('score'); // 1–5

            $table->timestamps();

            $table->unique(['trip_id', 'place_id', 'user_id']);
            $table->index(['trip_id', 'place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_place_votes');
    }
};
