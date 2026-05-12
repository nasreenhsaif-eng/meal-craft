<?php

namespace App\Support;

/**
 * Canonical labels for the admin Meal Library create form and CSV-aligned workflows.
 */
final class MealLibraryTaxonomy
{
    /** @var list<string> */
    public const MEAL_PLAN_TAGS = [
        'Balanced',
        'Hormone Feast',
        'Ketogenic',
        'Sickle Cell Anemia',
    ];

    /** @var list<string> */
    public const DIETARY_TAGS = [
        'Vegan',
        'Vegetarian',
        'Dairy-free',
        'Gluten-free',
        'Nut-free',
    ];
}
