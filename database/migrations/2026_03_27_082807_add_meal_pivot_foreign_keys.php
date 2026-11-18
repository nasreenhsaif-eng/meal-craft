<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link the meal pivots after ingredients, meals, and meal plans exist.
     *
     * Those tables share one migration timestamp, so MySQL was creating the
     * pivots first and rejecting the foreign keys.
     */
    public function up(): void
    {
        $this->foreignKey('ingredient_meal', 'ingredient_id', 'ingredients');
        $this->foreignKey('ingredient_meal', 'meal_id', 'meals');
        $this->foreignKey('meal_meal_plan', 'meal_id', 'meals');
        $this->foreignKey('meal_meal_plan', 'meal_plan_id', 'meal_plans');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropForeignKey('ingredient_meal', 'ingredient_id');
        $this->dropForeignKey('ingredient_meal', 'meal_id');
        $this->dropForeignKey('meal_meal_plan', 'meal_id');
        $this->dropForeignKey('meal_meal_plan', 'meal_plan_id');
    }

    private function foreignKey(string $table, string $column, string $referencedTable): void
    {
        if ($this->hasForeignKey($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable): void {
            $blueprint->foreign($column)->references('id')->on($referencedTable)->cascadeOnDelete();
        });
    }

    private function dropForeignKey(string $table, string $column): void
    {
        if (! $this->hasForeignKey($table, $column)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->dropForeign([$column]);
        });
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        foreach (Schema::getForeignKeys($table) as $foreign) {
            if (in_array($column, $foreign['columns'], true)) {
                return true;
            }
        }

        return false;
    }
};
