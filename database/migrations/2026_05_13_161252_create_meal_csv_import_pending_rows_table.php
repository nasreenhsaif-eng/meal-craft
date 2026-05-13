<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_csv_import_pending_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('meal_name_key');
            $table->string('meal_name');
            $table->string('category');
            $table->text('ingredient_quantities');
            $table->text('instructions')->nullable();
            $table->text('description_highlight')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'meal_name_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_csv_import_pending_rows');
    }
};
