<?php

namespace App\Console\Commands;

use App\Enums\MealPlanSlotType;
use App\Services\BalancedWeeklyMealPlanBuilder;
use App\Services\BalancedWeeklyRotationSchedule;
use App\Services\MenuDevelopmentCsvExport;
use Illuminate\Console\Command;

class BuildBalancedWeeklyMealPlanCommand extends Command
{
    protected $signature = 'meals:build-balanced-weekly-plan
                            {--skip-refine : Do not rewrite canonical meal recipes}
                            {--export-csv : Write database/data/menu/meals.csv after building}';

    protected $description = 'Refine canonical Balanced meals and build the 7-day production weekly structured plan';

    public function handle(BalancedWeeklyMealPlanBuilder $builder, MenuDevelopmentCsvExport $csvExport): int
    {
        $this->info('Building Balanced weekly structured meal plan…');

        $result = $builder->build(refineRecipes: ! $this->option('skip-refine'));

        $plan = $result['plan'];

        if ($result['refined_meals'] !== []) {
            $this->info('Refined recipes:');
            foreach ($result['refined_meals'] as $name) {
                $this->line("  • {$name}");
            }
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Plan ID', (string) $plan->id],
                ['Plan name', $plan->name],
                ['Daily slots (option A)', (string) $result['slots']],
                ['Reference daily calories', (string) BalancedWeeklyMealPlanBuilder::REFERENCE_DAILY_CALORIES],
            ],
        );

        $this->line('');
        $this->info('Daily menu pattern (option A, slot 1 examples):');
        foreach (range(1, 7) as $day) {
            $this->line(sprintf(
                '  Day %d: %s · %s · %s',
                $day,
                BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Breakfast, 1),
                BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 1),
                BalancedWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 3),
            ));
        }

        $this->line('');
        $this->comment('Set CUSTOMER_PRODUCTION_MEAL_PLAN_ID='.$plan->id.' in .env to pin this plan for production soup scheduling.');

        if ($this->option('export-csv')) {
            $count = $csvExport->exportMealsToPath(database_path('data/menu/meals.csv'));
            $this->info("Exported {$count} meal row(s) to database/data/menu/meals.csv");
        }

        return self::SUCCESS;
    }
}
