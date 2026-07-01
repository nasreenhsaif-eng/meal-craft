import { describe, expect, it } from 'vitest';

import { reconcileConsultationDayMacros } from './balanceMainMealProtein.ts';

describe('reconcileConsultationDayMacros', () => {
    it('boosts carbs on the highest-carb main when protein and fat are on target but calories are short', () => {
        const categories = {
            breakfasts: [
                {
                    id: '1',
                    caloriesNumber: 444,
                    macros: { calories: 444, protein: '35g', carbs: '8g', fat: '30g' },
                },
            ],
            meals: [
                {
                    id: '2',
                    caloriesNumber: 380,
                    macros: { calories: 380, protein: '42g', carbs: '18g', fat: '12g' },
                },
                {
                    id: '3',
                    caloriesNumber: 383,
                    macros: { calories: 383, protein: '43g', carbs: '35g', fat: '11g' },
                },
            ],
            desserts: [
                {
                    id: '4',
                    caloriesNumber: 201,
                    macros: { calories: 201, protein: '17g', carbs: '17g', fat: '8g' },
                },
            ],
        };

        const reconciled = reconcileConsultationDayMacros(
            categories,
            { calories: 1500, protein: 120, carbs: 105, fat: 67 },
            { protein: 15, carbs: 20, fat: 15 },
            1500,
            50,
            450,
        );

        const mains = reconciled.filter((meal) => meal.id === '2' || meal.id === '3');
        const totals = reconciled.reduce(
            (acc, meal) => ({
                calories: acc.calories + (meal.caloriesNumber ?? 0),
                carbs:
                    acc.carbs +
                    Number.parseFloat(String(meal.macros?.carbs ?? '0').replace(/[^\d.-]/g, '')),
            }),
            { calories: 0, carbs: 0 },
        );

        expect(totals.calories).toBeGreaterThan(1329);
        expect(totals.carbs).toBeGreaterThan(71);
        expect(mains.find((meal) => meal.id === '3')?.macros?.carbs).not.toBe('35g');
    });

    it('trims carbs and fat on mains when the day runs over calorie target', () => {
        const categories = {
            breakfasts: [
                {
                    id: '1',
                    caloriesNumber: 444,
                    macros: { calories: 444, protein: '35g', carbs: '8g', fat: '30g' },
                },
            ],
            meals: [
                {
                    id: '2',
                    caloriesNumber: 900,
                    macros: { calories: 900, protein: '42g', carbs: '55g', fat: '45g' },
                },
                {
                    id: '3',
                    caloriesNumber: 500,
                    macros: { calories: 500, protein: '43g', carbs: '70g', fat: '20g' },
                },
            ],
            soup: [
                {
                    id: '4',
                    caloriesNumber: 390,
                    macros: { calories: 390, protein: '23g', carbs: '68g', fat: '5g' },
                },
            ],
        };

        const reconciled = reconcileConsultationDayMacros(
            categories,
            { calories: 1500, protein: 120, carbs: 105, fat: 67 },
            { protein: 15, carbs: 20, fat: 15 },
            1500,
            50,
            450,
        );

        const totals = reconciled.reduce(
            (acc, meal) => ({
                calories: acc.calories + (meal.caloriesNumber ?? 0),
                carbs:
                    acc.carbs +
                    Number.parseFloat(String(meal.macros?.carbs ?? '0').replace(/[^\d.-]/g, '')),
                fat:
                    acc.fat +
                    Number.parseFloat(String(meal.macros?.fat ?? '0').replace(/[^\d.-]/g, '')),
            }),
            { calories: 0, carbs: 0, fat: 0 },
        );

        expect(totals.calories).toBeLessThan(1933);
        expect(totals.carbs).toBeLessThan(151);
        expect(totals.fat).toBeLessThan(103);
    });
});
