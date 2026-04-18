<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->string('schema_type', 32)->default('weekly')->after('goal');
            $table->decimal('target_total_calories', 12, 2)->nullable()->after('schema_type');
            $table->decimal('target_total_protein_g', 12, 2)->nullable()->after('target_total_calories');
            $table->decimal('target_total_carbs_g', 12, 2)->nullable()->after('target_total_protein_g');
            $table->decimal('target_total_fat_g', 12, 2)->nullable()->after('target_total_carbs_g');
        });

        Schema::create('meal_plan_day_meal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_number');
            $table->string('slot_type', 32);
            $table->unsignedTinyInteger('slot_index');
            $table->boolean('is_option_b')->default(false);
            $table->timestamps();

            $table->unique(
                ['meal_plan_id', 'day_number', 'slot_type', 'slot_index', 'is_option_b'],
                'meal_plan_day_meal_slot_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_day_meal');

        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropColumn([
                'schema_type',
                'target_total_calories',
                'target_total_protein_g',
                'target_total_carbs_g',
                'target_total_fat_g',
            ]);
        });
    }
};
