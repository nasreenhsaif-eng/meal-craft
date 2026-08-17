import { describe, expect, it } from 'vitest';

import {
    buildWeeklyConsultationDisplayDecks,
    CONSULTATION_DECK_OPTION_LIMITS,
    normalizeConsultationMealId,
    padConsultationDeckOptions,
    selectedMealsFromDisplayDecks,
    sumSelectedMacrosFromDisplayDecks,
} from './ChooseYourMeals.jsx';

describe('sumSelectedMacrosFromDisplayDecks', () => {
    it('sums macros from the same card objects shown in weekly decks', () => {
        const breakfast = {
            id: '10',
            mealType: 'Breakfast',
            caloriesNumber: 310,
            macros: { calories: 310, protein: '22g', carbs: '3g', fat: '23g' },
        };
        const liver = {
            id: '20',
            mealType: 'Meal',
            caloriesNumber: 585,
            macros: { calories: 585, protein: '51g', carbs: '28g', fat: '29g' },
        };
        const salmon = {
            id: '30',
            mealType: 'Meal',
            caloriesNumber: 526,
            macros: { calories: 526, protein: '37g', carbs: '18g', fat: '34g' },
        };
        const salad = {
            id: '40',
            mealType: 'Side salad',
            caloriesNumber: 142,
            macros: { calories: 142, protein: '7g', carbs: '14g', fat: '8g' },
        };
        const chia = {
            id: '50',
            mealType: 'Dessert',
            caloriesNumber: 254,
            macros: { calories: 254, protein: '17g', carbs: '27g', fat: '10g' },
        };

        // Catalog clone with dishonest lower macros (same ids).
        const catalogLiver = {
            ...liver,
            caloriesNumber: 400,
            macros: { calories: 400, protein: '40g', carbs: '20g', fat: '15g' },
        };

        const decks = buildWeeklyConsultationDisplayDecks({
            meals: [breakfast, catalogLiver, salmon, salad, chia],
            assignedMealsByCategory: {
                breakfasts: [breakfast],
                meals: [liver, salmon],
                sideSalads: [salad],
                desserts: [chia],
                soup: [],
            },
            includeBreakfast: true,
        });

        // Number ids in selections must still resolve to displayed schedule cards.
        const totals = sumSelectedMacrosFromDisplayDecks(
            {
                breakfasts: [10],
                meals: [20, 30],
                sideSalads: [40],
                desserts: [50],
                soup: [],
            },
            decks,
        );

        expect(normalizeConsultationMealId(20)).toBe('20');
        expect(totals.calories).toBe(1817);
        expect(Math.round(totals.protein)).toBe(134);
        expect(Math.round(totals.fat)).toBe(104);

        const selected = selectedMealsFromDisplayDecks(
            {
                breakfasts: [10],
                meals: [20, 30],
                sideSalads: [40],
                desserts: [50],
                soup: [],
            },
            decks,
        );
        expect(selected.map((meal) => meal.id)).toEqual(['10', '20', '30', '40', '50']);
        expect(selected[1]).toBe(liver);
        expect(selected[1]).not.toBe(catalogLiver);
    });

    it('sums Sunday salmon+liver card macros to the honest plate total (~1835), not the tier target', () => {
        const breakfast = {
            id: 'b1',
            mealType: 'Breakfast',
            caloriesNumber: 358,
            macros: { calories: 358, protein: '25g', carbs: '8g', fat: '24g' },
        };
        const salmon = {
            id: 'm1',
            mealType: 'Meal',
            caloriesNumber: 526,
            macros: { calories: 526, protein: '37g', carbs: '22g', fat: '33g' },
        };
        const liver = {
            id: 'm2',
            mealType: 'Meal',
            caloriesNumber: 584,
            macros: { calories: 584, protein: '51g', carbs: '46g', fat: '22g' },
        };
        const salad = {
            id: 's1',
            mealType: 'Side salad',
            caloriesNumber: 113,
            macros: { calories: 113, protein: '5g', carbs: '12g', fat: '6g' },
        };
        const dessert = {
            id: 'd1',
            mealType: 'Dessert',
            caloriesNumber: 254,
            macros: { calories: 254, protein: '17g', carbs: '27g', fat: '10g' },
        };

        // Dishonest catalog clones that would invent a near-1500 total if preferred.
        const catalogSalmon = {
            ...salmon,
            caloriesNumber: 400,
            macros: { calories: 400, protein: '35g', carbs: '15g', fat: '20g' },
        };
        const catalogLiver = {
            ...liver,
            caloriesNumber: 400,
            macros: { calories: 400, protein: '40g', carbs: '20g', fat: '15g' },
        };

        const decks = buildWeeklyConsultationDisplayDecks({
            meals: [breakfast, catalogSalmon, catalogLiver, salad, dessert],
            assignedMealsByCategory: {
                breakfasts: [breakfast],
                meals: [salmon, liver],
                sideSalads: [salad],
                desserts: [dessert],
                soup: [],
            },
            includeBreakfast: true,
        });

        const totals = sumSelectedMacrosFromDisplayDecks(
            {
                breakfasts: ['b1'],
                meals: ['m1', 'm2'],
                sideSalads: ['s1'],
                desserts: ['d1'],
                soup: [],
            },
            decks,
        );

        expect(totals.calories).toBe(1835);
        expect(totals.calories).not.toBe(1515);
        expect(Math.abs(totals.calories - 1500)).toBeGreaterThan(50);
        expect(Math.round(totals.protein)).toBe(135);
        expect(Math.round(totals.carbs)).toBe(115);
        expect(Math.round(totals.fat)).toBe(95);

        const selected = selectedMealsFromDisplayDecks(
            {
                breakfasts: ['b1'],
                meals: ['m1', 'm2'],
                sideSalads: ['s1'],
                desserts: ['d1'],
                soup: [],
            },
            decks,
        );
        expect(selected.map((meal) => meal.caloriesNumber)).toEqual([358, 526, 584, 113, 254]);
        expect(selected[1]).toBe(salmon);
        expect(selected[1]).not.toBe(catalogSalmon);
        expect(selected[2]).toBe(liver);
        expect(selected[2]).not.toBe(catalogLiver);
    });

    it('keeps six weekday main options in the meals deck', () => {
        const mains = Array.from({ length: 6 }, (_, index) => ({
            id: String(100 + index),
            mealType: 'Meal',
            caloriesNumber: 500 + index,
            macros: { calories: 500 + index, protein: '40g', carbs: '30g', fat: '20g' },
        }));

        const decks = buildWeeklyConsultationDisplayDecks({
            meals: mains,
            assignedMealsByCategory: { meals: mains },
            includeBreakfast: false,
        });

        expect(CONSULTATION_DECK_OPTION_LIMITS.meal).toBe(6);
        expect(decks.meals).toHaveLength(6);
        expect(decks.meals.map((meal) => meal.id)).toEqual(['100', '101', '102', '103', '104', '105']);
    });

    it('pads schedule mains up to six from the catalog', () => {
        const preferred = [
            { id: '1', mealType: 'Meal', caloriesNumber: 500 },
            { id: '2', mealType: 'Meal', caloriesNumber: 510 },
            { id: '3', mealType: 'Meal', caloriesNumber: 520 },
            { id: '4', mealType: 'Meal', caloriesNumber: 530 },
        ];
        const filler = [
            ...preferred,
            { id: '5', mealType: 'Meal', caloriesNumber: 540 },
            { id: '6', mealType: 'Meal', caloriesNumber: 550 },
            { id: '7', mealType: 'Meal', caloriesNumber: 560 },
        ];

        expect(padConsultationDeckOptions(preferred, filler, 6).map((meal) => meal.id)).toEqual([
            '1',
            '2',
            '3',
            '4',
            '5',
            '6',
        ]);
    });
});
