<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('sex', 16)->nullable();
            $table->string('activity_level', 32)->nullable();
            $table->string('macro_split_style', 32)->default('balanced');
            $table->unsignedInteger('daily_calorie_target');
            $table->decimal('protein_percentage', 5, 2);
            $table->decimal('carb_percentage', 5, 2);
            $table->decimal('fat_percentage', 5, 2);
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_profiles');
    }
};
