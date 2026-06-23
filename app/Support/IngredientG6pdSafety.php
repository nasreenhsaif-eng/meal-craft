<?php

namespace App\Support;

use App\Models\Ingredient;
use Illuminate\Database\Eloquent\Builder;

/**
 * G6PD deficiency safety: flags ingredients that can trigger hemolysis and surfaces alerts on meals.
 */
final class IngredientG6pdSafety
{
    public const TRIGGER_SAFETY_LABEL = 'G6PD Trigger';

    public const HIGHLIGHT_BADGE_LABEL = 'G6PD Alert';

    public const SAFETY_ALERT_BADGE_LABEL = 'G6PD Safety Alert';

    /**
     * Lowercase substrings matched against normalized ingredient names when {@see Ingredient::$is_g6pd_trigger}
     * is false — clinical lists evolve; keep aligned with medical guidance.
     *
     * @var list<string>
     */
    private const CANONICAL_NAME_FRAGMENTS = [
        'cannellini',
        'green beans',
    ];

    /**
     * Whether the ingredient name alone indicates G6PD caution (in addition to {@see Ingredient::$is_g6pd_trigger}).
     */
    public static function canonicalNameIndicatesG6pdTrigger(?string $name): bool
    {
        if ($name === null || trim($name) === '') {
            return false;
        }

        $normalized = strtolower(trim((string) preg_replace('/\s+/', ' ', $name)));

        foreach (self::CANONICAL_NAME_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Effective trigger: stored flag OR canonical name rule.
     */
    public static function ingredientHasEffectiveG6pdTrigger(Ingredient $ingredient): bool
    {
        return $ingredient->is_g6pd_trigger
            || self::canonicalNameIndicatesG6pdTrigger($ingredient->name);
    }

    /**
     * @param  Builder<Ingredient>  $query
     */
    public static function applyEffectiveG6pdConstraintToQuery(Builder $query): void
    {
        $query->where(function (Builder $inner): void {
            $inner->where('is_g6pd_trigger', true);
            foreach (self::CANONICAL_NAME_FRAGMENTS as $fragment) {
                $inner->orWhereRaw('lower(name) like ?', ['%'.$fragment.'%']);
            }
        });
    }

    /**
     * @param  list<int>  $ingredientIds
     */
    public static function mealContainsG6pdTrigger(array $ingredientIds): bool
    {
        $ids = array_values(array_unique(array_filter(
            array_map(intval(...), $ingredientIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return false;
        }

        return Ingredient::query()
            ->whereIn('id', $ids)
            ->where(function (Builder $outer): void {
                $outer->where(function (Builder $direct): void {
                    self::applyEffectiveG6pdConstraintToQuery($direct);
                })->orWhereHas('components', static function (Builder $relation): void {
                    self::applyEffectiveG6pdConstraintToQuery($relation);
                });
            })
            ->exists();
    }

    /**
     * @param  list<string>  $existingLabels
     * @return list<string>
     */
    public static function mergeTriggerIntoSafetyLabels(array $existingLabels, bool $hasTrigger): array
    {
        $labels = [];
        foreach ($existingLabels as $label) {
            $trimmed = trim((string) $label);
            if ($trimmed !== '') {
                $labels[$trimmed] = true;
            }
        }

        if ($hasTrigger) {
            $labels[self::TRIGGER_SAFETY_LABEL] = true;
        }

        $out = array_keys($labels);
        sort($out);

        return $out;
    }
}
