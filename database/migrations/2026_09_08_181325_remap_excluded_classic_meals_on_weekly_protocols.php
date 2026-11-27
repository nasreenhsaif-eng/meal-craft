<?php

use App\Models\MealPlan;
use App\Support\ScheduledTiersMealResolver;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        MealPlan::query()
            ->whereIn('name', [
                'TBD Weekly Protocol',
                'Balanced Weekly Protocol',
            ])
            ->each(function (MealPlan $plan): void {
                ScheduledTiersMealResolver::remapPlan($plan);
            });
    }

    public function down(): void
    {
        //
    }
};
