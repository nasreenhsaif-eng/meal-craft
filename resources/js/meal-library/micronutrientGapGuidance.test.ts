import { describe, expect, it } from 'vitest';
import type { DayMicronutrientRow } from './aggregateDayNutritionalData.ts';
import {
    buildDayMicronutrientGuidance,
    dayHasLiverMain,
    yogurtGramsForCalciumGap,
} from './micronutrientGapGuidance.ts';

/**
 * @param {string} label
 * @param {number} total
 * @param {number} rdiPercent
 */
function mockRow(label: string, total: number, rdiPercent: number): DayMicronutrientRow {
    return {
        sectionTitle: 'Vitamins',
        label,
        total,
        sectionOrder: 0,
        rowOrder: 0,
        formattedTotal: String(total),
        rdiPercent,
        formattedRdiPercent: `${Math.round(rdiPercent)}%`,
    };
}

describe('yogurtGramsForCalciumGap', () => {
    it('rounds up to nearest 25 g', () => {
        expect(yogurtGramsForCalciumGap(200)).toBe(200);
        expect(yogurtGramsForCalciumGap(210)).toBe(225);
    });

    it('returns zero when gap is closed', () => {
        expect(yogurtGramsForCalciumGap(0)).toBe(0);
    });
});

describe('dayHasLiverMain', () => {
    it('detects liver in main meals category', () => {
        expect(
            dayHasLiverMain({
                meals: [{ title: 'Grilled Chicken' }, { title: 'Beef & Liver Kefta w Herb Salad & Tahini' }],
            }),
        ).toBe(true);

        expect(
            dayHasLiverMain({
                meals: [{ title: 'Grilled Chicken' }, { title: 'Salmon Bowl' }],
            }),
        ).toBe(false);
    });
});

describe('buildDayMicronutrientGuidance', () => {
    it('returns empty guidance for non-enforced tiers', () => {
        const rows = [mockRow('Vitamin K2 (mcg)', 30, 25)];

        expect(buildDayMicronutrientGuidance(rows, 1200, null)).toEqual([]);
    });

    it('suggests liver meals when K2 is below floor on enforced tier', () => {
        const rows = [mockRow('Vitamin K2 (mcg)', 35, 29)];

        const guidance = buildDayMicronutrientGuidance(rows, 1500, { meals: [] });
        const k2 = guidance.find((item) => item.id === 'vitamin-k2');

        expect(k2).toBeDefined();
        expect(k2?.liverMeals?.length).toBe(4);
        expect(k2?.actions?.[0]?.type).toBe('edit_meals');
    });

    it('includes yogurt grams note when calcium is low', () => {
        const rows = [mockRow('Calcium (mg)', 650, 65)];

        const guidance = buildDayMicronutrientGuidance(rows, 1500, null);
        const calcium = guidance.find((item) => item.id === 'calcium');

        expect(calcium).toBeDefined();
        expect(calcium?.body).toMatch(/Greek yogurt/);
        expect(calcium?.body).toMatch(/\d+ g/);
    });

    it('includes D3 and MK-7 guidance when vitamin D is low', () => {
        const rows = [
            mockRow('Vitamin D (mcg)', 4, 27),
            mockRow('Vitamin K2 (mcg)', 100, 83),
        ];

        const guidance = buildDayMicronutrientGuidance(rows, 1800, null);

        expect(guidance.some((item) => item.id === 'vitamin-d-k2')).toBe(true);
        expect(guidance.find((item) => item.id === 'vitamin-d-k2')?.body).toMatch(/MK-7/);
    });
});
