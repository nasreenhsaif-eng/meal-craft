import { describe, expect, it } from 'vitest';
import {
    categoryMacroTargetsFromPlan,
    dailyMacroTargetsFromPlan,
    dayMacroToleranceFromPlan,
    isMacroOutsideTolerance,
    macroCaloriePercentsFromGrams,
    macroSplitPercentagesFromPlan,
    mainSlotMacroTargetsFromPlan,
    nutritionPlanMatchesTier,
} from './craftCalorieTargets.js';

describe('dailyMacroTargetsFromPlan', () => {
    it('returns synced daily_macros at full craft when plan tier matches', () => {
        const plan = {
            plan_tier: 2000,
            daily_macros: { protein_g: 196, carbs_g: 147, fat_g: 65 },
            protein_percentage: 40,
            carb_percentage: 30,
            fat_percentage: 30,
        };

        expect(dailyMacroTargetsFromPlan(plan, 2000, 'full')).toEqual({
            calories: 2000,
            protein: 196,
            carbs: 147,
            fat: 65,
        });
    });

    it('ignores stale plan macros when tier mismatches', () => {
        const plan = {
            plan_tier: 1500,
            daily_macros: { protein_g: 147, carbs_g: 110, fat_g: 49 },
            protein_percentage: 40,
            carb_percentage: 30,
            fat_percentage: 30,
        };

        expect(nutritionPlanMatchesTier(plan, 2000)).toBe(false);
        expect(dailyMacroTargetsFromPlan(plan, 2000, 'full').protein).toBe(196);
    });

    it('scales craft day macros when craft is not full', () => {
        const plan = {
            plan_tier: 2000,
            daily_macros: { protein_g: 196, carbs_g: 147, fat_g: 65 },
        };

        const targets = dailyMacroTargetsFromPlan(plan, 2000, 'day');

        expect(targets.calories).toBeLessThan(2000);
        expect(targets.protein).toBeLessThan(196);
    });
});

describe('dayMacroToleranceFromPlan', () => {
    it('reads tolerances from plan payload with defaults', () => {
        expect(dayMacroToleranceFromPlan(null)).toEqual({ protein: 15, carbs: 20, fat: 15 });
        expect(
            dayMacroToleranceFromPlan({ day_macro_tolerance: { protein_g: 12, carbs_g: 18, fat_g: 10 } }),
        ).toEqual({ protein: 12, carbs: 18, fat: 10 });
    });
});

describe('isMacroOutsideTolerance', () => {
    it('flags values outside the gram tolerance band', () => {
        expect(isMacroOutsideTolerance(180, 196, 15)).toBe(true);
        expect(isMacroOutsideTolerance(190, 196, 15)).toBe(false);
    });
});

describe('categoryMacroTargetsFromPlan', () => {
    it('returns breakfast and scaled main targets for full craft days', () => {
        const categories = {
            breakfasts: [{}],
            meals: [{}, {}],
            sideSalads: [{}],
            desserts: [{}],
        };

        const targets = categoryMacroTargetsFromPlan('full', 2000, null, categories);

        expect(targets.breakfasts?.calories).toBe(450);
        expect(targets.meals?.calories).toBe(1250);
        expect(targets.sideSalads?.calories).toBe(150);
        expect(targets.desserts?.calories).toBe(150);
    });

    it('uses synced slot macros when nutrition plan matches tier', () => {
        const plan = {
            plan_tier: 1500,
            scalable_slot_targets: {
                breakfast: { calories: 300, macros: { protein_g: 26, carbs_g: 30, fat_g: 10 } },
                main_each: { calories: 450, macros: { protein_g: 51, carbs_g: 28, fat_g: 15 } },
            },
        };

        expect(mainSlotMacroTargetsFromPlan(plan, 1500)).toEqual({
            calories: 450,
            protein: 51,
            carbs: 28,
            fat: 15,
        });
    });
});

describe('macroSplitPercentagesFromPlan', () => {
    it('returns nutrient dense protocol split from plan payload', () => {
        expect(
            macroSplitPercentagesFromPlan({
                protein_percentage: 32,
                carb_percentage: 28,
                fat_percentage: 40,
            }),
        ).toEqual({ protein: 32, carbs: 28, fat: 40 });
    });

    it('falls back to balanced defaults when plan is missing', () => {
        expect(macroSplitPercentagesFromPlan(null)).toEqual({ protein: 35, carbs: 35, fat: 30 });
    });
});

describe('macroCaloriePercentsFromGrams', () => {
    it('derives calorie split from gram totals', () => {
        expect(
            macroCaloriePercentsFromGrams({
                protein: 120,
                carbs: 105,
                fat: 67,
            }),
        ).toEqual({ protein: 32, carbs: 28, fat: 40 });
    });

    it('returns zeros when macros are empty', () => {
        expect(macroCaloriePercentsFromGrams({ protein: 0, carbs: 0, fat: 0 })).toEqual({
            protein: 0,
            carbs: 0,
            fat: 0,
        });
    });
});
