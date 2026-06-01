import { describe, expect, it } from 'vitest';
import {
    applyGoalCalorieAdjustment,
    calculateDailyTargets,
    calculateMacroGrams,
    calculateMifflinStJeorBmr,
    calculateTdee,
    DIET_PROTOCOL_MACRO_PRESETS,
    resolveCustomerGoal,
} from './dailyTargetsCalculator.js';

describe('calculateMifflinStJeorBmr', () => {
    it('uses the female formula', () => {
        expect(calculateMifflinStJeorBmr(68, 165, 32, 'female')).toBeCloseTo(1390.25, 0);
    });

    it('uses the male formula', () => {
        expect(calculateMifflinStJeorBmr(80, 178, 35, 'male')).toBeCloseTo(1742.5, 0);
    });
});

describe('calculateDailyTargets', () => {
    it('derives macro grams from diet protocol percentages', () => {
        const targets = calculateDailyTargets({
            sex: 'female',
            age: 32,
            weight_kg: 68,
            height_cm: 165,
            activity_level: 'lightly_active',
            target_weight_kg: 68,
            diet_protocol: 'balanced',
        });

        expect(targets.dailyCalories).toBeGreaterThan(1200);
        expect(targets.carbPercentage).toBe(DIET_PROTOCOL_MACRO_PRESETS.balanced.carbPercentage);
        expect(targets.proteinGrams).toBe(
            calculateMacroGrams(
                targets.dailyCalories,
                targets.proteinPercentage,
                targets.carbPercentage,
                targets.fatPercentage,
            ).proteinGrams,
        );
    });

    it('applies ketobiotic macro split', () => {
        const targets = calculateDailyTargets({
            sex: 'female',
            age: 32,
            weight_kg: 68,
            height_cm: 165,
            activity_level: 'lightly_active',
            target_weight_kg: 68,
            diet_protocol: 'ketobiotic',
        });

        expect(targets.fatPercentage).toBe(70);
        expect(targets.carbPercentage).toBe(10);
    });

    it('respects an explicit daily calorie override', () => {
        const targets = calculateDailyTargets({
            sex: 'female',
            age: 32,
            weight_kg: 68,
            height_cm: 165,
            activity_level: 'lightly_active',
            daily_calorie_target: 1929,
            protein_percentage: 40,
            carb_percentage: 35,
            fat_percentage: 25,
        });

        expect(targets.dailyCalories).toBe(1929);
        expect(targets.proteinGrams).toBe(193);
        expect(targets.carbGrams).toBe(169);
        expect(targets.fatGrams).toBe(54);
    });
});

describe('resolveCustomerGoal', () => {
    it('infers weight loss from target weight below current weight', () => {
        expect(
            resolveCustomerGoal({
                weight_kg: 72,
                target_weight_kg: 65,
            }),
        ).toBe('lose_weight');
    });
});

describe('applyGoalCalorieAdjustment', () => {
    it('applies a 500 kcal deficit for weight loss goals', () => {
        expect(applyGoalCalorieAdjustment(2000, 'lose_weight')).toBe(1500);
    });

    it('applies a 300 kcal surplus for muscle gain goals', () => {
        expect(applyGoalCalorieAdjustment(2000, 'gain_muscle')).toBe(2300);
    });

    it('never drops below the minimum calorie floor', () => {
        expect(applyGoalCalorieAdjustment(1250, 'lose_weight')).toBe(1200);
    });
});

describe('calculateTdee', () => {
    it('multiplies BMR by the activity factor', () => {
        expect(calculateTdee(1390.25, 'lightly_active')).toBeCloseTo(1911.59, 0);
    });
});
