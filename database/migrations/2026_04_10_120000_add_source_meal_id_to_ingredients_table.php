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
        Schema::table('ingredients', function (Blueprint $table) {
            $table->foreignId('source_meal_id')
                ->nullable()
                ->after('id')
                ->constrained('meals')
                ->nullOnDelete();
            $table->unique('source_meal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropForeign(['source_meal_id']);
            $table->dropUnique(['source_meal_id']);
            $table->dropColumn('source_meal_id');
        });
    }
};
