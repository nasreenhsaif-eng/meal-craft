<?php

namespace App\Support;

/**
 * Daily reference intakes for % RDI on full-day micronutrient totals.
 * Labels match {@see resources/js/meal-library/nutrientDailyRdi.ts} and
 * {@see MealLibraryController::nutritionalDataForDetailView}.
 */
final class NutrientDailyRdi
{
    public const FLOOR_TARGET_PERCENT = 98.0;

    /** @var array<string, float> */
    public const RDI_BY_LABEL = [
        'Fiber (g)' => 28.0,
        'Sugar (g)' => 50.0,
        'Vitamin A (mcg RAE)' => 900.0,
        'Vitamin C (mg)' => 90.0,
        'Vitamin D (mcg)' => 15.0,
        'Vitamin E (mg)' => 15.0,
        'Vitamin K2 (mcg)' => 120.0,
        'Folate B9 (mcg)' => 400.0,
        'Vitamin B12 (mcg)' => 2.4,
        'Vitamin B6 (mg)' => 1.7,
        'Calcium (mg)' => 1000.0,
        'Iron (mg)' => 18.0,
        'Magnesium (mg)' => 420.0,
        'Potassium (mg)' => 2600.0,
        'Zinc (mg)' => 11.0,
        'Sodium (mg)' => 2300.0,
    ];

    /** @var array<string, string> */
    public const NUTRITION_KEY_TO_LABEL = [
        'fiber' => 'Fiber (g)',
        'sugar' => 'Sugar (g)',
        'vitamin_a' => 'Vitamin A (mcg RAE)',
        'vitamin_c' => 'Vitamin C (mg)',
        'vitamin_d' => 'Vitamin D (mcg)',
        'vitamin_e' => 'Vitamin E (mg)',
        'vitamin_k2' => 'Vitamin K2 (mcg)',
        'b9_folate' => 'Folate B9 (mcg)',
        'b12' => 'Vitamin B12 (mcg)',
        'b6' => 'Vitamin B6 (mg)',
        'calcium' => 'Calcium (mg)',
        'iron' => 'Iron (mg)',
        'magnesium' => 'Magnesium (mg)',
        'potassium' => 'Potassium (mg)',
        'zinc' => 'Zinc (mg)',
        'sodium' => 'Sodium (mg)',
    ];

    /** @var list<string> */
    private const FLOOR_LABELS = [
        'Fiber (g)',
        'Vitamin A (mcg RAE)',
        'Vitamin C (mg)',
        'Vitamin E (mg)',
        'Vitamin K2 (mcg)',
        'Folate B9 (mcg)',
        'Vitamin B12 (mcg)',
        'Vitamin B6 (mg)',
        'Calcium (mg)',
        'Iron (mg)',
        'Magnesium (mg)',
        'Potassium (mg)',
        'Zinc (mg)',
    ];

    /** @var list<string> */
    private const CEILING_LABELS = [
        'Sugar (g)',
        'Sodium (mg)',
    ];

    /** @var list<string> */
    private const BEST_EFFORT_LABELS = [
        'Vitamin D (mcg)',
    ];

    /**
     * @return list<int>
     */
    public static function enforcedTiers(): array
    {
        return [1500, 1800, 2000];
    }

    /**
     * @return list<int>
     */
    public static function informationalTiers(): array
    {
        return [1000, 1200];
    }

    /**
     * @return list<int>
     */
    public static function allAuditTiers(): array
    {
        return [1000, 1200, 1500, 1800, 2000];
    }

    /**
     * @return list<string>
     */
    public static function floorLabels(): array
    {
        return self::FLOOR_LABELS;
    }

    /**
     * @return list<string>
     */
    public static function ceilingLabels(): array
    {
        return self::CEILING_LABELS;
    }

    /**
     * @return list<string>
     */
    public static function bestEffortLabels(): array
    {
        return self::BEST_EFFORT_LABELS;
    }

    public static function rdiForLabel(string $label): ?float
    {
        return self::RDI_BY_LABEL[$label] ?? null;
    }

    public static function labelForNutritionKey(string $key): ?string
    {
        return self::NUTRITION_KEY_TO_LABEL[$key] ?? null;
    }

    public static function tierEnforced(int $planTier): bool
    {
        return in_array($planTier, self::enforcedTiers(), true);
    }

    public static function nutrientStatus(string $label): string
    {
        if (in_array($label, self::BEST_EFFORT_LABELS, true)) {
            return 'best_effort';
        }

        if (in_array($label, self::CEILING_LABELS, true)) {
            return 'ceiling';
        }

        return 'floor';
    }

    public static function percentOfRdi(string $label, float $total): ?float
    {
        $rdi = self::rdiForLabel($label);

        if ($rdi === null || $rdi <= 0 || ! is_finite($total)) {
            return null;
        }

        return ($total / $rdi) * 100.0;
    }

    public static function meetsFloorTarget(string $label, float $percent): bool
    {
        if (self::nutrientStatus($label) !== 'floor') {
            return true;
        }

        return $percent >= self::FLOOR_TARGET_PERCENT;
    }

    public static function meetsCeilingTarget(string $label, float $percent): bool
    {
        if (self::nutrientStatus($label) !== 'ceiling') {
            return true;
        }

        return $percent <= 100.0;
    }

    /**
     * @return list<string>
     */
    public static function fixedSlotCombinations(): array
    {
        return [
            'side_salad,dessert',
            'side_salad,soup',
            'dessert,soup',
        ];
    }

    /**
     * @return list<string>
     */
    public static function parseFixedSlotCombination(string $combination): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $combination))));

        return $parts;
    }
}
