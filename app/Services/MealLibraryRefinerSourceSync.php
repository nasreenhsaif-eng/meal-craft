<?php

namespace App\Services;

use App\Models\Meal;
use App\Support\MealLibraryRefinerOverrides;
use App\Support\MealRefinerCatalog;

/**
 * Mirrors Meal Library UI edits into {@see MealLibraryRefinerOverrides} for version control.
 */
final class MealLibraryRefinerSourceSync
{
    public function syncMeal(Meal $meal): bool
    {
        $meal->loadMissing('ingredients');

        if (! MealRefinerCatalog::isManagedMealName($meal->name)) {
            return false;
        }

        MealLibraryRefinerOverrides::put($meal->name, $this->snapshotFromMeal($meal));

        return true;
    }

    public function syncAllManagedMealsFromDatabase(): int
    {
        $count = 0;

        Meal::queryForMealLibrary()
            ->with(['ingredients'])
            ->orderBy('name')
            ->each(function (Meal $meal) use (&$count): void {
                if ($this->syncMeal($meal)) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @param  list<string>  $mealNames
     */
    public function forgetMeals(array $mealNames): void
    {
        $managed = array_values(array_filter(
            $mealNames,
            static fn (string $name): bool => MealRefinerCatalog::isManagedMealName($name),
        ));

        if ($managed === []) {
            return;
        }

        MealLibraryRefinerOverrides::forgetMany($managed);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotFromMeal(Meal $meal): array
    {
        /** @var array<string, float> $ingredients */
        $ingredients = [];

        foreach ($meal->ingredients as $ingredient) {
            $grams = (float) ($ingredient->pivot->amount_grams ?? 0);
            if ($grams <= 0) {
                continue;
            }

            $ingredients[$ingredient->name] = round($grams, 4);
        }

        ksort($ingredients, SORT_NATURAL | SORT_FLAG_CASE);

        $highlight = trim((string) ($meal->highlight ?? ''));
        if ($highlight === '') {
            $highlight = trim((string) ($meal->short_description ?? ''));
        }

        $instructions = trim((string) ($meal->instructions ?? ''));
        if ($instructions === '') {
            $instructions = trim((string) ($meal->description ?? ''));
        }

        $snapshot = [
            'synced_at' => now()->toIso8601String(),
        ];

        if ($ingredients !== []) {
            $snapshot['ingredients'] = $ingredients;
        }

        if ($highlight !== '') {
            $snapshot['highlight'] = $highlight;
            $snapshot['short_description'] = $highlight;
        }

        if ($instructions !== '') {
            $snapshot['instructions'] = $instructions;
        }

        if (is_array($meal->diet_tags) && $meal->diet_tags !== []) {
            $snapshot['diet_tags'] = array_values($meal->diet_tags);
        }

        if (is_array($meal->food_filter_tags) && $meal->food_filter_tags !== []) {
            $snapshot['food_filter_tags'] = array_values($meal->food_filter_tags);
        }

        return $snapshot;
    }
}
