import { describe, expect, it } from 'vitest';
import { calculateMicronutrientTargets, NUTRIENT_RDI_BY_LABEL } from './nutrientDailyRdi.ts';

describe('calculateMicronutrientTargets', () => {
    it('uses 18 mg iron for female and 8 mg for male at the not-active baseline', () => {
        const female = calculateMicronutrientTargets({ sex: 'female', activityLevel: 'sedentary' });
        const male = calculateMicronutrientTargets({ sex: 'male', activityLevel: 'sedentary' });

        expect(female['Iron (mg)']).toBe(18);
        expect(male['Iron (mg)']).toBe(8);
    });

    it('raises female iron magnesium and sodium for extremely active versus somewhat active', () => {
        const somewhat = calculateMicronutrientTargets({
            sex: 'female',
            activityLevel: 'lightly_active',
            dailyCalories: 1500,
        });
        const extreme = calculateMicronutrientTargets({
            sex: 'female',
            activityLevel: 'very_active',
            dailyCalories: 1500,
        });

        expect(somewhat['Iron (mg)']).toBe(19.8);
        expect(extreme['Iron (mg)']).toBe(27);
        expect(somewhat['Magnesium (mg)']).toBe(320);
        expect(extreme['Magnesium (mg)']).toBe(384);
        expect(somewhat['Sodium (mg)']).toBe(2300);
        expect(extreme['Sodium (mg)']).toBe(3300);
        expect(somewhat['Fiber (g)']).toBe(25);
        expect(extreme['Fiber (g)']).toBe(25);
    });

    it('keeps the sex fiber floor at 1500 kcal and uses 14 g per 1000 kcal at 2800', () => {
        const femaleAt1500 = calculateMicronutrientTargets({
            sex: 'female',
            activityLevel: 'very_active',
            dailyCalories: 1500,
        });
        const maleAt1500 = calculateMicronutrientTargets({
            sex: 'male',
            activityLevel: 'very_active',
            dailyCalories: 1500,
        });
        const femaleAt2800 = calculateMicronutrientTargets({
            sex: 'female',
            activityLevel: 'very_active',
            dailyCalories: 2800,
        });

        expect(femaleAt1500['Fiber (g)']).toBe(25);
        expect(maleAt1500['Fiber (g)']).toBe(38);
        expect(femaleAt2800['Fiber (g)']).toBe(39.2);
    });

    it('keeps the library default table on female not-active iron 18', () => {
        expect(NUTRIENT_RDI_BY_LABEL['Iron (mg)']).toBe(18);
        expect(NUTRIENT_RDI_BY_LABEL['Magnesium (mg)']).toBe(320);
        expect(NUTRIENT_RDI_BY_LABEL['Sugar (g)']).toBe(25);
    });
});
