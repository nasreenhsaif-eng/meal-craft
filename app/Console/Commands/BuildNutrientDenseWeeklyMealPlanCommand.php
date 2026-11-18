<?php

namespace App\Console\Commands;

use App\Enums\MealPlanSlotType;
use App\Services\MenuDevelopmentCsvExport;
use App\Services\NutrientDenseWeeklyMealPlanBuilder;
use App\Services\NutrientDenseWeeklyRotationSchedule;
use Illuminate\Console\Command;

class BuildNutrientDenseWeeklyMealPlanCommand extends Command
{
    protected $signature = 'meal-plan:build-nutrient-dense
                            {--skip-refine : Do not rewrite canonical meal recipes}
                            {--audit-only : Run micronutrient audit without building}
                            {--export-csv : Write database/data/menu/meals.csv after building}';

    protected $description = 'Refine nutrient-dense meals and build the 7-day production weekly structured plan';

    public function handle(NutrientDenseWeeklyMealPlanBuilder $builder, MenuDevelopmentCsvExport $csvExport): int
    {
        $this->info('Building Nutrient Density weekly structured meal plan…');

        $result = $builder->build(
            refineRecipes: ! $this->option('skip-refine'),
            auditOnly: (bool) $this->option('audit-only'),
        );

        if ($this->option('audit-only')) {
            $this->line('Audit passed: '.($result['audit_passed'] ? 'yes' : 'no'));
            $this->line('Audit failures: '.$result['audit_failures']);

            return $result['audit_passed'] ? self::SUCCESS : self::FAILURE;
        }

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
                ['Reference daily calories', (string) NutrientDenseWeeklyMealPlanBuilder::REFERENCE_DAILY_CALORIES],
                ['Audit passed', $result['audit_passed'] ? 'yes' : 'no'],
            ],
        );

        foreach (range(1, 7) as $day) {
            $this->line(sprintf(
                '  Day %d: %s · fish %s · fermented %s',
                $day,
                NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Breakfast, 1),
                NutrientDenseWeeklyRotationSchedule::mealNameForDay($day, MealPlanSlotType::Main, 3),
                NutrientDenseWeeklyRotationSchedule::fermentedAnchorForDay($day),
            ));
        }

        $this->comment('Set CUSTOMER_NUTRIENT_DENSE_MEAL_PLAN_ID='.$plan->id.' in .env for nutrient-dense production scheduling.');

        if ($this->option('export-csv')) {
            $count = $csvExport->exportMealsToPath(database_path('data/menu/meals.csv'));
            $this->info("Exported {$count} meal row(s) to database/data/menu/meals.csv");
        }

        return self::SUCCESS;
    }
}
