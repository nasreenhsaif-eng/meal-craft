<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealInstructionsText;
use App\Support\MealLibraryEditGuard;
use App\Support\SaladMealPresentation;
use App\Support\StandardMeatPortion;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ensures every salad meal lists a dressing base ingredient separately from salad body,
 * with salad-only assembly instructions on the meal and dressing prep on the base recipe.
 */
final class SaladDressingMealRefiner
{
    public const CLASSIC_LEMON_GARLIC_DRESSING = 'Classic Lemon Garlic Dressing (Base)';

    public const MINT_COCONUT_CHUTNEY_DRESSING = 'Mint Coconut Chutney Dressing (Base)';

    public const PEANUT_BUTTER_DRESSING = 'Peanut Butter Dressing (Base)';

    public const HONEY_MUSTARD_DRESSING = 'Honey Mustard Dressing (Base)';

    public const SERVE_DRESSING_ON_THE_SIDE = 'Serve dressing on the side.';

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
                    $definition['salad_ingredients'],
                    $definition['dressing_ingredients'],
                    $definition['salad_instructions'],
                    $definition['diet_tags'] ?? null,
                    $definition['short_description'] ?? null,
                );

                $updated[] = $mealName;
            }

            return $updated;
        });
    }

    /**
     * @param  array<string, float>  $saladIngredientGrams
     * @param  array<string, float>  $dressingIngredientGrams
     * @param  list<string>  $saladInstructionSteps
     * @param  list<string>|null  $dietTags
     */
    private function syncMeal(
        Meal $meal,
        array $saladIngredientGrams,
        array $dressingIngredientGrams,
        array $saladInstructionSteps,
        ?array $dietTags = null,
        ?string $shortDescription = null,
    ): void {
        $ingredientGrams = array_merge($saladIngredientGrams, $dressingIngredientGrams);
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

        foreach ($saladInstructionSteps as $index => $step) {
            $instructionLines[] = ($index + 1).'. '.$step;
        }

        $instructions = MealInstructionsText::normalizeForStorage(implode("\n", $instructionLines));

        $update = array_merge(
            Meal::nutritionSummaryToPersistedAttributes($nutrition),
            [
                'nutrition_aggregates_synced' => true,
                'instructions' => $instructions,
            ],
        );

        if ($dietTags !== null) {
            $update['diet_tags'] = $dietTags;
        }

        if ($shortDescription !== null) {
            $update['short_description'] = $shortDescription;
        }

        $meal->update($update);

        MealRecipeAsIngredientSyncService::syncFromPersistedMeal($fresh->fresh(['ingredients']), false);

        if (! SaladMealPresentation::isSaladMeal($meal->fresh())) {
            return;
        }

        $violations = WholeFoodDietPolicy::violationsForMeal($meal->fresh(['ingredients']));

        if ($violations !== []) {
            throw new InvalidArgumentException(implode('; ', $violations));
        }
    }

    /**
     * @return array<string, array{
     *     salad_ingredients: array<string, float>,
     *     dressing_ingredients: array<string, float>,
     *     salad_instructions: list<string>,
     *     diet_tags?: list<string>
     * }>
     */
    private function recipeDefinitions(): array
    {
        $wholeFoodTags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;
        $veganTags = array_merge($wholeFoodTags, ['Vegan']);

        return [
            'Marinated Pineapple, Peppers, Red Onion & Cilantro Side Salad' => [
                'salad_ingredients' => [
                    'Pineapple' => 40,
                    'Bell Pepper (Red)' => 25,
                    'Cabbage (Purple)' => 45,
                    'Cucumber' => 35,
                    'Red Onion' => 12,
                    'Fresh Coriander' => 4,
                    'Red Thai Chillies' => 2,
                ],
                'dressing_ingredients' => [
                    'Zesty Lime Chili Salad Dressing (Base)' => 12,
                ],
                'salad_instructions' => [
                    'Dice pineapple, pepper, cucumber, and red onion.',
                    'Toss with thinly sliced cabbage and fresh coriander.',
                    'Refrigerate 15–30 minutes so the vegetables soften slightly.',
                    'Add chilli just before serving.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
            ],
            'Classic Garden Salad' => [
                'salad_ingredients' => [
                    'Romaine Lettuce' => 50,
                    'Tomato (Raw)' => 60,
                    'Cucumber' => 60,
                    'Bell Pepper (Red)' => 40,
                    'Cabbage (Purple)' => 30,
                    'Red Onion' => 25,
                ],
                'dressing_ingredients' => [
                    self::CLASSIC_LEMON_GARLIC_DRESSING => 15,
                ],
                'salad_instructions' => [
                    'Wash and chop the lettuce, tomato, cucumber, pepper, cabbage, and onion.',
                    'Toss the vegetables together in a large bowl.',
                    'Serve immediately.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
            ],
            'Tomato Parsely Salad w Sumac Za’ater Dressing' => [
                'salad_ingredients' => [
                    'Tomato (Raw)' => 150,
                    'Cucumber' => 40,
                    'Red Onion' => 13,
                    'Parsley' => 10,
                    'Fresh Mint' => 3,
                    'Rocca' => 30,
                    'Pomegranate Seeds' => 12,
                ],
                'dressing_ingredients' => [
                    'Sumac Za\'atar Dressing (Base)' => 31,
                ],
                'salad_instructions' => [
                    'Prepare Sumac Za\'atar Dressing (Base) per base recipe instructions; rest 10 minutes.',
                    'Halve or wedge the tomatoes; slice cucumber and thinly slice red onion.',
                    'Roughly chop the parsley and mint; tear rocca into bite-sized pieces.',
                    'Combine tomatoes, cucumber, onion, rocca, herbs, and pomegranate seeds in a bowl.',
                    'Serve at room temperature.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
            ],
            'Citrus Beet Arugula Salad' => [
                'salad_ingredients' => [
                    'Arugula' => 45,
                    'Beetroot' => 75,
                    'Orange Sections' => 45,
                    'Cucumber' => 30,
                    'Walnuts' => 8,
                    'Fresh Mint' => 3,
                ],
                'dressing_ingredients' => [
                    self::CLASSIC_LEMON_GARLIC_DRESSING => 12,
                ],
                'salad_instructions' => [
                    'Roast or boil beetroot until tender. Cool, peel, and slice.',
                    'Arrange arugula on plates.',
                    'Top with beets, orange segments, cucumber, walnuts, and mint.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
                'short_description' => 'Roasted beet and citrus salad with arugula, walnuts, and lemon-garlic dressing.',
            ],
            'Shaved Fennel Rocca Salad' => [
                'salad_ingredients' => [
                    'Fennel Bulb' => 65,
                    'Rocca' => 45,
                    'Orange Sections' => 40,
                    'Pomegranate Seeds' => 12,
                    'Walnuts' => 6,
                ],
                'dressing_ingredients' => [
                    self::CLASSIC_LEMON_GARLIC_DRESSING => 12,
                ],
                'salad_instructions' => [
                    'Shave fennel very thin with a mandoline or sharp knife.',
                    'Toss fennel, rocca, orange segments, pomegranate, and walnuts together.',
                    'Serve immediately.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
            ],
            'Roasted Eggplant Rocca Salad' => [
                'salad_ingredients' => [
                    'Eggplant' => 120,
                    'Cherry Tomatoes' => 50,
                    'Rocca' => 45,
                    'Pomegranate Seeds' => 15,
                ],
                'dressing_ingredients' => [
                    self::CLASSIC_LEMON_GARLIC_DRESSING => 12,
                ],
                'salad_instructions' => [
                    'Cube eggplant and roast at 200°C with a little oil until soft and golden (25 min).',
                    'Halve cherry tomatoes and toss with rocca.',
                    'Combine with warm eggplant and pomegranate seeds.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
            ],
            'Marinated Strawberry Beet Salad' => [
                'salad_ingredients' => [
                    'Strawberries' => 55,
                    'Beetroot' => 65,
                    'Romaine Lettuce' => 45,
                    'White Onion' => 15,
                    'Walnuts' => 8,
                    'Fresh Mint' => 3,
                ],
                'dressing_ingredients' => [
                    'Apple Cider Beet Marinade (Base)' => 14,
                ],
                'salad_instructions' => [
                    'Cook beetroot until tender. Cool and dice.',
                    'Slice strawberries and onion.',
                    'Toss beets, strawberries, onion, walnuts, and mint. Marinate 20 minutes.',
                    'Serve over romaine.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
            ],
            'Coconut Grapefruit Salad' => [
                'salad_ingredients' => [
                    'Romaine Lettuce' => 50,
                    'Grapefruit Sections' => 65,
                    'Cucumber' => 45,
                    'Coconut Meat' => 12,
                    'Red Onion' => 10,
                    'Fresh Mint' => 4,
                    'Pomegranate Seeds' => 12,
                ],
                'dressing_ingredients' => [
                    'Grapefruit Lime Dressing (Base)' => 13,
                ],
                'salad_instructions' => [
                    'Segment grapefruit and slice cucumber and red onion.',
                    'Toss romaine with grapefruit, cucumber, onion, mint, and pomegranate.',
                    'Top with coconut and serve.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
            ],
            'Rosemary Chicken Rocca Salad' => [
                'salad_ingredients' => [
                    'Rosemary Garlic Chicken (Base)' => StandardMeatPortion::GRAMS,
                    'Rocca' => 50,
                    'Cucumber' => 55,
                    'Roasted Cherry Tomato (Base)' => 45,
                    'Rosemary (Fresh)' => 2,
                    'Garlic (Raw)' => 3,
                    'Walnuts' => 6,
                ],
                'dressing_ingredients' => [
                    'Red Pepper Dressing (Base)' => 20,
                ],
                'salad_instructions' => [
                    'Grill or pan-sear Rosemary Garlic Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                    'Toss rocca, cucumber, and cherry tomatoes in a bowl.',
                    'Top with chicken and walnuts.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            'Turmeric Chicken Kale Salad' => [
                'salad_ingredients' => [
                    'Chicken Breast' => StandardMeatPortion::GRAMS,
                    'Kale' => 50,
                    'Carrots' => 40,
                    'Cucumber' => 40,
                    'Cherry Tomatoes' => 35,
                    'Red Onion' => 15,
                    'Pomegranate Seeds' => 12,
                    'Garlic (Raw)' => 2,
                ],
                'dressing_ingredients' => [
                    'Turmeric Lemon Dressing (Base)' => 14,
                ],
                'salad_instructions' => [
                    'Rub chicken with half the turmeric dressing as a marinade. Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                    'Massage kale until tender.',
                    'Add cucumber, carrots, tomatoes, onion, and pomegranate. Top with warm chicken.',
                    'Serve remaining dressing on the side.',
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            'Chicken Thai Mango Salad' => [
                'salad_ingredients' => [
                    'Chicken Breast' => StandardMeatPortion::GRAMS,
                    'Cabbage (Purple)' => 75,
                    'Cucumber' => 45,
                    'Mango' => 55,
                    'Cherry Tomatoes' => 40,
                    'Red Onion' => 15,
                    'Fresh Coriander' => 5,
                    'Peanuts (Crushed)' => 8,
                ],
                'dressing_ingredients' => [
                    self::PEANUT_BUTTER_DRESSING => 25,
                ],
                'salad_instructions' => [
                    'Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice thinly.',
                    'Shred cabbage; slice mango, cucumber, tomatoes, and red onion.',
                    'Toss vegetables with coriander.',
                    'Top with chicken and crushed peanuts.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            'Mediterranean Crunch Salad' => [
                'salad_ingredients' => [
                    'Rosemary Garlic Chicken (Base)' => StandardMeatPortion::GRAMS,
                    'Romaine Lettuce' => 45,
                    'Rocca' => 45,
                    'Cucumber' => 55,
                    'Cherry Tomatoes' => 45,
                    'Bell Pepper (Red)' => 35,
                    'Red Onion' => 15,
                    'Fresh Basil' => 3,
                    'Kalamata Olives' => 15,
                    'Walnuts' => 10,
                    'Pumpkin Seeds' => 10,
                ],
                'dressing_ingredients' => [
                    self::CLASSIC_LEMON_GARLIC_DRESSING => 12,
                ],
                'salad_instructions' => [
                    'Dice cucumber, cherry tomatoes, red pepper, and red onion.',
                    'Grill or pan-sear Rosemary Garlic Chicken (Base) until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                    'Toss romaine, rocca, vegetables, basil, olives, walnuts, and pumpkin seeds. Top with chicken.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            'Blackened Chicken, Grilled Peppers & Onion Salad w Quinoa, Kale & Mustard Dressing' => [
                'salad_ingredients' => [
                    'Chicken Breast' => StandardMeatPortion::GRAMS,
                    'Cooked Quinoa (Base)' => 84,
                    'Kale' => 40,
                    'Bell Pepper (Red)' => 40,
                    'Red Onion' => 20,
                    'Blackened Seasoning (Base)' => 5,
                ],
                'dressing_ingredients' => [
                    self::HONEY_MUSTARD_DRESSING => 15,
                ],
                'salad_instructions' => [
                    'Prepare Cooked Quinoa (Base) per base recipe instructions; let cool slightly.',
                    'Rub chicken with Blackened Seasoning (Base). Grill or pan-sear chicken until golden then in the oven for 20 minutes exactly, then Rest and slice.',
                    'Grill pepper strips and onion until charred and soft.',
                    'Massage kale until tender.',
                    'Toss quinoa, kale, and vegetables. Top with chicken.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $wholeFoodTags,
            ],
            'Vegan Curry Lentil Salad' => [
                'salad_ingredients' => [
                    'French Lentils' => 60,
                    'Spinach (Fresh)' => 40,
                    'Carrots' => 40,
                    'Bell Pepper (Red)' => 35,
                    'Cucumber' => 35,
                    'Red Onion' => 15,
                    'Fresh Coriander' => 4,
                    'Wild Rice (Cooked)' => 75,
                ],
                'dressing_ingredients' => [
                    'Curry Vinaigrette (Base)' => 15,
                ],
                'salad_instructions' => [
                    'Cook lentils until tender but not mushy. Drain and cool.',
                    'Cook wild rice if needed. Cool slightly.',
                    'Toss lentils, rice, spinach, carrots, pepper, cucumber, onion, and coriander.',
                    'Serve at room temperature or chilled.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
            ],
            'Spiced Cauliflower Chickpea Salad' => [
                'salad_ingredients' => [
                    'Cauliflower Florets' => 95,
                    'Cooked Chickpeas (Base)' => 50,
                    'Romaine Lettuce' => 40,
                    'Cherry Tomatoes' => 35,
                    'Red Onion' => 15,
                    'Cumin Seeds' => 2,
                    'Smoked Paprika' => 1,
                ],
                'dressing_ingredients' => [
                    'Spiced Lemon Dressing (Base)' => 12,
                ],
                'salad_instructions' => [
                    'Prepare Cooked Chickpeas (Base) per base recipe instructions.',
                    'Toss cauliflower with cumin, paprika, and half the dressing.',
                    'Roast at 200°C for 22 minutes. Add cooked chickpeas for the last 10 minutes.',
                    'Toss roasted vegetables with tomatoes and red onion.',
                    'Serve over romaine.',
                    'Serve remaining dressing on the side.',
                ],
                'diet_tags' => $veganTags,
            ],
            'Thai Rainbow Peanut Salad' => [
                'salad_ingredients' => [
                    'Cabbage (Purple)' => 55,
                    'Carrots' => 40,
                    'Cucumber' => 40,
                    'Bell Pepper (Red)' => 30,
                    'Red Onion' => 12,
                    'Fresh Coriander' => 5,
                    'Peanuts (Crushed)' => 8,
                ],
                'dressing_ingredients' => [
                    self::PEANUT_BUTTER_DRESSING => 25,
                ],
                'salad_instructions' => [
                    'Shred cabbage and julienne carrots, cucumber, and red onion.',
                    'Toss vegetables with coriander and crushed peanuts.',
                    'Serve chilled.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
            ],
            'Vegan Harissa Roasted Cauliflower & Chickpea Salad w Tahini Dressing' => [
                'salad_ingredients' => [
                    'Cauliflower' => 150,
                    'Beetroot' => 100,
                    'Cooked Chickpeas (Base)' => 75,
                    'Shallots' => 20,
                    'Harissa Paste (Base)' => 5,
                    'Olive Oil (Extra Virgin)' => 5,
                    'Fresh Mint' => 3,
                    'Dill (Fresh)' => 2,
                    'Sunflower Seeds' => 10,
                    'Black Seeds' => 5,
                ],
                'dressing_ingredients' => [
                    'Lemon-Tahini Dressing (Base)' => 35,
                ],
                'salad_instructions' => [
                    'Prepare Cooked Chickpeas (Base) per base recipe instructions.',
                    'Toss cauliflower, beetroot, and chickpeas with Harissa Paste (Base) and olive oil.',
                    'Roast at 200°C for 25 minutes until crisp and charred at the edges.',
                    'Toss roasted vegetables with shallots, dill, mint, sunflower seeds, and black seeds.',
                    'Serve warm or at room temperature.',
                    self::SERVE_DRESSING_ON_THE_SIDE,
                ],
                'diet_tags' => $veganTags,
            ],
        ];
    }
}
