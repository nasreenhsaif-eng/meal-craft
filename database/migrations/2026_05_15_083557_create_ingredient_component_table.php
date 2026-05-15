<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingredient_component', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_ingredient_id')
                ->constrained('ingredients')
                ->cascadeOnDelete();
            $table->foreignId('child_ingredient_id')
                ->constrained('ingredients')
                ->cascadeOnDelete();
            $table->decimal('amount_grams', 14, 4);
            $table->timestamps();

            $table->unique(['parent_ingredient_id', 'child_ingredient_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingredient_component');
    }
};
