<?php

use App\Services\SaladDressingMealRefiner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meals') && ! Schema::hasColumn('meals', 'library_edited_at')) {
            Schema::table('meals', function (Blueprint $table): void {
                $table->timestamp('library_edited_at')->nullable()->after('updated_at');
            });
        }

        if (Schema::hasTable('ingredients') && ! Schema::hasColumn('ingredients', 'library_edited_at')) {
            Schema::table('ingredients', function (Blueprint $table): void {
                $table->timestamp('library_edited_at')->nullable()->after('updated_at');
            });
        }

        if (! Schema::hasColumn('meals', 'library_edited_at')) {
            return;
        }

        if (class_exists(SaladDressingMealRefiner::class)) {
            app(SaladDressingMealRefiner::class)->refine('Classic Garden Salad');
        }

        DB::table('meals')
            ->whereNull('library_edited_at')
            ->where('instructions', 'like', '%Serve dressing on the side%')
            ->update(['library_edited_at' => DB::raw('COALESCE(updated_at, CURRENT_TIMESTAMP)')]);

        DB::table('meals')
            ->whereNull('library_edited_at')
            ->whereBetween('updated_at', ['2026-06-25 10:45:00', '2026-06-25 10:46:00'])
            ->update(['library_edited_at' => DB::raw('COALESCE(updated_at, CURRENT_TIMESTAMP)')]);

        foreach ([
            'Power Breakfast Bowl',
            'Spicy Harissa Grilled Chicken w Roasted Sweet Potato & Zucchini',
            'Smashed Beans & Eggs',
            'Grilled Chicken Tikka Salad w Quinoa & Cilantro Lime Dressing',
            'Blackened Chicken, Grilled Peppers & Onion Salad w Quinoa, Kale & Mustard Dressing',
        ] as $mealName) {
            DB::table('meals')
                ->whereNull('library_edited_at')
                ->where('name', $mealName)
                ->update(['library_edited_at' => DB::raw('COALESCE(updated_at, CURRENT_TIMESTAMP)')]);
        }

        if (Schema::hasColumn('ingredients', 'library_edited_at')) {
            DB::table('ingredients')
                ->whereNull('library_edited_at')
                ->where('usda_food_category', 'Base Ingredient')
                ->whereExists(function ($query): void {
                    $query->select(DB::raw(1))
                        ->from('ingredient_component')
                        ->whereColumn('ingredient_component.parent_ingredient_id', 'ingredients.id');
                })
                ->update(['library_edited_at' => DB::raw('COALESCE(updated_at, CURRENT_TIMESTAMP)')]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('meals') && Schema::hasColumn('meals', 'library_edited_at')) {
            Schema::table('meals', function (Blueprint $table): void {
                $table->dropColumn('library_edited_at');
            });
        }

        if (Schema::hasTable('ingredients') && Schema::hasColumn('ingredients', 'library_edited_at')) {
            Schema::table('ingredients', function (Blueprint $table): void {
                $table->dropColumn('library_edited_at');
            });
        }
    }
};
