<?php

namespace App\Services;

use App\Enums\MealCyclePhaseTag;
use App\Models\Meal;

/**
 * Phase compatibility from whole-meal micronutrient totals (same sums as Ingredient_Quantities / builder).
 *
 * Thresholds are absolute (not library-relative).
 */
final class MealCyclePhaseTaggingService
{
    /** Whole-meal iron (mg) */
    private const MENSTRUAL_IRON_MIN_MG = 4.0;

    /** Whole-meal vitamin C (mg) — supports non-heme iron absorption */
    private const MENSTRUAL_VITAMIN_C_MIN_MG = 20.0;

    private const FOLLICULAR_VITAMIN_E_MIN_MG = 4.0;

    /** Whole-meal vitamin A (mcg RAE typical from USDA-style data) */
    private const FOLLICULAR_VITAMIN_A_MIN_MCG = 300.0;

    /** Whole-meal fiber (g) */
    private const OVULATORY_FIBER_MIN_G = 8.0;

    /** Whole-meal vitamin B6 (mg) */
    private const OVULATORY_B6_MIN_MG = 0.4;

    private const LUTEAL_MAGNESIUM_MIN_MG = 80.0;

    private const LUTEAL_ZINC_MIN_MG = 3.0;

    public function refreshAutoTagsForEntireLibrary(): void
    {
        $meals = Meal::query()->get([
            'id',
            'cycle_phase_tags_manual',
            'total_iron',
            'total_vitamin_e',
            'total_vitamin_a',
            'total_fiber',
            'total_vitamin_c',
            'total_magnesium',
            'total_b6',
            'total_zinc',
        ]);

        foreach ($meals as $meal) {
            if ($meal->cycle_phase_tags_manual) {
                continue;
            }

            $result = $this->calculatePhaseCompatibility(
                ironMg: (float) $meal->total_iron,
                vitaminEMg: (float) $meal->total_vitamin_e,
                vitaminAMcg: (float) $meal->total_vitamin_a,
                fiberG: (float) $meal->total_fiber,
                vitaminCMg: (float) $meal->total_vitamin_c,
                b6Mg: (float) $meal->total_b6,
                magnesiumMg: (float) $meal->total_magnesium,
                zincMg: (float) $meal->total_zinc,
            );

            Meal::query()->whereKey($meal->id)->update([
                'cycle_phase_tags' => $result['tags'],
                'cycle_phase_compatibility_tooltips' => $result['tooltips'],
            ]);
        }
    }

    /**
     * @return array{tags: list<string>, tooltips: array<string, string>}
     */
    public function calculatePhaseCompatibility(
        float $ironMg,
        float $vitaminEMg,
        float $vitaminAMcg,
        float $fiberG,
        float $vitaminCMg,
        float $b6Mg,
        float $magnesiumMg,
        float $zincMg,
    ): array {
        $tags = [];
        $tooltips = [];

        if ($ironMg > self::MENSTRUAL_IRON_MIN_MG && $vitaminCMg > self::MENSTRUAL_VITAMIN_C_MIN_MG) {
            $k = MealCyclePhaseTag::Menstrual->value;
            $tags[] = $k;
            $tooltips[$k] = $this->menstrualTooltip($ironMg, $vitaminCMg);
        }

        if ($vitaminEMg > self::FOLLICULAR_VITAMIN_E_MIN_MG || $vitaminAMcg > self::FOLLICULAR_VITAMIN_A_MIN_MCG) {
            $k = MealCyclePhaseTag::Follicular->value;
            $tags[] = $k;
            $tooltips[$k] = $this->follicularTooltip($vitaminEMg, $vitaminAMcg);
        }

        if ($fiberG > self::OVULATORY_FIBER_MIN_G && $b6Mg > self::OVULATORY_B6_MIN_MG) {
            $k = MealCyclePhaseTag::Ovulatory->value;
            $tags[] = $k;
            $tooltips[$k] = $this->ovulatoryTooltip($fiberG, $b6Mg);
        }

        if ($magnesiumMg > self::LUTEAL_MAGNESIUM_MIN_MG && $zincMg > self::LUTEAL_ZINC_MIN_MG) {
            $k = MealCyclePhaseTag::Luteal->value;
            $tags[] = $k;
            $tooltips[$k] = $this->lutealTooltip($magnesiumMg, $zincMg);
        }

        return [
            'tags' => array_values(array_unique($tags)),
            'tooltips' => $tooltips,
        ];
    }

    private function menstrualTooltip(float $ironMg, float $vitaminCMg): string
    {
        return __(
            'Iron :fe mg, Vitamin C :c mg — Supports blood loss recovery and energy; vitamin C enhances iron absorption.',
            ['fe' => round($ironMg, 1), 'c' => round($vitaminCMg, 1)]
        );
    }

    private function follicularTooltip(float $vitaminEMg, float $vitaminAMcg): string
    {
        $parts = [];
        if ($vitaminEMg > self::FOLLICULAR_VITAMIN_E_MIN_MG) {
            $parts[] = __('Vitamin E :v mg', ['v' => round($vitaminEMg, 1)]);
        }
        if ($vitaminAMcg > self::FOLLICULAR_VITAMIN_A_MIN_MCG) {
            $parts[] = __('Vitamin A :v mcg', ['v' => round($vitaminAMcg, 0)]);
        }

        return __(
            ':nutrients — Supports follicle development and skin health.',
            ['nutrients' => implode(', ', $parts)]
        );
    }

    private function ovulatoryTooltip(float $fiberG, float $b6Mg): string
    {
        return __(
            'Fiber :f g, Vitamin B6 :b mg — Fiber and B6 help the liver process estrogen surges.',
            ['f' => round($fiberG, 1), 'b' => round($b6Mg, 2)]
        );
    }

    private function lutealTooltip(float $magnesiumMg, float $zincMg): string
    {
        return __(
            'Magnesium :m mg, Zinc :z mg — May ease PMS symptoms and support progesterone balance.',
            ['m' => round($magnesiumMg, 0), 'z' => round($zincMg, 1)]
        );
    }
}
