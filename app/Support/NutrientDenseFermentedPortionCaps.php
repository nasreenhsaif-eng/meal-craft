<?php

namespace App\Support;

/**
 * Gram caps for fermented ingredients in nutrient-dense protocol meals.
 */
final class NutrientDenseFermentedPortionCaps
{
    public static function capGramsForIngredient(string $ingredientName): ?float
    {
        $normalized = strtolower(trim($ingredientName));

        $caps = config('customer_nutrition.nutrient_dense_fermented_caps', []);

        return match (true) {
            str_contains($normalized, 'miso') => (float) ($caps['miso_paste'] ?? 12.0),
            str_contains($normalized, 'kimchi') => (float) ($caps['kimchi'] ?? 40.0),
            str_contains($normalized, 'sauerkraut') => (float) ($caps['sauerkraut'] ?? 50.0),
            str_contains($normalized, 'fermented beetroot') => (float) ($caps['fermented_beetroot'] ?? 60.0),
            $normalized === 'kefir' => (float) ($caps['kefir'] ?? 120.0),
            str_contains($normalized, 'fermented chimichurri') => (float) ($caps['fermented_chimichurri'] ?? 25.0),
            default => null,
        };
    }

    /**
     * @return array<string, float>
     */
    public static function allCaps(): array
    {
        /** @var array<string, float> $caps */
        $caps = config('customer_nutrition.nutrient_dense_fermented_caps', []);

        return $caps;
    }
}
