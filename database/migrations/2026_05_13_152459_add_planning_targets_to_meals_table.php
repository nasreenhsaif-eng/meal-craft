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
        Schema::table('meals', function (Blueprint $table) {
            $table->decimal('target_calories', 12, 2)->nullable()->after('servings_count');
            $table->decimal('target_protein', 12, 2)->nullable()->after('target_calories');
            $table->decimal('target_carbs', 12, 2)->nullable()->after('target_protein');
            $table->decimal('target_fat', 12, 2)->nullable()->after('target_carbs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropColumn(['target_calories', 'target_protein', 'target_carbs', 'target_fat']);
        });
    }
};
