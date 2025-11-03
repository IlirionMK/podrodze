<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_place', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->integer('order_index')->nullable();
            $table->string('status')->default('planned');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['trip_id', 'place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_place');
    }
};
