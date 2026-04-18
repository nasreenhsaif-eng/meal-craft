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
            $table->string('meal_type', 32)->default('main')->after('category');
        });

        $map = [
            'Breakfast' => 'breakfast',
            'Meal' => 'main',
            'Soup' => 'soup',
            'Side Salad' => 'salad',
            'Main Salad' => 'salad',
            'Dessert' => 'dessert',
        ];

        foreach ($map as $categoryValue => $mealType) {
            DB::table('meals')->where('category', $categoryValue)->update(['meal_type' => $mealType]);
        }
    }

    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropColumn('meal_type');
        });
    }
};
