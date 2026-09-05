<?php

namespace App\Services;

use App\Enums\MealLibraryKey;
use App\Models\Meal;
use App\Models\MealCalorieTier;
use App\Support\MealTiersAuthoredPlates;
use App\Support\MealTiersCalorieTabs;
use App\Support\MealTiersIngredientStructurer;
use App\Support\MealTiersLibraryExclusions;
use App\Support\MealTiersProteinFamily;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class MealTiersLibraryCopyService
{
    public function copyFromClassic(Meal $classic): Meal
    {
        if ($classic->library_key === MealLibraryKey::Tiers) {
            throw new InvalidArgumentException('Only Meal Library meals can be copied into the Meal Tiers Library.');
        }

        if (MealTiersLibraryExclusions::isExcluded($classic)) {
            throw new InvalidArgumentException('This meal is excluded from the Meal Tiers Library.');
        }

        $classic->loadMissing('ingredients');

        return DB::transaction(function () use ($classic): Meal {
            $copy = $classic->replicate();
            $copy->library_key = MealLibraryKey::Tiers;
            $copy->library_sort_order = Meal::nextTiersLibrarySortOrder();
            $copy->library_edited_at = now();
            $copy->save();

            $baselineAttach = MealTiersAuthoredPlates::baselineAttach($copy);

            if ($baselineAttach === null) {
                $baselineAttach = [];
                foreach ($classic->ingredients as $ingredient) {
                    $grams = (float) ($ingredient->pivot->amount_grams ?? $ingredient->pivot->amount ?? 0);
                    $baselineAttach[$ingredient->id] = [
                        'amount_grams' => $grams,
                        'amount' => $ingredient->pivot->amount,
                        'unit' => $ingredient->pivot->unit,
                    ];
                }
            }

            if ($baselineAttach !== []) {
                $copy->ingredients()->sync($baselineAttach);
            }

            $copy->load('ingredients');
            $this->syncCalorieTiersFromBaseline($copy);

            return $copy->fresh(['ingredients', 'calorieTiers.ingredients']) ?? $copy;
        });
    }

    /**
     * Soft-delete Meal Tiers Library rows whose classic names are on the exclusion list.
     */
    public function purgeExcludedFromTiersLibrary(): int
    {
        $excluded = MealTiersLibraryExclusions::names();

        if ($excluded === []) {
            return 0;
        }

        $purged = 0;

        Meal::query()
            ->where('library_key', MealLibraryKey::Tiers)
            ->whereIn('name', $excluded)
            ->orderBy('id')
            ->each(function (Meal $meal) use (&$purged): void {
                $meal->calorieTiers()->each(function (MealCalorieTier $tier): void {
                    $tier->ingredients()->detach();
                });
                $meal->calorieTiers()->delete();
                $meal->ingredients()->detach();
                $meal->delete();
                $purged++;
            });

        return $purged;
    }

    /**
     * Copy every Meal Library row that is not already present (by name) in Meal Tiers Library.
     *
     * @return array{copied: int, skipped: int, purged: int}
     */
    public function copyAllMissingFromClassic(): array
    {
        $purged = $this->purgeExcludedFromTiersLibrary();

        /** @var array<string, true> $existingNames */
        $existingNames = Meal::query()
            ->where('library_key', MealLibraryKey::Tiers)
            ->pluck('name')
            ->flip()
            ->all();

        $copied = 0;
        $skipped = 0;

        Meal::queryForMealLibrary()
            ->with('ingredients')
            ->orderBy('library_sort_order')
            ->orderBy('id')
            ->each(function (Meal $classic) use (&$copied, &$skipped, &$existingNames): void {
                if (MealTiersLibraryExclusions::isExcluded($classic)) {
                    $skipped++;

                    return;
                }

                if (isset($existingNames[$classic->name])) {
                    $skipped++;

                    return;
                }

                $this->copyFromClassic($classic);
                $existingNames[$classic->name] = true;
                $copied++;
            });

        return [
            'copied' => $copied,
            'skipped' => $skipped,
            'purged' => $purged,
        ];
    }

    /**
     * Re-apply calorie tab generation for every tiers-library meal (e.g. after tier config changes).
     */
    public function resyncAllCalorieTiers(): int
    {
        $count = 0;

        Meal::queryForMealTiersLibrary()
            ->with('ingredients')
            ->orderBy('id')
            ->each(function (Meal $meal) use (&$count): void {
                $this->syncCalorieTiersFromBaseline($meal);
                $count++;
            });

        return $count;
    }

    public function syncCalorieTiersFromBaseline(Meal $meal): void
    {
        $tabs = MealTiersCalorieTabs::forMeal($meal);

        if ($tabs === []) {
            $meal->calorieTiers()->delete();

            return;
        }

        $gramsByTier = MealTiersIngredientStructurer::gramsByTier($meal);

        foreach ($tabs as $tier) {
            $grams = $gramsByTier[$tier] ?? [];
            $this->persistTier($meal, $tier, $grams);
        }

        $meal->calorieTiers()
            ->whereNotIn('calorie_tier', $tabs)
            ->delete();
    }

    public function reapplyAuthoredPlate(Meal $meal): void
    {
        $attach = MealTiersAuthoredPlates::baselineAttach($meal);

        if ($attach === null) {
            return;
        }

        $meal->ingredients()->sync($attach);
        $meal->load('ingredients');
        $this->syncCalorieTiersFromBaseline($meal);

        $displayTier = $meal->calorieTiers()->where('calorie_tier', 500)->first()
            ?? $meal->calorieTiers()->first();

        if ($displayTier !== null) {
            $meal->update([
                'total_calories' => $displayTier->total_calories,
                'total_protein' => $displayTier->total_protein,
                'total_carbs' => $displayTier->total_carbs,
                'total_fat' => $displayTier->total_fat,
                'library_edited_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<int, float>  $gramsByIngredientId
     */
    public function persistTier(Meal $meal, int $calorieTier, array $gramsByIngredientId): MealCalorieTier
    {
        $rows = [];
        foreach ($gramsByIngredientId as $ingredientId => $grams) {
            $rows[] = [
                'ingredient_id' => (int) $ingredientId,
                'amount_grams' => max(0.0, (float) $grams),
            ];
        }

        $nutrition = $rows === []
            ? []
            : RecipeNutritionCalculator::fromRows($rows, applyMealCookingYield: true);

        $buckets = MealTiersProteinFamily::bucketsForMeal($meal, $calorieTier);
        $designed = is_array($buckets) ? (float) ($buckets['designed_calories'] ?? $calorieTier) : (float) ($nutrition['calories'] ?? $calorieTier);

        /** @var MealCalorieTier $tierRow */
        $tierRow = $meal->calorieTiers()->updateOrCreate(
            ['calorie_tier' => $calorieTier],
            [
                'designed_calories' => $designed,
                'total_calories' => (float) ($nutrition['calories'] ?? 0),
                'total_protein' => (float) ($nutrition['protein'] ?? 0),
                'total_carbs' => (float) ($nutrition['carbs'] ?? 0),
                'total_fat' => (float) ($nutrition['fat'] ?? 0),
                'nutrition' => $nutrition === [] ? null : $nutrition,
            ],
        );

        $sync = [];
        foreach ($gramsByIngredientId as $ingredientId => $grams) {
            $sync[(int) $ingredientId] = ['amount_grams' => round((float) $grams, 2)];
        }
        $tierRow->ingredients()->sync($sync);

        return $tierRow;
    }
}
