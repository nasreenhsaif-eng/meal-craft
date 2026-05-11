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
            $table->json('diet_tags')->nullable()->after('cycle_phase_compatibility_tooltips');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->json('diet_tags')->nullable()->after('micronutrients');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meals', function (Blueprint $table) {
            $table->dropColumn('diet_tags');
        });

        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn('diet_tags');
        });
    }
};
