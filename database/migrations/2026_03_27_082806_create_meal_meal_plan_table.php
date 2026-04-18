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
        Schema::create('meal_meal_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_plan_id')->constrained()->cascadeOnDelete();
            $table->string('day_of_week');
            $table->string('meal_type');
            $table->timestamps();

            $table->unique(['meal_plan_id', 'day_of_week', 'meal_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_meal_plan');
    }
};
