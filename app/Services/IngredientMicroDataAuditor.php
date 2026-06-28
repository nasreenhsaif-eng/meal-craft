<?php

namespace App\Services;

use App\Models\Ingredient;

/**
 * Reports missing micronutrient data on ingredients used in scheduled rotation meals.
 */
final class IngredientMicroDataAuditor
{
    /** @var list<string> */
    private const PLANT_PRIORITY_KEYS = [
        'potassium',
        'iron',
        'calcium',
        'b9_folate',
        'magnesium',
        'zinc',
        'vitamin_a',
        'fiber',
    ];

    /** @var list<string> */
    private const PLANT_VITAMIN_C_INGREDIENTS = [
        'Purslane',
        'Spinach (Fresh)',
        'Kale',
        'Rocca',
        'Broccoli',
        'Sweet Potato',
        'Carrots',
        'Bell Pepper (Red)',
    ];

    /** @var array<string, list<string>> */
    private const EXPECTED_NUTRIENTS_BY_INGREDIENT = [
        'Egg' => ['calcium', 'b12', 'vitamin_k2'],
        'Tahini' => ['calcium', 'vitamin_k2'],
        'Salmon' => ['b12', 'vitamin_k2'],
        'Beef Chuck Roast' => ['b12', 'vitamin_k2', 'calcium', 'iron'],
        'Beef Liver' => ['b12', 'vitamin_k2', 'iron', 'b9_folate', 'vitamin_a'],
        'Chicken Liver' => ['b12', 'vitamin_k2', 'iron', 'b9_folate', 'vitamin_a'],
        'Sesame Seeds' => ['calcium', 'vitamin_k2'],
        'Rocca' => ['calcium'],
        'Purslane' => ['calcium', 'iron', 'magnesium', 'potassium'],
    ];

    /**
     * @return list<array{ingredient: string, missing: list<string>}>
     */
    public function auditScheduledBoostIngredients(): array
    {
        $issues = [];

        foreach (MicronutrientBoostCatalog::BOOST_INGREDIENTS as $name) {
            $keys = self::PLANT_PRIORITY_KEYS;

            if (in_array($name, self::PLANT_VITAMIN_C_INGREDIENTS, true)) {
                $keys = [...$keys, 'vitamin_c'];
            }

            $issues = array_merge($issues, $this->auditIngredient($name, $keys));
        }

        foreach (self::EXPECTED_NUTRIENTS_BY_INGREDIENT as $name => $keys) {
            $issues = array_merge($issues, $this->auditIngredient($name, $keys));
        }

        return $issues;
    }

    /**
     * @param  list<string>  $keys
     * @return list<array{ingredient: string, missing: list<string>}>
     */
    private function auditIngredient(string $name, array $keys): array
    {
        /** @var Ingredient|null $ingredient */
        $ingredient = Ingredient::query()->where('name', $name)->first();

        if ($ingredient === null) {
            return [[
                'ingredient' => $name,
                'missing' => ['ingredient_not_found'],
            ]];
        }

        $per100 = RecipeNutritionCalculator::per100gNutritionForIngredient($ingredient);
        $missing = [];

        foreach ($keys as $key) {
            if (((float) ($per100[$key] ?? 0)) <= 0) {
                $missing[] = $key;
            }
        }

        if ($missing === []) {
            return [];
        }

        return [[
            'ingredient' => $name,
            'missing' => $missing,
        ]];
    }

    /**
     * @param  list<array{ingredient: string, missing: list<string>}>  $issues
     */
    public function formatReport(array $issues): string
    {
        if ($issues === []) {
            return 'All boost-catalog ingredients have priority micronutrient data.';
        }

        $lines = ['Boost ingredient micro data gaps:'];

        foreach ($issues as $issue) {
            $lines[] = sprintf(
                '  - %s: %s',
                $issue['ingredient'],
                implode(', ', $issue['missing']),
            );
        }

        return implode(PHP_EOL, $lines);
    }
}
