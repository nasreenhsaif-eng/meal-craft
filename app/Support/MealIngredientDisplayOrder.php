<?php

namespace App\Support;

use App\Models\Ingredient;

/**
 * Customer-facing ingredient list order: protein → carbs → vegetables → herbs/spices → sauces → fats.
 */
final class MealIngredientDisplayOrder
{
    public const GROUP_PROTEIN = 10;

    public const GROUP_CARBS = 20;

    public const GROUP_VEGETABLES = 30;

    public const GROUP_HERBS_SPICES = 40;

    public const GROUP_SAUCES = 50;

    public const GROUP_FATS = 60;

    public const GROUP_OTHER = 70;

    public static function groupRank(Ingredient $ingredient): int
    {
        $name = strtolower($ingredient->name);
        $category = strtolower(trim((string) $ingredient->usda_food_category));

        if (self::nameIndicatesSauce($name)) {
            return self::GROUP_SAUCES;
        }

        if (self::nameIndicatesFat($name, $category)) {
            return self::GROUP_FATS;
        }

        if (self::nameIndicatesProtein($name)) {
            return self::GROUP_PROTEIN;
        }

        if (self::nameIndicatesCarb($name)) {
            return self::GROUP_CARBS;
        }

        if (self::categoryIsHerbOrSpice($category) || self::nameIndicatesHerbOrSpice($name)) {
            return self::GROUP_HERBS_SPICES;
        }

        return match (true) {
            self::categoryIsProtein($category) => self::GROUP_PROTEIN,
            self::categoryIsCarb($category) => self::GROUP_CARBS,
            self::categoryIsVegetable($category) => self::GROUP_VEGETABLES,
            self::categoryIsSauce($category) => self::GROUP_SAUCES,
            self::categoryIsFat($category) => self::GROUP_FATS,
            default => self::GROUP_OTHER,
        };
    }

    /**
     * @param  iterable<Ingredient>  $ingredients
     * @return list<Ingredient>
     */
    public static function sortedIngredients(iterable $ingredients): array
    {
        $items = is_array($ingredients) ? $ingredients : iterator_to_array($ingredients);

        usort($items, static function (Ingredient $a, Ingredient $b): int {
            $rank = self::groupRank($a) <=> self::groupRank($b);

            if ($rank !== 0) {
                return $rank;
            }

            return strcasecmp($a->name, $b->name);
        });

        return $items;
    }

    private static function nameIndicatesSauce(string $name): bool
    {
        foreach ([
            'sauce',
            'dressing',
            'marinade',
            'chutney',
            'pesto',
            'hummus',
            ' dip',
            'mutabal',
            'harissa paste',
            'curry paste',
            'marinara',
            'broth',
            'stock',
            'vinegar',
            'tamari',
            'soy sauce',
            'fish sauce',
            'mustard dressing',
            'tomato paste',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return str_ends_with($name, ' dip');
    }

    private static function nameIndicatesProtein(string $name): bool
    {
        if (str_contains($name, 'green bean')) {
            return false;
        }

        foreach ([
            'chicken',
            'beef',
            'salmon',
            'shrimp',
            'tuna',
            'turkey',
            'lamb',
            'pork',
            'meatball',
            'ground beef',
            'ground lean',
            'chuck',
            'breast',
            'fillet',
            'tofu',
            'egg white',
            'anchov',
            'edamame',
            'bean',
            'lentil',
            'chickpea',
            'cannellini',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        if ($name === 'egg' || str_starts_with($name, 'egg ')) {
            return true;
        }

        return false;
    }

    private static function nameIndicatesCarb(string $name): bool
    {
        foreach ([
            'rice',
            'quinoa',
            'couscous',
            'bread',
            'flatbread',
            'pasta',
            'spaghetti',
            'noodle',
            'oat',
            'barley',
            'millet',
            'flour',
            'sweet potato',
            'potato',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function nameIndicatesFat(string $name, string $category): bool
    {
        if (self::categoryIsFat($category)) {
            return true;
        }

        foreach ([
            ' oil',
            'oil ',
            'butter',
            'ghee',
            'tahini',
            'avocado',
            'walnut',
            'almond whole',
            'cashew',
            'peanut butter',
            'sesame seed',
            'chia seed',
            'hemp seed',
            'pumpkin seed',
            'sunflower seed',
            'coconut cream',
            'coconut milk',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return str_starts_with($name, 'olive oil');
    }

    private static function nameIndicatesHerbOrSpice(string $name): bool
    {
        foreach ([
            'salt',
            'pepper',
            'cinnamon',
            'cumin',
            'paprika',
            'oregano',
            'thyme',
            'rosemary',
            'basil',
            'parsley',
            'coriander',
            'mint',
            'dill',
            'saffron',
            'turmeric',
            'ginger',
            'chili',
            'chilli',
            'spice',
            'za\'atar',
            'sumac',
            'clove',
            'nutmeg',
            'cardamom',
            'barberr',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function categoryIsProtein(string $category): bool
    {
        return str_contains($category, 'protein') || $category === 'legumes';
    }

    private static function categoryIsCarb(string $category): bool
    {
        return str_contains($category, 'grain');
    }

    private static function categoryIsVegetable(string $category): bool
    {
        return str_contains($category, 'vegetable')
            || str_contains($category, 'fruit');
    }

    private static function categoryIsHerbOrSpice(string $category): bool
    {
        return str_contains($category, 'herb') || str_contains($category, 'spice');
    }

    private static function categoryIsSauce(string $category): bool
    {
        return in_array($category, [
            'condiments',
            'condiment',
            'liquids',
            'soups & broths',
            'beverages',
            'beverage',
        ], true);
    }

    private static function categoryIsFat(string $category): bool
    {
        return str_contains($category, 'fat') || $category === 'nuts';
    }
}
