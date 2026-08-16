<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealInstructionsText;
use App\Support\MealLibraryEditGuard;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Standardizes Balanced rotation chia breakfasts on {@see Coconut Chia Pudding (Base)}
 * with date-syrup-sweetened coconut chia and per-meal toppings only (max 200 kcal each).
 */
final class BalancedChiaBreakfastRecipeRefiner
{
    public const COCONUT_CHIA_BASE_NAME = 'Coconut Chia Pudding (Base)';

    public const COCONUT_CHIA_BASE_GRAMS = 53.0;

    public const MAX_CALORIES = 200.0;

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

                if (MealLibraryEditGuard::shouldSkipMealRefinement($meal)) {
                    continue;
                }

                $this->syncMeal(
                    $meal,
                    $definition['ingredients'],
                    $definition['instructions'],
                    $definition['diet_tags'] ?? WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
                    $definition['short_description'] ?? null,
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
    private function syncMeal(
        Meal $meal,
        array $ingredientGrams,
        array $instructionSteps,
        array $dietTags,
        ?string $shortDescription = null,
    ): void {
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

        if ((float) ($nutrition['calories'] ?? 0) > self::MAX_CALORIES + 0.5) {
            throw new InvalidArgumentException(sprintf(
                '%s exceeds %gkcal cap (%.1f kcal).',
                $meal->name,
                self::MAX_CALORIES,
                (float) $nutrition['calories'],
            ));
        }

        $instructionLines = [];

        foreach ($instructionSteps as $index => $step) {
            $instructionLines[] = ($index + 1).'. '.$step;
        }

        $updates = array_merge(
            Meal::nutritionSummaryToPersistedAttributes($nutrition),
            [
                'nutrition_aggregates_synced' => true,
                'diet_tags' => array_merge($dietTags, ['Vegan']),
                'instructions' => MealInstructionsText::normalizeForStorage(implode("\n", $instructionLines)),
            ],
        );

        if ($shortDescription !== null) {
            $updates['short_description'] = $shortDescription;
        }

        $meal->update($updates);

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);

        $violations = WholeFoodDietPolicy::violationsForMeal($meal->fresh(['ingredients']));

        if ($violations !== []) {
            throw new InvalidArgumentException(implode('; ', $violations));
        }
    }

    /**
     * @return array<string, array{ingredients: array<string, float>, instructions: list<string>, diet_tags?: list<string>, short_description?: string}>
     */
    private function recipeDefinitions(): array
    {
        $tags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;
        $base = self::COCONUT_CHIA_BASE_GRAMS;

        $basePrep = [
            'Prepare Coconut Chia Pudding (Base) ahead (chia, coconut milk, and date syrup) and chill until thick.',
            'Spoon the set pudding into a bowl or jar.',
        ];

        return [
            'Blueberry Walnut Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Blueberries' => 20,
                    'Walnuts' => 5,
                    'Fresh Mint' => 1,
                    'Cinnamon' => 1,
                ],
                'instructions' => array_merge($basePrep, [
                    'Fold in blueberries, walnuts, cinnamon, and mint.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
                'short_description' => 'Creamy coconut chia pudding with blueberries, walnuts, cinnamon, and mint.',
            ],
            'Mango Pumpkin Seed Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Mango' => 35,
                    'Pumpkin Seeds' => 5,
                ],
                'instructions' => array_merge($basePrep, [
                    'Top with diced mango and pumpkin seeds.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
                'short_description' => 'Tropical coconut chia pudding topped with fresh mango and pumpkin seeds.',
            ],
            'Spiced Crunch Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Almond whole' => 3,
                    'Black Seeds' => 2,
                    'Sesame Seeds' => 3,
                    'Cinnamon' => 1.5,
                    'Clove' => 0.5,
                    'Ground Ginger' => 1,
                ],
                'instructions' => array_merge($basePrep, [
                    'Stir cinnamon, clove, and ginger through the pudding.',
                    'Top with chopped almonds, black seeds, and sesame seeds.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
                'short_description' => 'Warming spiced coconut chia pudding topped with almonds, black seeds, and sesame.',
            ],
            'Strawberry Almond Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Strawberries' => 40,
                    'Almond whole' => 4,
                ],
                'instructions' => array_merge($basePrep, [
                    'Fold in sliced strawberries and almonds.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
                'short_description' => 'Coconut chia pudding with fresh strawberries and almonds.',
            ],
            'Peach Pecan Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Peach' => 35,
                    'Pecans' => 5,
                    'Cinnamon' => 0.5,
                    'Fresh Mint' => 2,
                ],
                'instructions' => array_merge($basePrep, [
                    'Top with sliced peach, pecans, cinnamon, and mint.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
                'short_description' => 'Coconut chia pudding with sweet peach, pecans, cinnamon, and mint.',
            ],
            'Raspberry Cacao Chia Pudding' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Raspberries' => 35,
                    'Cacao Nibs' => 4,
                    'Cocoa Powder' => 2,
                ],
                'instructions' => array_merge($basePrep, [
                    'Fold in raspberries, cacao nibs, and cocoa powder.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
                'short_description' => 'Dark cacao coconut chia pudding with raspberries and cacao nibs.',
            ],
            'Cacao & Almond Chia' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Almond Butter' => 2,
                    'Almond whole' => 5,
                    'Cocoa Powder' => 2,
                ],
                'instructions' => array_merge($basePrep, [
                    'Swirl in almond butter and cocoa powder. Top with chopped almonds.',
                    'Serve chilled.',
                ]),
                'diet_tags' => $tags,
                'short_description' => 'Rich cacao coconut chia pudding swirled with almond butter and almonds.',
            ],
            'Chia Pudding Smoothie' => [
                'ingredients' => [
                    self::COCONUT_CHIA_BASE_NAME => $base,
                    'Strawberries' => 30,
                    'Banana' => 25,
                ],
                'instructions' => [
                    'Prepare Coconut Chia Pudding (Base) ahead and chill until thick.',
                    'Spoon the set pudding into the bottom of a glass or jar.',
                    'Blend strawberries and banana until smooth.',
                    'Pour the fruit smoothie over the chia layer. Serve chilled.',
                ],
                'diet_tags' => $tags,
                'short_description' => 'Layered coconut chia pudding with a strawberry-banana smoothie top.',
            ],
        ];
    }
}
