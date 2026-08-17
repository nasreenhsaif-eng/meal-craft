import { describe, expect, it } from 'vitest';

import {
    reconcileConsultationDayMacros,
    sumConsultationMealCardCalories,
    sumConsultationMealCardMacros,
} from './balanceMainMealProtein.ts';

describe('sumConsultationMealCardCalories (honest footer totals)', () => {
    it('matches the unreonciled adapted meal-card sum when kitchen floors bind', () => {
        const selectedCards = [
            {
                id: 'breakfast',
                caloriesNumber: 310,
                macros: { calories: 310, protein: '22g', carbs: '3g', fat: '23g' },
            },
            {
                id: 'salmon',
                caloriesNumber: 526,
                macros: { calories: 526, protein: '37g', carbs: '18g', fat: '34g' },
            },
            {
                id: 'liver',
                caloriesNumber: 585,
                macros: { calories: 585, protein: '51g', carbs: '28g', fat: '29g' },
            },
            {
                id: 'salad',
                caloriesNumber: 142,
                macros: { calories: 142, protein: '7g', carbs: '14g', fat: '8g' },
            },
            {
                id: 'chia',
                caloriesNumber: 254,
                macros: { calories: 254, protein: '17g', carbs: '27g', fat: '10g' },
            },
        ];

        const honestTotal = sumConsultationMealCardCalories(selectedCards);
        expect(honestTotal).toBe(1817);

        const honestMacros = sumConsultationMealCardMacros(selectedCards);
        expect(honestMacros.calories).toBe(1817);
        expect(Math.round(honestMacros.protein)).toBe(134);
        expect(Math.round(honestMacros.fat)).toBe(104);

        // Prefer card MacroGrid calories over a stale / dishonest caloriesNumber.
        expect(
            sumConsultationMealCardCalories([
                { id: 'x', caloriesNumber: 400, macros: { calories: 585 } },
            ]),
        ).toBe(585);

        // Client surplus may invent a lower total — footer must still report the card sum.
        const categories = {
            breakfasts: [selectedCards[0]],
            meals: [selectedCards[1], selectedCards[2]],
            sideSalads: [selectedCards[3]],
            desserts: [selectedCards[4]],
        };
        const reconciled = reconcileConsultationDayMacros(
            categories,
            { calories: 1500, protein: 131, carbs: 131, fat: 50 },
            { protein: 15, carbs: 20, fat: 15 },
            1500,
            50,
            450,
        );
        const fakeFooter = sumConsultationMealCardCalories(reconciled);

        expect(honestTotal).toBeGreaterThan(1500 + 50);
        // Even if reconcile changes macros, the consultation footer uses the adapted card sum.
        expect(honestTotal).not.toBe(fakeFooter);
    });
});

describe('reconcileConsultationDayMacros', () => {
    it('boosts carbs on the highest-carb main when protein is on target but calories are short', () => {
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

    it('trims carbs and protein on mains when the day runs over, without cutting fat', () => {
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
        // Cooking fat is a kitchen floor — surplus close must not strip it.
        expect(totals.fat).toBeGreaterThanOrEqual(99);
    });
});
