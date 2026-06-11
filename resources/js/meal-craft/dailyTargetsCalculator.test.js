import { describe, expect, it } from 'vitest';
import {
    applyGoalCalorieAdjustment,
    calculateDailyTargets,
    calculateGoalCalorieRange,
    calculateMacroGrams,
    calculateMifflinStJeorBmr,
    calculateTdee,
    DIET_PROTOCOL_MACRO_PRESETS,
    goalSummaryCopy,
    WEIGHT_GOAL_SUMMARY_COPY,
    kcalToKj,
    resolveCustomerGoal,
    resolveWeightGoal,
} from './dailyTargetsCalculator.js';

describe('calculateMifflinStJeorBmr', () => {
    it('uses the female formula', () => {
        expect(calculateMifflinStJeorBmr(68, 165, 32, 'female')).toBeCloseTo(1390.25, 0);
    });

    it('uses the male formula', () => {
        expect(calculateMifflinStJeorBmr(80, 178, 35, 'male')).toBeCloseTo(1742.5, 0);
    });
});

describe('calculateGoalCalorieRange', () => {
    it('returns TDEE to TDEE + 100 for maintain', () => {
        expect(calculateGoalCalorieRange(2000, 'maintain')).toEqual({
            min: 2000,
            max: 2100,
            midpoint: 2050,
        });
    });

    it('returns a 500–750 kcal deficit range for lose', () => {
        expect(calculateGoalCalorieRange(2000, 'lose')).toEqual({
            min: 1250,
            max: 1500,
            midpoint: 1375,
        });
    });

    it('returns a 300–500 kcal surplus range for gain', () => {
        expect(calculateGoalCalorieRange(2000, 'gain')).toEqual({
            min: 2300,
            max: 2500,
            midpoint: 2400,
        });
    });
});

describe('kcalToKj', () => {
    it('converts using 4.184 kJ per kcal', () => {
        expect(kcalToKj(2000)).toBe(8368);
    });
});

describe('DIET_PROTOCOL_MACRO_PRESETS', () => {
    it('uses a 40/40/20 balanced macro split', () => {
        expect(DIET_PROTOCOL_MACRO_PRESETS.balanced).toEqual({
            proteinPercentage: 40,
            carbPercentage: 40,
            fatPercentage: 20,
        });
    });
});

describe('calculateMacroGrams', () => {
    it('converts balanced calorie splits to grams using 4/4/9 kcal constants', () => {
        const grams = calculateMacroGrams(2000, 40, 40, 20);

        expect(grams.proteinGrams).toBe(200);
        expect(grams.carbGrams).toBe(200);
        expect(grams.fatGrams).toBe(44);
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
            weight_goal: 'maintain',
            diet_protocol: 'balanced',
        });

        expect(targets.dailyCaloriesMin).toBeLessThanOrEqual(targets.dailyCaloriesMax);
        expect(targets.dailyKjMin).toBe(kcalToKj(targets.dailyCaloriesMin));
        expect(targets.dailyKjMax).toBe(kcalToKj(targets.dailyCaloriesMax));
        expect(targets.carbPercentage).toBe(40);
        expect(targets.proteinPercentage).toBe(40);
        expect(targets.fatPercentage).toBe(20);
        expect(targets.proteinGrams).toBe(
            calculateMacroGrams(
                targets.dailyCalories,
                targets.proteinPercentage,
                targets.carbPercentage,
                targets.fatPercentage,
            ).proteinGrams,
        );
    });

    it('applies a deficit range when weight goal is lose', () => {
        const targets = calculateDailyTargets({
            sex: 'female',
            age: 32,
            weight_kg: 72,
            height_cm: 168,
            activity_level: 'moderately_active',
            weight_goal: 'lose',
            diet_protocol: 'balanced',
        });

        expect(targets.weightGoal).toBe('lose');
        expect(targets.dailyCaloriesMax).toBeLessThan(targets.tdee);
        expect(targets.tdee - targets.dailyCaloriesMin).toBeGreaterThanOrEqual(500);
        expect(targets.tdee - targets.dailyCaloriesMax).toBeGreaterThanOrEqual(500);
    });

    it('applies ketobiotic macro split', () => {
        const targets = calculateDailyTargets({
            sex: 'female',
            age: 32,
            weight_kg: 68,
            height_cm: 165,
            activity_level: 'lightly_active',
            weight_goal: 'maintain',
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
            diet_protocol: 'balanced',
        });

        expect(targets.dailyCalories).toBe(1929);
        expect(targets.dailyCaloriesMin).toBe(1929);
        expect(targets.dailyCaloriesMax).toBe(1929);
        expect(targets.proteinPercentage).toBe(40);
        expect(targets.proteinGrams).toBe(193);
        expect(targets.carbGrams).toBe(193);
        expect(targets.fatGrams).toBe(43);
    });
});

describe('resolveWeightGoal', () => {
    it('reads explicit weight_goal values', () => {
        expect(resolveWeightGoal({ weight_goal: 'gain' })).toBe('gain');
        expect(resolveWeightGoal({ goal: 'lose_weight' })).toBe('lose');
    });

    it('infers weight loss from target weight below current weight', () => {
        expect(
            resolveWeightGoal({
                weight_kg: 72,
                target_weight_kg: 65,
            }),
        ).toBe('lose');
    });
});

describe('resolveCustomerGoal', () => {
    it('maps weight goals to customer goal enum values', () => {
        expect(resolveCustomerGoal({ weight_goal: 'lose' })).toBe('lose_weight');
        expect(resolveCustomerGoal({ weight_goal: 'gain' })).toBe('gain_muscle');
        expect(resolveCustomerGoal({ weight_goal: 'maintain' })).toBe('maintain');
    });
});

describe('applyGoalCalorieAdjustment', () => {
    it('returns the midpoint of the goal calorie range', () => {
        expect(applyGoalCalorieAdjustment(2000, 'lose_weight')).toBe(1375);
        expect(applyGoalCalorieAdjustment(2000, 'gain_muscle')).toBe(2400);
        expect(applyGoalCalorieAdjustment(2000, 'maintain')).toBe(2050);
    });

    it('never drops below the minimum calorie floor', () => {
        expect(applyGoalCalorieAdjustment(1250, 'lose_weight')).toBe(1200);
    });
});

describe('goalSummaryCopy', () => {
    it('returns the exact summary copy for each weight goal', () => {
        expect(goalSummaryCopy('lose')).toBe(
            'This calorie target will allow you to lose weight at a healthy and sustainable rate of 0.5 to 1 kilogram per week.',
        );
        expect(goalSummaryCopy('maintain')).toBe(
            'This calorie target allows you to maintain your current weight, within a margin of a kilogram.',
        );
        expect(goalSummaryCopy('gain')).toBe(
            'This calorie target will allow you to gain weight at a healthy and sustainable rate of 0.5 to 1 kilogram per week.',
        );
    });

    it('normalizes legacy goal enum values', () => {
        expect(goalSummaryCopy('lose_weight')).toBe(WEIGHT_GOAL_SUMMARY_COPY.lose);
        expect(goalSummaryCopy('gain_muscle')).toBe(WEIGHT_GOAL_SUMMARY_COPY.gain);
    });
});

describe('calculateTdee', () => {
    it('multiplies BMR by the activity factor', () => {
        expect(calculateTdee(1390.25, 'lightly_active')).toBeCloseTo(1911.59, 0);
    });
});
