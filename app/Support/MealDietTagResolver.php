<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Meal;
use Illuminate\Support\Collection;

/**
 * Derives canonical meal-library dietary tags from direct ingredients and base-recipe components.
 */
final class MealDietTagResolver
{
    /** @var list<string> */
    private const EGG_INGREDIENT_NAMES = [
        'Egg',
        'Egg White',
        'Egg Whites',
        'Eggs (Large)',
    ];

    /** @var list<string> */
    private const DAIRY_EXCEPTION_NAMES = [
        'Ghee',
        'Ghee (Clarified)',
    ];

    /**
     * Lowercase substrings that indicate meat, poultry, fish, or shellfish in an ingredient name.
     *
     * @var list<string>
     */
    private const ANIMAL_PROTEIN_NAME_FRAGMENTS = [
        'beef',
        'chicken',
        'salmon',
        'shrimp',
        'hamour',
        'fish sauce',
        'lamb',
        'turkey',
        'pork',
        'duck',
        'tuna',
        'sardine',
        'anchovy',
        'prawn',
        'scallop',
        'crab',
        'lobster',
        'bone broth',
        'beef bones',
        'beef rib bones',
    ];

    /**
     * Lowercase substrings that mark a meal as spicy (aligned with Balanced refiners).
     *
     * @var list<string>
     */
    private const SPICY_NAME_FRAGMENTS = [
        'harissa',
        'cajun',
        'tandoori',
        'kashmiri chili',
        'red thai chilli',
        'red thai chili',
        'spicy green chilli',
        'spicy green chili',
        'thai red curry',
        'zesty lime chili',
    ];

    /**
     * Lowercase substrings that indicate tree nuts or peanuts in an ingredient name.
     *
     * @var list<string>
     */
    private const NUT_NAME_FRAGMENTS = [
        'almond',
        'walnut',
        'pecan',
        'cashew',
        'peanut',
        'pistachio',
        'hazelnut',
        'macadamia',
        'pine nut',
        'brazil nut',
        'chestnut',
    ];

    /**
     * @return list<string>
     */
    public static function resolveForMeal(Meal $meal): array
    {
        $meal->loadMissing('ingredients.components');

        $ingredients = self::effectiveIngredients($meal);
        $tags = WholeFoodDietPolicy::REQUIRED_MEAL_DIET_TAGS;

        if ($ingredients->isEmpty()) {
            return self::sortTags($tags);
        }

        $hasAnimalProtein = false;
        $hasEggs = false;
        $hasDairy = false;
        $hasNuts = false;
        $isSpicy = false;

        foreach ($ingredients as $ingredient) {
            if (self::ingredientIsAnimalProtein($ingredient)) {
                $hasAnimalProtein = true;
            }

            if (self::ingredientContainsEggs($ingredient)) {
                $hasEggs = true;
            }

            if (self::ingredientContainsDairy($ingredient)) {
                $hasDairy = true;
            }

            if (self::ingredientContainsNuts($ingredient)) {
                $hasNuts = true;
            }

            if (self::ingredientIndicatesSpicy($ingredient)) {
                $isSpicy = true;
            }
        }

        if (! $hasAnimalProtein && ! $hasEggs && ! $hasDairy) {
            $tags[] = 'Vegan';
        } elseif (! $hasAnimalProtein) {
            $tags[] = 'Vegetarian';
        }

        if (! $hasNuts) {
            $tags[] = 'Nut-free';
        }

        if ($isSpicy) {
            $tags[] = 'Spicy';
        }

        return self::sortTags($tags);
    }

    /**
     * @return Collection<int, Ingredient>
     */
    private static function effectiveIngredients(Meal $meal): Collection
    {
        $collected = collect();
        $visited = [];

        foreach ($meal->ingredients as $ingredient) {
            self::collectIngredientTree($ingredient, $collected, $visited);
        }

        return $collected->unique(fn (Ingredient $ingredient): int => (int) $ingredient->getKey())->values();
    }

    /**
     * @param  Collection<int, Ingredient>  $collected
     * @param  array<int, true>  $visited
     */
    private static function collectIngredientTree(Ingredient $ingredient, Collection $collected, array &$visited): void
    {
        $id = (int) $ingredient->getKey();

        if ($id <= 0 || isset($visited[$id])) {
            return;
        }

        $visited[$id] = true;
        $ingredient->loadMissing('components');
        $collected->push($ingredient);

        foreach ($ingredient->components as $component) {
            self::collectIngredientTree($component, $collected, $visited);
        }
    }

    private static function ingredientIsAnimalProtein(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if ($name === 'eggplant' || str_starts_with($name, 'eggplant ')) {
            return false;
        }

        foreach (self::ANIMAL_PROTEIN_NAME_FRAGMENTS as $fragment) {
            if (str_contains($name, $fragment)) {
                return true;
            }
        }

        $allergens = self::normalizedAllergenSlugs($ingredient);

        return in_array(IngredientAllergenCatalog::FISH, $allergens, true)
            || in_array(IngredientAllergenCatalog::SHELLFISH, $allergens, true);
    }

    private static function ingredientContainsEggs(Ingredient $ingredient): bool
    {
        if (in_array($ingredient->name, self::EGG_INGREDIENT_NAMES, true)) {
            return true;
        }

        return in_array(IngredientAllergenCatalog::EGGS, self::normalizedAllergenSlugs($ingredient), true);
    }

    private static function ingredientContainsDairy(Ingredient $ingredient): bool
    {
        if (in_array($ingredient->name, self::DAIRY_EXCEPTION_NAMES, true)) {
            return true;
        }

        $name = strtolower(trim($ingredient->name));

        if (str_contains($name, 'ghee')) {
            return true;
        }

        if (in_array(IngredientAllergenCatalog::DAIRY, self::normalizedAllergenSlugs($ingredient), true)) {
            return true;
        }

        if (self::nameIsNonDairyButterOrMilkProduct($name)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(butter|milk|cheese|cream|yogurt|parmesan|cheddar|mozzarella|feta|ricotta|paneer)\b/',
            $name,
        );
    }

    private static function nameIsNonDairyButterOrMilkProduct(string $normalizedName): bool
    {
        if (str_contains($normalizedName, 'butternut')) {
            return true;
        }

        return (bool) preg_match(
            '/\b(almond|peanut|cashew|sunflower|sesame|coconut|homemade coconut|cashew cream)\b.*\b(butter|milk|cream)\b/',
            $normalizedName,
        ) || (bool) preg_match(
            '/\b(butter|milk|cream)\b.*\b(almond|peanut|cashew|coconut)\b/',
            $normalizedName,
        );
    }

    private static function ingredientContainsNuts(Ingredient $ingredient): bool
    {
        $allergens = self::normalizedAllergenSlugs($ingredient);

        if (in_array(IngredientAllergenCatalog::PEANUTS, $allergens, true)
            || in_array(IngredientAllergenCatalog::TREE_NUTS, $allergens, true)) {
            return true;
        }

        $name = strtolower(trim($ingredient->name));

        foreach (self::NUT_NAME_FRAGMENTS as $fragment) {
            if (str_contains($name, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private static function ingredientIndicatesSpicy(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        foreach (self::SPICY_NAME_FRAGMENTS as $fragment) {
            if (str_contains($name, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function normalizedAllergenSlugs(Ingredient $ingredient): array
    {
        $raw = is_array($ingredient->common_allergens) ? $ingredient->common_allergens : [];
        $slugs = [];

        foreach ($raw as $slug) {
            if (! is_string($slug)) {
                continue;
            }

            $normalized = strtolower(trim($slug));

            if ($normalized !== '') {
                $slugs[] = $normalized;
            }
        }

        return $slugs;
    }

    /**
     * @param  list<string>  $tags
     * @return list<string>
     */
    private static function sortTags(array $tags): array
    {
        $unique = [];
        foreach ($tags as $tag) {
            $canonical = MealLibraryTaxonomy::resolveDietaryTagCanonical($tag);
            if ($canonical !== null) {
                $unique[$canonical] = true;
            }
        }

        $order = ['Vegan', 'Vegetarian', 'Dairy-free', 'Gluten-free', 'Nut-free', 'Spicy'];
        $sorted = [];

        foreach ($order as $tag) {
            if (isset($unique[$tag])) {
                $sorted[] = $tag;
                unset($unique[$tag]);
            }
        }

        foreach (array_keys($unique) as $remaining) {
            $sorted[] = $remaining;
        }

        return $sorted;
    }
}
