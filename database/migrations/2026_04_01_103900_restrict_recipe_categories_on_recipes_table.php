<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @return list<string>
     */
    private function allowedCategories(): array
    {
        return ['Breakfast', 'Soup', 'Side Salad', 'Main Salad', 'Meal', 'Dessert'];
    }

    public function up(): void
    {
        $allowed = $this->allowedCategories();

        DB::table('recipes')
            ->whereNotIn('category', $allowed)
            ->update(['category' => 'Meal']);

        Schema::table('recipes', function (Blueprint $table) use ($allowed) {
            $table->enum('category', $allowed)->change();
        });
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->string('category')->change();
        });
    }
};
