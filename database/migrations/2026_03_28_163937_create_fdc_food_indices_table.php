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
        Schema::create('fdc_food_indices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fdc_id')->unique();
            $table->string('data_type', 64);
            $table->text('description');
            $table->string('ndb_number', 32)->nullable();
            $table->string('food_category', 191)->nullable();
            $table->string('publication_date', 64)->nullable();
            $table->timestamps();

            $table->index('food_category');
            $table->index('data_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fdc_food_indices');
    }
};
