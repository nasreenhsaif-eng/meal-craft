import { describe, expect, it } from 'vitest';
import {
    dailyMacroTargetsFromPlan,
    dayMacroToleranceFromPlan,
    isMacroOutsideTolerance,
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
