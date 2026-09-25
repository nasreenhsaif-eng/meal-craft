<?php

use App\Enums\MealLibraryKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table): void {
            $table->string('library_key', 32)
                ->default(MealLibraryKey::Classic->value)
                ->index();
        });

        Schema::create('meal_calorie_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meal_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('calorie_tier');
            $table->decimal('designed_calories', 10, 2)->default(0);
            $table->decimal('total_calories', 10, 2)->default(0);
            $table->decimal('total_protein', 10, 2)->default(0);
            $table->decimal('total_carbs', 10, 2)->default(0);
            $table->decimal('total_fat', 10, 2)->default(0);
            $table->json('nutrition')->nullable();
            $table->timestamps();

            $table->unique(['meal_id', 'calorie_tier']);
        });

        Schema::create('ingredient_meal_tier', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meal_calorie_tier_id')->constrained('meal_calorie_tiers')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_grams', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['meal_calorie_tier_id', 'ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_meal_tier');
        Schema::dropIfExists('meal_calorie_tiers');

        Schema::table('meals', function (Blueprint $table): void {
            $table->dropIndex(['library_key']);
            $table->dropColumn('library_key');
        });
    }
};
