<?php

namespace App\Support;

use App\Enums\CustomerActivityLevel;
use App\Enums\CustomerSex;
use App\Models\CustomerProfile;

/**
 * Daily reference intakes for % RDI on full-day micronutrient totals.
 * Labels match {@see resources/js/meal-library/nutrientDailyRdi.ts} and
 * {@see MealLibraryController::nutritionalDataForDetailView}.
 *
 * Sex- and activity-aware targets live in {@see self::calculate()}. When a
 * profile is omitted, {@see self::defaultTargets()} uses female + not-active
 * so library audits keep the 18 mg iron baseline.
 */
final class NutrientDailyRdi
{
    public const FLOOR_TARGET_PERCENT = 98.0;

    public const SODIUM_AI_MG = 1500.0;

    public const SODIUM_CAP_MG = 2300.0;

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
     * Adult (19–50) daily reference intakes by sex and activity.
     *
     * @return array<string, float>
     */
    public static function calculate(CustomerSex $sex, CustomerActivityLevel $activity, ?float $dailyCalories = null): array
    {
        $male = $sex === CustomerSex::Male;
        $band = self::activityBand($activity);

        $fiberBaseline = $male ? 38.0 : 25.0;
        $fiber = $fiberBaseline;
        if ($dailyCalories !== null && $dailyCalories > 0) {
            $fiber = max($fiberBaseline, self::roundTarget(($dailyCalories / 1000) * 14));
        }

        $vitaminC = self::scale(($male ? 90.0 : 75.0), match ($band) {
            'highly_active' => 1.2,
            'extremely_active' => 1.4,
            default => 1.0,
        });

        $vitaminB6 = self::scale(1.3, match ($band) {
            'highly_active' => 1.15,
            'extremely_active' => 1.3,
            default => 1.0,
        });

        $iron = self::scale(($male ? 8.0 : 18.0), match ($band) {
            'somewhat_active' => 1.1,
            'highly_active' => 1.3,
            'extremely_active' => 1.5,
            default => 1.0,
        });

        $magnesium = self::scale(($male ? 420.0 : 320.0), match ($band) {
            'highly_active' => 1.1,
            'extremely_active' => 1.2,
            default => 1.0,
        });

        $potassium = self::scale(($male ? 3400.0 : 2600.0), match ($band) {
            'highly_active' => 1.15,
            'extremely_active' => 1.25,
            default => 1.0,
        });

        $sodium = self::SODIUM_CAP_MG + match ($band) {
            'highly_active' => 500.0,
            'extremely_active' => 1000.0,
            default => 0.0,
        };

        return [
            'Fiber (g)' => $fiber,
            'Sugar (g)' => $male ? 36.0 : 25.0,
            'Vitamin A (mcg RAE)' => $male ? 900.0 : 700.0,
            'Vitamin C (mg)' => $vitaminC,
            'Vitamin D (mcg)' => 15.0,
            'Vitamin E (mg)' => 15.0,
            'Vitamin K2 (mcg)' => $male ? 120.0 : 90.0,
            'Folate B9 (mcg)' => 400.0,
            'Vitamin B12 (mcg)' => 2.4,
            'Vitamin B6 (mg)' => $vitaminB6,
            'Calcium (mg)' => 1000.0,
            'Iron (mg)' => $iron,
            'Magnesium (mg)' => $magnesium,
            'Potassium (mg)' => $potassium,
            'Zinc (mg)' => $male ? 11.0 : 8.0,
            'Sodium (mg)' => $sodium,
        ];
    }

    /**
     * Female, not-active baseline used when no customer profile is available.
     *
     * @return array<string, float>
     */
    public static function defaultTargets(?float $dailyCalories = null): array
    {
        return self::calculate(CustomerSex::Female, CustomerActivityLevel::Sedentary, $dailyCalories);
    }

    /**
     * @return array<string, float>
     */
    public static function forProfile(?CustomerProfile $profile, ?float $dailyCalories = null): array
    {
        if ($profile === null) {
            return self::defaultTargets($dailyCalories);
        }

        $sex = $profile->sex instanceof CustomerSex ? $profile->sex : CustomerSex::Female;
        $activity = $profile->activity_level instanceof CustomerActivityLevel
            ? $profile->activity_level
            : CustomerActivityLevel::tryFromStored(null);

        $calories = $dailyCalories;
        if ($calories === null && $profile->daily_calorie_target !== null) {
            $calories = (float) $profile->daily_calorie_target;
        }

        return self::calculate($sex, $activity, $calories);
    }

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

    /**
     * @param  array<string, float>|null  $targets
     */
    public static function rdiForLabel(string $label, ?array $targets = null): ?float
    {
        $map = $targets ?? self::defaultTargets();

        if (! isset($map[$label])) {
            return null;
        }

        return (float) $map[$label];
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

    /**
     * @param  array<string, float>|null  $targets
     */
    public static function percentOfRdi(string $label, float $total, ?array $targets = null): ?float
    {
        $rdi = self::rdiForLabel($label, $targets);

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

    /**
     * @return 'not_active'|'somewhat_active'|'highly_active'|'extremely_active'
     */
    private static function activityBand(CustomerActivityLevel $activity): string
    {
        return match ($activity->multiplierKey()) {
            'sedentary' => 'not_active',
            'moderately_active' => 'highly_active',
            'very_active' => 'extremely_active',
            default => 'somewhat_active',
        };
    }

    private static function scale(float $base, float $factor): float
    {
        return self::roundTarget($base * $factor);
    }

    private static function roundTarget(float $value): float
    {
        return round($value, 1);
    }
}
