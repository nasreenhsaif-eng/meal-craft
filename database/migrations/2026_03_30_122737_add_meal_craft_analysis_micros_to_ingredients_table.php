<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meal Craft Analysis / library: first-class per-100 g micronutrients for Sickling planning and local-first search (alongside JSON micronutrients).
     */
    public function up(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->decimal('b6', 10, 4)->default(0)->comment('Vitamin B6 mg per 100 g');
            $table->decimal('b9_folate', 10, 4)->default(0)->comment('Folate (B9) µg per 100 g');
            $table->decimal('b12', 10, 4)->default(0)->comment('Vitamin B12 µg per 100 g');
            $table->decimal('iron', 10, 4)->default(0)->comment('Iron mg per 100 g');
            $table->decimal('magnesium', 10, 4)->default(0)->comment('Magnesium mg per 100 g');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table): void {
            $table->dropColumn(['b6', 'b9_folate', 'b12', 'iron', 'magnesium']);
        });
    }
};
