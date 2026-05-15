<?php

namespace App\Support;

/**
 * Sickle Cell program RDI values and per-serving “High Source” (≥20% RDI) highlight rules.
 */
final class SickleCellNutrientRdi
{
    public const HIGH_SOURCE_FRACTION = 0.20;

    public const RDI_FOLATE_MCG = 1000.0;

    public const RDI_B12_MCG = 2.4;

    public const RDI_B6_MG = 1.7;

    public const RDI_VITAMIN_D_MCG = 15.0;

    public const RDI_CALCIUM_MG = 1000.0;

    public const RDI_ZINC_MG = 11.0;

    public const RDI_VITAMIN_A_MCG = 900.0;

    public const RDI_VITAMIN_C_MG = 90.0;

    public const RDI_VITAMIN_E_MG = 15.0;

    public const BADGE_FOLATE = 'Folate';

    public const BADGE_B12 = 'B12';

    public const BADGE_B6 = 'B6';

    public const BADGE_VITAMIN_D_CALCIUM = 'Vitamin D & Calcium';

    public const BADGE_ZINC = 'Zinc';

    public const BADGE_ANTIOXIDANTS = 'Antioxidants';

    public static function highSourceThreshold(float $rdi): float
    {
        return $rdi * self::HIGH_SOURCE_FRACTION;
    }

    public static function isHighSource(float $amount, float $rdi): bool
    {
        if ($rdi <= 0) {
            return false;
        }

        return $amount >= self::highSourceThreshold($rdi);
    }

    /**
     * @param  array<string, float>  $nutrition  Per-serving totals (bulk meals: batch ÷ servings).
     * @return list<string>
     */
    public static function highlightBadgeLabels(array $nutrition): array
    {
        $badges = [];

        if (self::isHighSource((float) ($nutrition['b9_folate'] ?? 0), self::RDI_FOLATE_MCG)) {
            $badges[] = self::BADGE_FOLATE;
        }

        if (self::isHighSource((float) ($nutrition['b12'] ?? 0), self::RDI_B12_MCG)) {
            $badges[] = self::BADGE_B12;
        }

        if (self::isHighSource((float) ($nutrition['b6'] ?? 0), self::RDI_B6_MG)) {
            $badges[] = self::BADGE_B6;
        }

        if (
            self::isHighSource((float) ($nutrition['vitamin_d'] ?? 0), self::RDI_VITAMIN_D_MCG)
            && self::isHighSource((float) ($nutrition['calcium'] ?? 0), self::RDI_CALCIUM_MG)
        ) {
            $badges[] = self::BADGE_VITAMIN_D_CALCIUM;
        }

        if (self::isHighSource((float) ($nutrition['zinc'] ?? 0), self::RDI_ZINC_MG)) {
            $badges[] = self::BADGE_ZINC;
        }

        if (
            self::isHighSource((float) ($nutrition['vitamin_a'] ?? 0), self::RDI_VITAMIN_A_MCG)
            || self::isHighSource((float) ($nutrition['vitamin_c'] ?? 0), self::RDI_VITAMIN_C_MG)
            || self::isHighSource((float) ($nutrition['vitamin_e'] ?? 0), self::RDI_VITAMIN_E_MG)
        ) {
            $badges[] = self::BADGE_ANTIOXIDANTS;
        }

        return $badges;
    }

    /**
     * @return array<string, string>
     */
    public static function badgeTooltips(): array
    {
        return [
            self::BADGE_FOLATE => 'Supports the continuous production of fresh, healthy red blood cells to maintain optimal energy.',
            self::BADGE_B12 => 'Essential for DNA synthesis and works with Folate to ensure robust red blood cell formation.',
            self::BADGE_B6 => 'Promotes efficient hemoglobin function and boosts daily energy metabolism.',
            self::BADGE_VITAMIN_D_CALCIUM => 'Strengthens bone density and supports skeletal resilience and a powerful immune system.',
            self::BADGE_ZINC => 'Key for cellular repair, efficient wound healing, and maintaining high-performing immune defense.',
            self::BADGE_ANTIOXIDANTS => 'Protects red blood cell membranes and promotes resilience against oxidative stress.',
        ];
    }

    public static function tooltipForBadge(string $badge): ?string
    {
        return self::badgeTooltips()[$badge] ?? null;
    }

    /**
     * @param  array<string, float>  $nutrition
     */
    public static function hasAnyHighlight(array $nutrition): bool
    {
        return self::highlightBadgeLabels($nutrition) !== [];
    }

    /**
     * Legacy flag map for Livewire / calculators (keys align with nutrient fields).
     *
     * @param  array<string, float>  $nutrition
     * @return array<string, bool>
     */
    public static function highlightFlags(array $nutrition): array
    {
        $labels = array_fill_keys(self::highlightBadgeLabels($nutrition), true);

        return [
            'folate' => isset($labels[self::BADGE_FOLATE]),
            'b12' => isset($labels[self::BADGE_B12]),
            'b6' => isset($labels[self::BADGE_B6]),
            'vitamin_d_calcium' => isset($labels[self::BADGE_VITAMIN_D_CALCIUM]),
            'zinc' => isset($labels[self::BADGE_ZINC]),
            'antioxidants' => isset($labels[self::BADGE_ANTIOXIDANTS]),
        ];
    }
}
