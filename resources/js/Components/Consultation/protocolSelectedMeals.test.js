import { describe, expect, it } from 'vitest';

import {
    applyDeckSelectionToggle,
    selectedMealsFromDisplayDecks,
    sumSelectedMacrosFromDisplayDecks,
} from './ChooseYourMeals.jsx';

describe('protocol selected-meal layout selection', () => {
    it('replaces breakfast when max is 1 (omelette → chia)', () => {
        expect(applyDeckSelectionToggle(['omelette'], 'chia', 1)).toEqual(['chia']);
        expect(applyDeckSelectionToggle(['chia'], 'omelette', 1)).toEqual(['omelette']);
    });

    it('requires deselect before swapping a third main when two are selected', () => {
        expect(applyDeckSelectionToggle(['1', '5'], '3', 2)).toEqual(['1', '5']);
        expect(applyDeckSelectionToggle(['1', '5'], '5', 2)).toEqual(['1']);
        expect(applyDeckSelectionToggle(['1'], '3', 2)).toEqual(['1', '3']);
    });

    it('sums only selected protocol cards for the day footer', () => {
        const omelette = {
            id: 'b1',
            mealType: 'Breakfast',
            caloriesNumber: 300,
            macros: { calories: 300, protein: '24g', carbs: '4g', fat: '20g' },
            isRecommended: true,
        };
        const chia = {
            id: 'b2',
            mealType: 'Breakfast',
            caloriesNumber: 280,
            macros: { calories: 280, protein: '18g', carbs: '28g', fat: '12g' },
            isRecommended: false,
        };
        const chicken = {
            id: '1',
            mealType: 'Meal',
            caloriesNumber: 520,
            macros: { calories: 520, protein: '48g', carbs: '32g', fat: '22g' },
            isRecommended: true,
        };
        const liver = {
            id: '5',
            mealType: 'Meal',
            caloriesNumber: 480,
            macros: { calories: 480, protein: '42g', carbs: '28g', fat: '20g' },
            isRecommended: true,
        };
        const fish = {
            id: '3',
            mealType: 'Meal',
            caloriesNumber: 500,
            macros: { calories: 500, protein: '40g', carbs: '20g', fat: '25g' },
            isRecommended: false,
        };

        const decks = {
            breakfasts: [omelette, chia],
            meals: [chicken, fish, liver],
            sideSalads: [],
            desserts: [],
            soup: [],
        };

        const selections = {
            breakfasts: ['b1'],
            meals: ['1', '5'],
            sideSalads: [],
            desserts: [],
            soup: [],
        };

        const selected = selectedMealsFromDisplayDecks(selections, decks);
        expect(selected.map((meal) => meal.id)).toEqual(['b1', '1', '5']);

        const totals = sumSelectedMacrosFromDisplayDecks(selections, decks);
        expect(totals.calories).toBe(300 + 520 + 480);
        expect(Math.round(totals.protein)).toBe(24 + 48 + 42);
    });
});
