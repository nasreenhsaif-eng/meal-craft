<?php

namespace App\Support;

final class IngredientLibraryCategory
{
    public const BaseIngredient = 'Base Ingredient';

    /** @deprecated Legacy label; normalized to {@see BaseIngredient}. */
    public const LegacyBaseRecipe = 'Base Recipe';

    /**
     * @return list<string>
     */
    public static function preparedLabels(): array
    {
        return [self::BaseIngredient, self::LegacyBaseRecipe];
    }

    public static function isPrepared(?string $category): bool
    {
        if ($category === null || trim($category) === '') {
            return false;
        }

        $norm = strtolower(trim(preg_replace('/\s+/', ' ', $category) ?? $category));

        foreach (self::preparedLabels() as $label) {
            if (strtolower($label) === $norm) {
                return true;
            }
        }

        return false;
    }

    public static function canonicalPrepared(?string $category): string
    {
        return self::isPrepared($category) ? self::BaseIngredient : (string) $category;
    }
}
