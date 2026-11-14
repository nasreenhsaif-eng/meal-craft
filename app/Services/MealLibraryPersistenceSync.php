<?php

namespace App\Services;

use App\Models\Meal;

/**
 * Keeps version-controlled menu files aligned with Meal Library UI edits.
 */
final class MealLibraryPersistenceSync
{
    public function __construct(
        private MenuDevelopmentCsvSync $menuDevelopmentCsvSync,
        private MealLibraryRefinerSourceSync $mealLibraryRefinerSourceSync,
    ) {}

    public function afterMealSaved(Meal $meal): void
    {
        $this->menuDevelopmentCsvSync->syncMealsFromDatabase();
        $this->mealLibraryRefinerSourceSync->syncMeal($meal->fresh(['ingredients']));
    }

    /**
     * @param  list<string>  $deletedMealNames
     */
    public function afterMealsDeleted(array $deletedMealNames): void
    {
        $this->mealLibraryRefinerSourceSync->forgetMeals($deletedMealNames);
        $this->menuDevelopmentCsvSync->syncMealsFromDatabase();
    }

    public function afterIngredientSaved(): void
    {
        $this->menuDevelopmentCsvSync->syncIngredientsFromDatabase();
    }

    public function afterMealAndIngredientSaved(Meal $meal): void
    {
        $this->afterMealSaved($meal);
        $this->afterIngredientSaved();
    }
}
