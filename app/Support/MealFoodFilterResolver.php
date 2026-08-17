<?php

namespace App\Support;

use App\Models\Ingredient;
use App\Models\Meal;
use Illuminate\Support\Collection;

/**
 * Derives canonical meal-library food filter slugs from direct ingredients and base-recipe components.
 */
final class MealFoodFilterResolver
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

    /** @var list<string> */
    private const SHELLFISH_NAME_FRAGMENTS = [
        'shrimp',
        'prawn',
        'crab',
        'lobster',
        'scallop',
    ];

    /** @var list<string> */
    private const FISH_NAME_FRAGMENTS = [
        'salmon',
        'tuna',
        'sardine',
        'anchovy',
        'hamour',
        'fish sauce',
    ];

    /** @var list<string> */
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

    /** @var list<string> */
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

    /** @var list<string> */
    private const GLUTEN_NAME_FRAGMENTS = [
        'wheat',
        'gluten',
        'couscous',
        'bulgur',
        'semolina',
        'barley',
        'rye',
        'seitan',
        'panko',
        'breadcrumb',
        'crouton',
        'orzo',
        'pasta',
        'spaghetti',
        'noodle',
        'tortilla wrap',
        'flatbread',
    ];

    /** @var list<string> */
    private const GLUTEN_FREE_FLOUR_FRAGMENTS = [
        'almond',
        'coconut',
        'chickpea',
        'rice',
        'tapioca',
        'buckwheat',
        'quinoa',
        'sorghum',
        'cassava',
    ];

    /** @var list<string> */
    private const SOY_NAME_FRAGMENTS = [
        'soy',
        'tamari',
        'tempeh',
        'tofu',
        'miso',
        'edamame',
    ];

    /** @var list<string> */
    private const SESAME_NAME_FRAGMENTS = [
        'sesame',
        'tahini',
    ];

    /** @var list<string> */
    private const BEAN_NAME_FRAGMENTS = [
        'chickpea',
        'lentil',
        'hummus',
        'dal',
        'cannellini',
        'kidney bean',
        'black bean',
        'white bean',
        'fava',
        'lupini',
        'smashed white beans',
        'cooked black beans',
    ];

    /** @var list<string> */
    private const BEAN_EXCLUSION_FRAGMENTS = [
        'green bean',
        'string bean',
        'haricot vert',
    ];

    /** @var list<string> */
    private const NIGHTSHADE_NAME_FRAGMENTS = [
        'tomato',
        'eggplant',
        'bell pepper',
        'chili pepper',
        'chilli pepper',
        'jalapeno',
        'jalapeño',
        'habanero',
        'cayenne',
        'paprika',
        'rocoto',
        'goji',
        'tomatillo',
        'red pepper flake',
        'potato',
    ];

    /**
     * @return list<string>
     */
    public static function resolveForMeal(Meal $meal): array
    {
        $meal->loadMissing('ingredients.components');

        $ingredients = self::effectiveIngredients($meal);
        $filters = [];

        foreach ($ingredients as $ingredient) {
            foreach (self::filtersTriggeredByIngredient($ingredient) as $slug) {
                $filters[$slug] = true;
            }
        }

        return MealFoodFilterCatalog::canonicalSlugsFromList(array_keys($filters));
    }

    /**
     * @return list<string>
     */
    private static function filtersTriggeredByIngredient(Ingredient $ingredient): array
    {
        $triggered = [];

        if (self::ingredientTriggersSesame($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::SESAME;
        }

        if (self::ingredientTriggersGluten($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::GLUTEN;
        }

        if (self::ingredientTriggersSoy($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::SOY;
        }

        if (self::ingredientTriggersShellfish($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::SHELLFISH;
        }

        if (self::ingredientTriggersEggs($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::EGGS;
        }

        if (self::ingredientTriggersDairy($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::DAIRY;
        }

        if (self::ingredientTriggersFish($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::FISH;
        }

        if (self::ingredientTriggersNuts($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::NUTS;
        }

        if (self::ingredientTriggersBeans($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::BEANS;
        }

        if (self::ingredientTriggersNightshades($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::NIGHTSHADES;
        }

        if (self::ingredientTriggersSpicy($ingredient)) {
            $triggered[] = MealFoodFilterCatalog::SPICY;
        }

        return $triggered;
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

    private static function ingredientTriggersSesame(Ingredient $ingredient): bool
    {
        if (in_array(IngredientAllergenCatalog::SESAME, self::normalizedAllergenSlugs($ingredient), true)) {
            return true;
        }

        return self::nameContainsAnyFragment(strtolower(trim($ingredient->name)), self::SESAME_NAME_FRAGMENTS);
    }

    private static function ingredientTriggersGluten(Ingredient $ingredient): bool
    {
        if (in_array(IngredientAllergenCatalog::WHEAT, self::normalizedAllergenSlugs($ingredient), true)) {
            return true;
        }

        $name = strtolower(trim($ingredient->name));

        if (self::nameContainsAnyFragment($name, self::GLUTEN_NAME_FRAGMENTS)) {
            return true;
        }

        if (str_contains($name, 'bread') && ! str_contains($name, 'quinoa bread')) {
            return true;
        }

        if (str_contains($name, 'flour') && ! self::nameContainsAnyFragment($name, self::GLUTEN_FREE_FLOUR_FRAGMENTS)) {
            return true;
        }

        return false;
    }

    private static function ingredientTriggersSoy(Ingredient $ingredient): bool
    {
        if (in_array(IngredientAllergenCatalog::SOY, self::normalizedAllergenSlugs($ingredient), true)) {
            return true;
        }

        return self::nameContainsAnyFragment(strtolower(trim($ingredient->name)), self::SOY_NAME_FRAGMENTS);
    }

    private static function ingredientTriggersShellfish(Ingredient $ingredient): bool
    {
        if (in_array(IngredientAllergenCatalog::SHELLFISH, self::normalizedAllergenSlugs($ingredient), true)) {
            return true;
        }

        return self::nameContainsAnyFragment(strtolower(trim($ingredient->name)), self::SHELLFISH_NAME_FRAGMENTS);
    }

    private static function ingredientTriggersEggs(Ingredient $ingredient): bool
    {
        if (in_array($ingredient->name, self::EGG_INGREDIENT_NAMES, true)) {
            return true;
        }

        return in_array(IngredientAllergenCatalog::EGGS, self::normalizedAllergenSlugs($ingredient), true);
    }

    private static function ingredientTriggersDairy(Ingredient $ingredient): bool
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

    private static function ingredientTriggersFish(Ingredient $ingredient): bool
    {
        if (self::ingredientTriggersShellfish($ingredient)) {
            return false;
        }

        if (in_array(IngredientAllergenCatalog::FISH, self::normalizedAllergenSlugs($ingredient), true)) {
            return true;
        }

        return self::nameContainsAnyFragment(strtolower(trim($ingredient->name)), self::FISH_NAME_FRAGMENTS);
    }

    private static function ingredientTriggersNuts(Ingredient $ingredient): bool
    {
        $allergens = self::normalizedAllergenSlugs($ingredient);

        if (in_array(IngredientAllergenCatalog::PEANUTS, $allergens, true)
            || in_array(IngredientAllergenCatalog::TREE_NUTS, $allergens, true)) {
            return true;
        }

        return self::nameContainsAnyFragment(strtolower(trim($ingredient->name)), self::NUT_NAME_FRAGMENTS);
    }

    private static function ingredientTriggersBeans(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if (self::nameContainsAnyFragment($name, self::BEAN_EXCLUSION_FRAGMENTS)) {
            return false;
        }

        if (self::nameContainsAnyFragment($name, self::BEAN_NAME_FRAGMENTS)) {
            return true;
        }

        if (preg_match('/\bbeans?\b/', $name) === 1) {
            return true;
        }

        return false;
    }

    private static function ingredientTriggersNightshades(Ingredient $ingredient): bool
    {
        $name = strtolower(trim($ingredient->name));

        if ($name === 'eggplant' || str_starts_with($name, 'eggplant ')) {
            return true;
        }

        if (str_contains($name, 'sweet potato')) {
            return false;
        }

        if (str_contains($name, 'black pepper')) {
            return false;
        }

        return self::nameContainsAnyFragment($name, self::NIGHTSHADE_NAME_FRAGMENTS);
    }

    private static function ingredientTriggersSpicy(Ingredient $ingredient): bool
    {
        return self::nameContainsAnyFragment(strtolower(trim($ingredient->name)), self::SPICY_NAME_FRAGMENTS);
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

    /**
     * @param  list<string>  $fragments
     */
    private static function nameContainsAnyFragment(string $name, array $fragments): bool
    {
        foreach ($fragments as $fragment) {
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
}
