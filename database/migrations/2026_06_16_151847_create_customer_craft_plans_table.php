<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_craft_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_profile_id')->constrained()->cascadeOnDelete();
            $table->string('craft_key', 32);
            $table->unsignedTinyInteger('week_duration');
            $table->json('selected_weekdays');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['customer_profile_id', 'submitted_at']);
        });

        Schema::create('customer_craft_plan_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_craft_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->boolean('include_soup')->default(false);
            $table->timestamps();

            $table->unique(['customer_craft_plan_id', 'day_of_week']);
        });

        Schema::create('customer_craft_plan_day_meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_craft_plan_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->string('slot', 32);
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestamps();

            $table->unique(['customer_craft_plan_day_id', 'slot', 'position'], 'craft_day_meal_slot_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_craft_plan_day_meals');
        Schema::dropIfExists('customer_craft_plan_days');
        Schema::dropIfExists('customer_craft_plans');
    }
};
