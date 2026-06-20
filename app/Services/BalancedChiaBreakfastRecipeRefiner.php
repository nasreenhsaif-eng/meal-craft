<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealInstructionsText;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Standardizes Balanced rotation chia breakfasts on {@see Coconut Chia Pudding (Base)}
 * with date-syrup-sweetened coconut chia and per-meal toppings only.
 */
final class BalancedChiaBreakfastRecipeRefiner
{
    public const COCONUT_CHIA_BASE_NAME = 'Coconut Chia Pudding (Base)';

    public const COCONUT_CHIA_BASE_GRAMS = 100.0;

    /**
     * @return list<string>
     */
    public static function refinedMealNames(): array
    {
        return array_keys((new self)->recipeDefinitions());
    }

    /**
     * @return list<string>
     */
    public function refine(?string $onlyMealName = null): array
    {
        return DB::transaction(function () use ($onlyMealName): array {
            $updated = [];

            foreach ($this->recipeDefinitions() as $mealName => $definition) {
                if ($onlyMealName !== null && $mealName !== $onlyMealName) {
                    continue;
                }

                /** @var Meal|null $meal */
                $meal = Meal::queryForMealLibrary()->where('name', $mealName)->first();

                if ($meal === null) {
                    continue;
                }

                $this->syncMeal(
                    $meal,
                    $definition['ingredients'],
                    $definition['instructions'],
                    $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
                );
                $updated[] = $mealName;
            }

            return $updated;
        });
    }

    /**
     * @param  array<string, float>  $ingredientGrams
     * @param  list<string>  $instructionSteps
     * @param  list<string>  $dietTags
     */
    private function syncMeal(Meal $meal, array $ingredientGrams, array $instructionSteps, array $dietTags): void
    {
        $sync = [];

        foreach ($ingredientGrams as $ingredientName => $grams) {
            if ($grams <= 0) {
                continue;
            }

            if (WholeFoodDietPolicy::isBannedIngredientName($ingredientName)) {
                throw new InvalidArgumentException("Refiner attempted to use banned ingredient: {$ingredientName}");
            }

            /** @var Ingredient|null $ingredient */
            $ingredient = Ingredient::query()->where('name', $ingredientName)->first();

            if ($ingredient === null) {
                throw new InvalidArgumentException("Missing library ingredient: {$ingredientName}");
            }

            if (WholeFoodDietPolicy::isBannedIngredient($ingredient)) {
                throw new InvalidArgumentException("Refiner attempted to use banned ingredient: {$ingredientName}");
            }

            $sync[$ingredient->id] = [
                'amount_grams' => round((float) $grams, 4),
                'amount' => round((float) $grams, 4),
                'unit' => 'g',
            ];
        }

        $meal->ingredients()->sync($sync);

        $fresh = $meal->fresh(['ingredients']);
        $nutrition = RecipeNutritionCalculator::fromMeal($fresh);

        $instructionLines = [];

        foreach ($instructionSteps as $index => $step) {
            $instructionLines[] = ($index + 1).'. '.$step;
        }

        $meal->update(array_merge(
            Meal::nutritionSummaryToPersistedAttributes($nutrition),
            [
                'nutrition_aggregates_synced' => true,
                'diet_tags' => array_merge($dietTags, ['Vegan']),
                'instructions' => MealInstructionsText::normalizeForStorage(implode("\n", $instructionLines)),
            ],
        ));

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);

        $violations = WholeFoodDietPolicy::violationsForMeal($meal->fresh(['ingredients']));

        if ($violations !== []) {
            throw new InvalidArgumentException(implode('; ', $violations));
        }
    }

    /**
     * @return array<string, array{ingredients: array<string, float>, instructions: list<string>, diet_tags?: list<string>}>
     */
    private function recipeDefinitions(): array
    {
        $base = self::COCONUT_CHIA_BASE_GRAMS;
        $tags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;

        $basePrep = [
            'Prepare Coconut Chia Pudding (Base) ahead (chia, coconut milk, and date syrup) and chill until thick.',
            'Spoon the set pudding into a bowl or jar.',
        ];

        return [
            'Blueberry Walnut Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Blueberries' => 60,
                    'Walnuts' => 12,
                    'Fresh Mint' => 3,
                    'Cinnamon' => 2,
                ],
                'instructions' => array_merge($basePrep, [
                    'Fold in blueberries, walnuts, cinnamon, and mint.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
            ],
            'Mango Pumpkin Seed Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Mango' => 50,
                    'Pumpkin Seeds' => 10,
                ],
                'instructions' => array_merge($basePrep, [
                    'Top with diced mango and pumpkin seeds.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
            ],
            'Spiced Crunch Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Almond whole' => 8,
                    'Cinnamon' => 2,
                    'Clove' => 0.5,
                    'Ground Ginger' => 1,
                ],
                'instructions' => array_merge($basePrep, [
                    'Stir in cinnamon, clove, ginger, and chopped almonds.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
            ],
            'Strawberry Almond Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Strawberries' => 70,
                    'Almond whole' => 8,
                ],
                'instructions' => array_merge($basePrep, [
                    'Fold in sliced strawberries and almonds.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
            ],
            'Peach Pecan Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Peach' => 60,
                    'Pecans' => 8,
                    'Cinnamon' => 1,
                    'Fresh Mint' => 3,
                ],
                'instructions' => array_merge($basePrep, [
                    'Top with sliced peach, pecans, cinnamon, and mint.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
            ],
            'Raspberry Cacao Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Raspberries' => 70,
                    'Cacao Nibs' => 8,
                    'Cocoa Powder' => 5,
                ],
                'instructions' => array_merge($basePrep, [
                    'Fold in raspberries, cacao nibs, and cocoa powder.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
            ],
            'Cacao & Almond Chia' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Almond Butter' => 10,
                    'Almond whole' => 8,
                    'Cocoa Powder' => 5,
                ],
                'instructions' => array_merge($basePrep, [
                    'Swirl in almond butter and cocoa powder. Top with chopped almonds.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
            ],
        ];
    }
}
