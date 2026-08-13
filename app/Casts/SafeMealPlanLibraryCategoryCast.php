<?php

namespace App\Casts;

use App\Enums\MealPlanLibraryCategory;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<MealPlanLibraryCategory|null, MealPlanLibraryCategory|string|null>
 */
final class SafeMealPlanLibraryCategoryCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?MealPlanLibraryCategory
    {
        if ($value === null || $value === '') {
            return null;
        }

        return MealPlanLibraryCategory::tryFrom((string) $value)
            ?? MealPlanLibraryCategory::Balanced;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof MealPlanLibraryCategory) {
            return $value->value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return MealPlanLibraryCategory::tryFrom((string) $value)?->value
            ?? MealPlanLibraryCategory::Balanced->value;
    }
}
