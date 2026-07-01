<?php

namespace App\Services;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use Illuminate\Support\Facades\DB;

/**
 * Curates the meal library for the Nutrient Density protocol customer deck.
 */
final class NutrientDenseMealLibraryConfigurator
{
    public const BONE_BROTH_MEAL_NAME = 'Bone Broth Cup';

    public const BONE_BROTH_SERVING_GRAMS = 500.0;

    public const NON_CANONICAL_SORT_BASE = 100;

    /**
     * @return list<array{
     *     name: string,
     *     sort: int,
     *     slot: string,
     *     meal_plan_tags: list<string>,
     *     diet_tags: list<string>
     * }>
     */
    public static function canonicalSlots(): array
    {
        return [
            [
                'name' => 'Kefir Herb Egg Bowl',
                'sort' => 0,
                'slot' => 'breakfast',
                'meal_plan_tags' => ['NutrientDense'],
                'diet_tags' => ['Vegetarian', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME,
                'sort' => 1,
                'slot' => 'main_fish',
                'meal_plan_tags' => ['NutrientDense'],
                'diet_tags' => ['Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => NutrientDenseFermentedRecipeRefiner::SARDINE_MAIN_NAME,
                'sort' => 2,
                'slot' => 'main_fish',
                'meal_plan_tags' => ['NutrientDense'],
                'diet_tags' => ['Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Kimchi Purslane Side Salad',
                'sort' => 3,
                'slot' => 'side_salad',
                'meal_plan_tags' => ['NutrientDense'],
                'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Classic Garden Salad',
                'sort' => 4,
                'slot' => 'side_salad_classic',
                'meal_plan_tags' => ['NutrientDense'],
                'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Blueberry Walnut Greek Yogurt Chia Pudding',
                'sort' => 5,
                'slot' => 'dessert',
                'meal_plan_tags' => ['NutrientDense'],
                'diet_tags' => ['Vegetarian', 'Gluten-free'],
            ],
            [
                'name' => 'Fruit Salad Bowl',
                'sort' => 6,
                'slot' => 'dessert_fruit',
                'meal_plan_tags' => ['NutrientDense'],
                'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Miso Mushroom Soup',
                'sort' => 7,
                'slot' => 'soup_miso',
                'meal_plan_tags' => ['NutrientDense'],
                'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => self::BONE_BROTH_MEAL_NAME,
                'sort' => 8,
                'slot' => 'soup_bone_broth',
                'meal_plan_tags' => ['NutrientDense'],
                'diet_tags' => ['Dairy-free', 'Gluten-free'],
            ],
        ];
    }

    /**
     * @return array{canonical: int, demoted: int, bone_broth_created: bool}
     */
    public function configure(): array
    {
        return DB::transaction(function (): array {
            $boneBrothCreated = $this->ensureBoneBrothMealExists();

            $canonicalNames = [];
            $updated = 0;

            foreach (self::canonicalSlots() as $slot) {
                if ($slot['name'] === self::BONE_BROTH_MEAL_NAME && ! $boneBrothCreated) {
                    $meal = Meal::queryForMealLibrary()->where('name', self::BONE_BROTH_MEAL_NAME)->first();
                } else {
                    $meal = Meal::queryForMealLibrary()->where('name', $slot['name'])->first();
                }

                if ($meal === null) {
                    continue;
                }

                $meal->update([
                    'library_sort_order' => $slot['sort'],
                    'meal_plan_tags' => $slot['meal_plan_tags'],
                    'meal_plan_tag' => $slot['meal_plan_tags'][0] ?? 'NutrientDense',
                    'diet_tags' => $slot['diet_tags'] === [] ? null : $slot['diet_tags'],
                ]);

                $canonicalNames[] = $slot['name'];
                $updated++;
            }

            $demoted = 0;
            $sort = self::NON_CANONICAL_SORT_BASE;

            Meal::queryForMealLibrary()
                ->whereNotIn('name', $canonicalNames)
                ->orderBy('library_sort_order')
                ->orderBy('id')
                ->each(function (Meal $meal) use (&$sort, &$demoted): void {
                    $meal->update(['library_sort_order' => $sort]);
                    $sort++;
                    $demoted++;
                });

            return [
                'canonical' => $updated,
                'demoted' => $demoted,
                'bone_broth_created' => $boneBrothCreated,
            ];
        });
    }

    /**
     * @return list<string>
     */
    public static function canonicalMealNames(): array
    {
        return array_map(
            static fn (array $slot): string => $slot['name'],
            self::canonicalSlots(),
        );
    }

    private function ensureBoneBrothMealExists(): bool
    {
        $existing = Meal::queryForMealLibrary()->where('name', self::BONE_BROTH_MEAL_NAME)->first();

        if ($existing !== null) {
            $existing->update([
                'meal_plan_tags' => array_values(array_unique(array_merge(
                    is_array($existing->meal_plan_tags) ? $existing->meal_plan_tags : [],
                    ['NutrientDense'],
                ))),
            ]);

            return false;
        }

        /** @var Ingredient|null $broth */
        $broth = Ingredient::query()->where('name', 'Bone Broth (Base)')->first();

        if ($broth === null) {
            return false;
        }

        $portionGrams = self::BONE_BROTH_SERVING_GRAMS;

        $meal = Meal::query()->create([
            'name' => self::BONE_BROTH_MEAL_NAME,
            'category' => RecipeCategory::Soup,
            'meal_type' => MealType::Soup,
            'short_description' => '500 ml cup of defatted house bone broth — mineral anchor.',
            'instructions' => 'Heat gently and serve in a mug or bowl.',
            'meal_plan_tags' => ['Balanced', 'NutrientDense'],
            'meal_plan_tag' => 'NutrientDense',
            'diet_tags' => ['Dairy-free', 'Gluten-free'],
            'library_sort_order' => 12,
            'nutrition_aggregates_synced' => true,
        ]);

        $meal->ingredients()->sync([
            $broth->id => [
                'amount_grams' => $portionGrams,
                'amount' => $portionGrams,
                'unit' => 'g',
            ],
        ]);

        $nutrition = RecipeNutritionCalculator::fromMeal($meal->fresh(['ingredients']));
        $meal->update(Meal::nutritionSummaryToPersistedAttributes($nutrition));

        return true;
    }
}
