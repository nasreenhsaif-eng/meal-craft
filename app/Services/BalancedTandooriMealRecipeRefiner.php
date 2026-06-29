<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealInstructionsText;
use App\Support\MealLibraryEditGuard;
use App\Support\StandardMeatPortion;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ensures tandoori / tikka meals use {@see Tandoori Spice Mix (Base)} via prepared protein bases.
 */
final class BalancedTandooriMealRecipeRefiner
{
    public const TANDOORI_SPICE_MIX = 'Tandoori Spice Mix (Base)';

    public const TANDOORI_CHICKEN_BASE = 'Tandoori Chicken (Base)';

    public const TANDOORI_SALMON_BASE = 'Tandoori Salmon (Base)';

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
                'diet_tags' => $dietTags,
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
        $tags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;
        $spicyTags = array_merge($tags, ['Spicy']);

        return [
            'Tandoori Chicken Salad' => [
                'ingredients' => [
                    self::TANDOORI_CHICKEN_BASE => StandardMeatPortion::GRAMS,
                    'Romaine Lettuce' => 55,
                    'Cucumber' => 45,
                    'Cherry Tomatoes' => 45,
                    'Celery' => 20,
                    'Red Onion' => 12,
                    'Fresh Coriander' => 4,
                    'Fresh Mint' => 4,
                    'Pomegranate Seeds' => 12,
                    'Cashew Nuts' => 10,
                    'Mint Coconut Chutney Dressing (Base)' => 16,
                ],
                'instructions' => [
                    'Grill or pan-sear Tandoori Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                    'Toss romaine, cucumber, celery, tomatoes, onion, herbs, and pomegranate.',
                    'Top with chicken and cashews.',
                    SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $spicyTags,
            ],
            'Tandoori Coconut Mint Salad' => [
                'ingredients' => [
                    self::TANDOORI_CHICKEN_BASE => StandardMeatPortion::GRAMS,
                    'Romaine Lettuce' => 50,
                    'Cucumber' => 45,
                    'Cherry Tomatoes' => 45,
                    'Celery' => 25,
                    'Red Onion' => 12,
                    'Fresh Mint' => 8,
                    'Fresh Coriander' => 5,
                    'Pomegranate Seeds' => 15,
                    'Cashew Nuts' => 12,
                    'Black Seeds' => 1,
                    'Mint Coconut Chutney Dressing (Base)' => 18,
                ],
                'instructions' => [
                    'Grill or pan-sear Tandoori Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                    'Toss romaine, cucumber, celery, tomatoes, onion, mint, and coriander.',
                    'Top with chicken, cashews, and pomegranate. Finish with a pinch of black seeds.',
                    SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $spicyTags,
            ],
            'Grilled Chicken Tikka bowl w Quinoa & Mint Sauce' => [
                'ingredients' => [
                    self::TANDOORI_CHICKEN_BASE => StandardMeatPortion::GRAMS,
                    'Cooked Quinoa (Base)' => 84,
                    'Cabbage (Purple)' => 30,
                    'Carrots' => 20,
                    'Cucumber' => 30,
                    'Tomato (Raw)' => 60,
                    'Red Onion' => 15,
                    'White Onion' => 40,
                    'Fresh Coriander' => 5,
                    'Fresh Mint' => 5,
                    'Cilantro Lime Dressing (Base)' => 15,
                    'Cashew Nuts' => 10,
                    'Lime Juice' => 5,
                    'Apple Cider Vinegar' => 5,
                ],
                'instructions' => [
                    'Prepare Cooked Quinoa (Base) per base recipe instructions.',
                    'Grill or pan-sear Tandoori Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                    'Shred cabbage; julienne carrots and cucumber.',
                    'Layer quinoa, vegetables, and chicken.',
                    SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $spicyTags,
            ],
            'Grilled Chicken Tikka Salad w Quinoa & Cilantro Lime Dressing' => [
                'ingredients' => [
                    self::TANDOORI_CHICKEN_BASE => StandardMeatPortion::GRAMS,
                    'Cooked Quinoa (Base)' => 84,
                    'Cabbage (Purple)' => 30,
                    'Carrots' => 20,
                    'Cucumber' => 30,
                    'Tomato (Raw)' => 60,
                    'Red Onion' => 15,
                    'White Onion' => 40,
                    'Fresh Coriander' => 5,
                    'Fresh Mint' => 5,
                    'Cilantro Lime Dressing (Base)' => 15,
                    'Cashew Nuts' => 10,
                    'Lime Juice' => 5,
                    'Apple Cider Vinegar' => 5,
                ],
                'instructions' => [
                    'Prepare Cooked Quinoa (Base) per base recipe instructions.',
                    'Grill or pan-sear Tandoori Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                    'Shred cabbage; julienne carrots and cucumber.',
                    'Layer quinoa, vegetables, and chicken.',
                    SaladDressingMealRefiner::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $spicyTags,
            ],
        ];
    }
}
