<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('category')->index();
            $table->text('instructions')->nullable();

            $table->decimal('total_calories', 10, 2)->default(0);
            $table->decimal('total_protein', 10, 2)->default(0);
            $table->decimal('total_carbs', 10, 2)->default(0);
            $table->decimal('total_fat', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
