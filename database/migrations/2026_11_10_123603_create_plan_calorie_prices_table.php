<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_calorie_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('subscription_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('calorie_tier');
            $table->unsignedTinyInteger('days_count');
            $table->unsignedInteger('base_total_price');
            $table->unsignedInteger('discounted_total_price')->nullable();
            $table->unsignedInteger('daily_rate');
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->unique(['plan_id', 'calorie_tier', 'days_count'], 'plan_calorie_prices_plan_tier_days_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_calorie_prices');
    }
};
