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
            $table->string('standardized_name')->nullable();
            $table->decimal('portion_grams', 10, 2)->nullable();
            $table->text('sickle_cell_support_message')->nullable();
            $table->text('usda_description')->nullable();
            $table->string('usda_data_type', 128)->nullable();
            $table->string('usda_food_category', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ingredients', function (Blueprint $table) {
            $table->dropColumn([
                'standardized_name',
                'portion_grams',
                'sickle_cell_support_message',
                'usda_description',
                'usda_data_type',
                'usda_food_category',
            ]);
        });
    }
};
