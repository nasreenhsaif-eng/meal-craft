<?php

namespace App\Services;

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Models\Ingredient;
use App\Models\Meal;
use App\Support\MealInstructionsText;
use App\Support\MealLibraryEditGuard;
use App\Support\MealLibraryRefinerOverrides;
use App\Support\WholeFoodDietPolicy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Standardizes Balanced rotation chia desserts on {@see Coconut Chia Pudding (Base)}
 * or {@see Greek Yogurt Chia Pudding (Base)} with fixed kitchen portions (~300+ kcal).
 */
final class BalancedChiaDessertRecipeRefiner
{
    public const COCONUT_CHIA_BASE_NAME = 'Coconut Chia Pudding (Base)';

    public const COCONUT_CHIA_BASE_GRAMS = 120.0;

    public const GREEK_YOGURT_CHIA_BASE_NAME = 'Greek Yogurt Chia Pudding (Base)';

    public const GREEK_YOGURT_CHIA_BASE_GRAMS = 150.0;

    public const MIN_CALORIES = 280.0;

    public const GREEK_YOGURT_CHIA_MIN_CALORIES = 160.0;

    public const GREEK_YOGURT_CHIA_PSYLLIUM_HUSK_GRAMS = 10.0;

    public const PSYLLIUM_HUSKS_NAME = 'Psyllium Husks';

    /**
     * @return list<string>
     */
    public static function refinedMealNames(): array
    {
        return array_keys((new self)->recipeDefinitions());
    }

    public static function canonicalBaseGramsForIngredientName(string $ingredientName): ?float
    {
        return match ($ingredientName) {
            self::COCONUT_CHIA_BASE_NAME => self::COCONUT_CHIA_BASE_GRAMS,
            self::GREEK_YOGURT_CHIA_BASE_NAME => self::GREEK_YOGURT_CHIA_BASE_GRAMS,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public static function greekYogurtVariantMealNames(): array
    {
        $names = [];

        foreach (self::refinedMealNames() as $mealName) {
            if (self::isGreekYogurtVariantMealName($mealName)) {
                $names[] = $mealName;
            }
        }

        return $names;
    }

    public static function isGreekYogurtVariantMealName(string $mealName): bool
    {
        return str_contains($mealName, 'Greek Yogurt');
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
                    (bool) ($definition['is_vegan'] ?? true),
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
        bool $isVegan = true,
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
        $calories = (float) ($nutrition['calories'] ?? 0);

        $minCalories = array_key_exists(self::GREEK_YOGURT_CHIA_BASE_NAME, $ingredientGrams)
            ? self::GREEK_YOGURT_CHIA_MIN_CALORIES
            : self::MIN_CALORIES;

        if ($calories < $minCalories - 0.5) {
            throw new InvalidArgumentException(sprintf(
                '%s is below %gkcal minimum (%.1f kcal).',
                $meal->name,
                $minCalories,
                $calories,
            ));
        }

        $instructionLines = [];

        foreach ($instructionSteps as $index => $step) {
            $instructionLines[] = ($index + 1).'. '.$step;
        }

        $updates = array_merge(
            Meal::nutritionSummaryToPersistedAttributes($nutrition),
            [
                'meal_type' => MealType::Dessert,
                'category' => RecipeCategory::Dessert,
                'nutrition_aggregates_synced' => true,
                'diet_tags' => array_merge($dietTags, $isVegan ? ['Vegan'] : ['Vegetarian']),
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
     * @return array<string, array{ingredients: array<string, float>, instructions: list<string>, diet_tags?: list<string>, short_description?: string, is_vegan?: bool}>
     */
    private function recipeDefinitions(): array
    {
        $definitions = [];

        foreach ($this->flavorDefinitions() as $coconutMealName => $flavor) {
            $definitions[$coconutMealName] = $this->buildVariantDefinition(
                $flavor,
                self::COCONUT_CHIA_BASE_NAME,
                self::COCONUT_CHIA_BASE_GRAMS,
                true,
                $flavor['short_description_coconut'],
            );

            $definitions[$this->greekYogurtVariantName($coconutMealName)] = $this->buildVariantDefinition(
                $flavor,
                self::GREEK_YOGURT_CHIA_BASE_NAME,
                self::GREEK_YOGURT_CHIA_BASE_GRAMS,
                false,
                $flavor['short_description_greek'],
            );
        }

        return MealLibraryRefinerOverrides::mergeRecipeDefinitionMap($definitions);
    }

    /**
     * @param  array{
     *     toppings: array<string, float>,
     *     instruction_suffix?: list<string>,
     *     custom_instructions?: list<string>,
     *     short_description_coconut: string,
     *     short_description_greek: string
     * }  $flavor
     * @return array{ingredients: array<string, float>, instructions: list<string>, diet_tags: list<string>, short_description: string, is_vegan: bool}
     */
    private function buildVariantDefinition(
        array $flavor,
        string $baseName,
        float $baseGrams,
        bool $isVegan,
        string $shortDescription,
    ): array {
        $basePrep = $this->basePrepSteps($baseName);

        if (isset($flavor['custom_instructions'])) {
            $instructions = $this->instructionsForBase($flavor['custom_instructions'], $baseName);
        } else {
            $instructions = array_merge($basePrep, $flavor['instruction_suffix'] ?? []);
        }

        $ingredients = array_merge([$baseName => $baseGrams], $flavor['toppings']);

        if ($baseName === self::GREEK_YOGURT_CHIA_BASE_NAME) {
            $ingredients[self::PSYLLIUM_HUSKS_NAME] = self::GREEK_YOGURT_CHIA_PSYLLIUM_HUSK_GRAMS;
        }

        return [
            'ingredients' => $ingredients,
            'instructions' => $instructions,
            'diet_tags' => WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS,
            'short_description' => $shortDescription,
            'is_vegan' => $isVegan,
        ];
    }

    /**
     * @return list<string>
     */
    private function basePrepSteps(string $baseName): array
    {
        if ($baseName === self::GREEK_YOGURT_CHIA_BASE_NAME) {
            return [
                'Prepare Greek Yogurt Chia Pudding (Base) ahead (chia, Greek yogurt, and honey) and chill until thick.',
                'Spoon the set pudding into a bowl or jar.',
            ];
        }

        return [
            'Prepare Coconut Chia Pudding (Base) ahead (chia, coconut milk, and date syrup) and chill until thick.',
            'Spoon the set pudding into a bowl or jar.',
        ];
    }

    /**
     * @param  list<string>  $steps
     * @return list<string>
     */
    private function instructionsForBase(array $steps, string $baseName): array
    {
        if ($baseName === self::COCONUT_CHIA_BASE_NAME) {
            return $steps;
        }

        return array_map(
            static fn (string $step): string => str_replace(
                [
                    'Prepare Coconut Chia Pudding (Base) ahead and chill until thick.',
                    'Coconut Chia Pudding (Base)',
                ],
                [
                    'Prepare Greek Yogurt Chia Pudding (Base) ahead and chill until thick.',
                    'Greek Yogurt Chia Pudding (Base)',
                ],
                $step,
            ),
            $steps,
        );
    }

    private function greekYogurtVariantName(string $coconutMealName): string
    {
        if ($coconutMealName === 'Chia Pudding Smoothie') {
            return 'Greek Yogurt Chia Pudding Smoothie';
        }

        if (str_contains($coconutMealName, 'Chia Pudding')) {
            return str_replace('Chia Pudding', 'Greek Yogurt Chia Pudding', $coconutMealName);
        }

        return str_replace(' Chia', ' Greek Yogurt Chia', $coconutMealName);
    }

    /**
     * @return array<string, array{
     *     toppings: array<string, float>,
     *     instruction_suffix?: list<string>,
     *     custom_instructions?: list<string>,
     *     short_description_coconut: string,
     *     short_description_greek: string
     * }>
     */
    private function flavorDefinitions(): array
    {
        return [
            'Blueberry Walnut Chia Pudding' => [
                'toppings' => [
                    'Blueberries' => 20,
                    'Walnuts' => 8,
                    'Fresh Mint' => 1,
                    'Cinnamon' => 1,
                ],
                'instruction_suffix' => [
                    'Fold in blueberries, walnuts, cinnamon, and mint.',
                    'Serve chilled.',
                ],
                'short_description_coconut' => 'Creamy coconut chia pudding with blueberries, walnuts, cinnamon, and mint.',
                'short_description_greek' => 'Creamy Greek yogurt chia pudding with blueberries, walnuts, cinnamon, and mint.',
            ],
            'Mango Pumpkin Seed Chia Pudding' => [
                'toppings' => [
                    'Mango' => 35,
                    'Pumpkin Seeds' => 10,
                ],
                'instruction_suffix' => [
                    'Top with diced mango and pumpkin seeds.',
                    'Serve chilled.',
                ],
                'short_description_coconut' => 'Tropical coconut chia pudding topped with fresh mango and pumpkin seeds.',
                'short_description_greek' => 'Tropical Greek yogurt chia pudding topped with fresh mango and pumpkin seeds.',
            ],
            'Spiced Crunch Chia Pudding' => [
                'toppings' => [
                    'Almond whole' => 6,
                    'Black Seeds' => 2,
                    'Sesame Seeds' => 3,
                    'Cinnamon' => 1.5,
                    'Clove' => 0.5,
                    'Ground Ginger' => 1,
                ],
                'instruction_suffix' => [
                    'Stir cinnamon, clove, and ginger through the pudding.',
                    'Top with chopped almonds, black seeds, and sesame seeds.',
                    'Serve chilled.',
                ],
                'short_description_coconut' => 'Warming spiced coconut chia pudding topped with almonds, black seeds, and sesame.',
                'short_description_greek' => 'Warming spiced Greek yogurt chia pudding topped with almonds, black seeds, and sesame.',
            ],
            'Strawberry Almond Chia Pudding' => [
                'toppings' => [
                    'Strawberries' => 40,
                    'Almond whole' => 7,
                ],
                'instruction_suffix' => [
                    'Fold in sliced strawberries and almonds.',
                    'Serve chilled.',
                ],
                'short_description_coconut' => 'Coconut chia pudding with fresh strawberries and almonds.',
                'short_description_greek' => 'Greek yogurt chia pudding with fresh strawberries and almonds.',
            ],
            'Peach Pecan Chia Pudding' => [
                'toppings' => [
                    'Peach' => 35,
                    'Pecans' => 8,
                    'Cinnamon' => 0.5,
                    'Fresh Mint' => 2,
                ],
                'instruction_suffix' => [
                    'Top with sliced peach, pecans, cinnamon, and mint.',
                    'Serve chilled.',
                ],
                'short_description_coconut' => 'Coconut chia pudding with sweet peach, pecans, cinnamon, and mint.',
                'short_description_greek' => 'Greek yogurt chia pudding with sweet peach, pecans, cinnamon, and mint.',
            ],
            'Raspberry Cacao Chia Pudding' => [
                'toppings' => [
                    'Raspberries' => 35,
                    'Cacao Nibs' => 4,
                    'Cocoa Powder' => 2,
                ],
                'instruction_suffix' => [
                    'Fold in raspberries, cacao nibs, and cocoa powder.',
                    'Serve chilled.',
                ],
                'short_description_coconut' => 'Dark cacao coconut chia pudding with raspberries and cacao nibs.',
                'short_description_greek' => 'Dark cacao Greek yogurt chia pudding with raspberries and cacao nibs.',
            ],
            'Cacao & Almond Chia' => [
                'toppings' => [
                    'Almond Butter' => 2,
                    'Almond whole' => 5,
                    'Cocoa Powder' => 2,
                ],
                'instruction_suffix' => [
                    'Swirl in almond butter and cocoa powder. Top with chopped almonds.',
                    'Serve chilled.',
                ],
                'short_description_coconut' => 'Rich cacao coconut chia pudding swirled with almond butter and almonds.',
                'short_description_greek' => 'Rich cacao Greek yogurt chia pudding swirled with almond butter and almonds.',
            ],
            'Chia Pudding Smoothie' => [
                'toppings' => [
                    'Strawberries' => 30,
                    'Banana' => 25,
                ],
                'custom_instructions' => [
                    'Prepare Coconut Chia Pudding (Base) ahead and chill until thick.',
                    'Spoon the set pudding into the bottom of a glass or jar.',
                    'Blend strawberries and banana until smooth.',
                    'Pour the fruit smoothie over the chia layer. Serve chilled.',
                ],
                'short_description_coconut' => 'Layered coconut chia pudding with a strawberry-banana smoothie top.',
                'short_description_greek' => 'Layered Greek yogurt chia pudding with a strawberry-banana smoothie top.',
            ],
        ];
    }
}
