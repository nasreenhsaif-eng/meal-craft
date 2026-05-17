<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->unsignedInteger('library_sort_order')->nullable()->after('id');
        });

        $position = 0;
        DB::table('meals')
            ->whereNull('deleted_at')
            ->where('meal_type', '!=', MealType::BaseRecipe->value)
            ->where(function ($query): void {
                $query->whereNull('category')
                    ->orWhere('category', '!=', RecipeCategory::BaseRecipe->value);
            })
            ->orderByDesc('updated_at')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $id) use (&$position): void {
                DB::table('meals')->where('id', $id)->update(['library_sort_order' => $position]);
                $position++;
            });
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropColumn('library_sort_order');
        });
    }
};
