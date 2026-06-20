<?php

namespace App\Services;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use Illuminate\Support\Facades\DB;

/**
 * Curates the meal library for the Balanced protocol customer deck:
 * 2 breakfasts, 4 mains, 2 side salads, 2 desserts, 2 soups (same choices for all customers; portions scale by tier).
 */
final class BalancedMealLibraryConfigurator
{
    public const BONE_BROTH_MEAL_NAME = 'Bone Broth Cup';

    public const NON_CANONICAL_SORT_BASE = 100;

    /**
     * Canonical deck slots in global {@see Meal::$library_sort_order} (lower = shown first in consultation decks).
     *
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
                'name' => 'Blueberry Walnut Chia Pudding',
                'sort' => 0,
                'slot' => 'breakfast',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Mediterranean Omelet',
                'sort' => 1,
                'slot' => 'breakfast',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Vegetarian', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Tamarind Honey & Sesame Chicken w Garlicky Green Beans',
                'sort' => 2,
                'slot' => 'main_chicken_plate',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => BalancedCanonicalMealRecipeRefiner::ROSEMARY_GARLIC_CHICKEN_PLATE_NAME,
                'sort' => 3,
                'slot' => 'main_chicken_plate',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => BalancedCanonicalMealRecipeRefiner::BAKED_SALMON_NAME,
                'sort' => 4,
                'slot' => 'main_salmon',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Vegan Butternut Squash, Lentil & Nut Stew w Brown Rice',
                'sort' => 5,
                'slot' => 'main_vegan',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad',
                'sort' => 6,
                'slot' => 'side_salad',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Classic Garden Salad',
                'sort' => 7,
                'slot' => 'side_salad_classic',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => BalancedCanonicalMealRecipeRefiner::CARROT_DESSERT_NAME,
                'sort' => 8,
                'slot' => 'dessert',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Vegetarian', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Fruit Salad Bowl',
                'sort' => 9,
                'slot' => 'dessert_fruit',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => 'Vegan Mushroom Soup',
                'sort' => 10,
                'slot' => 'soup_vegan',
                'meal_plan_tags' => ['Balanced'],
                'diet_tags' => ['Vegan', 'Dairy-free', 'Gluten-free'],
            ],
            [
                'name' => self::BONE_BROTH_MEAL_NAME,
                'sort' => 11,
                'slot' => 'soup_bone_broth',
                'meal_plan_tags' => ['Balanced'],
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
                    continue;
                }

                /** @var Meal|null $meal */
                $meal = Meal::queryForMealLibrary()->where('name', $slot['name'])->first();

                if ($meal === null) {
                    continue;
                }

                $meal->update([
                    'library_sort_order' => $slot['sort'],
                    'meal_plan_tags' => $slot['meal_plan_tags'],
                    'meal_plan_tag' => $slot['meal_plan_tags'][0] ?? 'Balanced',
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
            return false;
        }

        /** @var Ingredient|null $broth */
        $broth = Ingredient::query()->where('name', 'Bone Broth (Base)')->first();

        if ($broth === null) {
            return false;
        }

        $portionGrams = 240.0;

        $meal = Meal::query()->create([
            'name' => self::BONE_BROTH_MEAL_NAME,
            'category' => RecipeCategory::Soup,
            'meal_type' => MealType::Soup,
            'short_description' => 'Long-simmered gelatin-rich bone broth — warming protein-rich add-on.',
            'instructions' => 'Heat gently and serve in a mug or bowl.',
            'meal_plan_tags' => ['Balanced'],
            'meal_plan_tag' => 'Balanced',
            'diet_tags' => ['Dairy-free', 'Gluten-free'],
            'library_sort_order' => 11,
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
