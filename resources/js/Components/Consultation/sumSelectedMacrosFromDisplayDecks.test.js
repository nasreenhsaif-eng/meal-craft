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
