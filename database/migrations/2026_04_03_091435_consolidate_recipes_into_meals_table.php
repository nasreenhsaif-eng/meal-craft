<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->string('category')->nullable()->index()->after('name');
            $table->decimal('total_calories', 10, 2)->default(0)->after('image_path');
            $table->decimal('total_protein', 10, 2)->default(0);
            $table->decimal('total_carbs', 10, 2)->default(0);
            $table->decimal('total_fat', 10, 2)->default(0);
            $table->decimal('total_b6', 14, 4)->default(0);
            $table->decimal('total_folate', 14, 4)->default(0);
            $table->decimal('total_b12', 14, 4)->default(0);
            $table->decimal('total_iron', 14, 4)->default(0);
            $table->decimal('total_magnesium', 14, 4)->default(0);
            $table->decimal('total_fiber', 14, 4)->default(0);
            $table->decimal('total_sugar', 14, 4)->default(0);
            $table->decimal('total_calcium', 14, 4)->default(0);
            $table->decimal('total_potassium', 14, 4)->default(0);
            $table->decimal('total_sodium', 14, 4)->default(0);
            $table->decimal('total_zinc', 14, 4)->default(0);
            $table->decimal('total_vitamin_c', 14, 4)->default(0);
            $table->decimal('total_vitamin_a', 14, 4)->default(0);
            $table->decimal('total_vitamin_e', 14, 4)->default(0);
            $table->decimal('total_vitamin_d', 14, 4)->default(0);
            $table->decimal('total_vitamin_k', 14, 4)->default(0);
        });

        Schema::table('ingredient_meal', function (Blueprint $table) {
            $table->decimal('amount', 14, 4)->nullable()->after('meal_id');
            $table->string('unit', 16)->nullable()->after('amount');
        });

        DB::table('ingredient_meal')->whereNull('amount')->update([
            'amount' => DB::raw('amount_grams'),
            'unit' => 'g',
        ]);

        if (! Schema::hasTable('recipes')) {
            return;
        }

        $now = now();

        foreach (DB::table('recipes')->orderBy('id')->get() as $recipe) {
            $mealId = DB::table('meals')->insertGetId([
                'name' => $recipe->name,
                'category' => $recipe->category,
                'description' => $recipe->instructions,
                'image_path' => null,
                'total_calories' => $recipe->total_calories,
                'total_protein' => $recipe->total_protein,
                'total_carbs' => $recipe->total_carbs,
                'total_fat' => $recipe->total_fat,
                'total_b6' => $recipe->total_b6 ?? 0,
                'total_folate' => $recipe->total_folate ?? 0,
                'total_b12' => $recipe->total_b12 ?? 0,
                'total_iron' => $recipe->total_iron ?? 0,
                'total_magnesium' => $recipe->total_magnesium ?? 0,
                'total_fiber' => $recipe->total_fiber ?? 0,
                'total_sugar' => $recipe->total_sugar ?? 0,
                'total_calcium' => $recipe->total_calcium ?? 0,
                'total_potassium' => $recipe->total_potassium ?? 0,
                'total_sodium' => $recipe->total_sodium ?? 0,
                'total_zinc' => $recipe->total_zinc ?? 0,
                'total_vitamin_c' => $recipe->total_vitamin_c ?? 0,
                'total_vitamin_a' => $recipe->total_vitamin_a ?? 0,
                'total_vitamin_e' => $recipe->total_vitamin_e ?? 0,
                'total_vitamin_d' => $recipe->total_vitamin_d ?? 0,
                'total_vitamin_k' => $recipe->total_vitamin_k ?? 0,
                'created_at' => $recipe->created_at ?? $now,
                'updated_at' => $recipe->updated_at ?? $now,
            ]);

            $pivots = DB::table('recipe_ingredient')->where('recipe_id', $recipe->id)->get();

            foreach ($pivots as $pivot) {
                DB::table('ingredient_meal')->insert([
                    'ingredient_id' => $pivot->ingredient_id,
                    'meal_id' => $mealId,
                    'amount_grams' => $pivot->amount_grams,
                    'amount' => $pivot->amount ?? $pivot->amount_grams,
                    'unit' => $pivot->unit ?? 'g',
                    'created_at' => $pivot->created_at ?? $now,
                    'updated_at' => $pivot->updated_at ?? $now,
                ]);
            }
        }

        Schema::dropIfExists('recipe_ingredient');
        Schema::dropIfExists('recipes');
    }

    public function down(): void
    {
        Schema::table('ingredient_meal', function (Blueprint $table) {
            $table->dropColumn(['amount', 'unit']);
        });

        Schema::table('meals', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'total_calories',
                'total_protein',
                'total_carbs',
                'total_fat',
                'total_b6',
                'total_folate',
                'total_b12',
                'total_iron',
                'total_magnesium',
                'total_fiber',
                'total_sugar',
                'total_calcium',
                'total_potassium',
                'total_sodium',
                'total_zinc',
                'total_vitamin_c',
                'total_vitamin_a',
                'total_vitamin_e',
                'total_vitamin_d',
                'total_vitamin_k',
            ]);
        });
    }
};
