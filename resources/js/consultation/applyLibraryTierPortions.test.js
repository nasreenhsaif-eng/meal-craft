import { describe, expect, it } from 'vitest';
import { applyLibraryTierPortions, daysUseLibraryPortions } from './applyLibraryTierPortions.js';

const libraryMeal = (id, tabs) => ({
    id,
    title: `Meal ${id}`,
    macros: { calories: 500, protein: 40, carbs: 20, fat: 20 },
    calorieTiers: tabs.map((calorie_tier) => ({
        calorie_tier,
        macros: { calories: calorie_tier, protein: 30, carbs: 10, fat: 12 },
        nutrition: { calories: calorie_tier, iron: calorie_tier / 10 },
        kitchenIngredientRows: [{ ingredientId: 1, selectedName: 'Chicken', amount: String(calorie_tier / 5), unit: 'g' }],
    })),
    detailView: { macros: { calories: 500 }, nutrition: { iron: 1 } },
});

describe('applyLibraryTierPortions', () => {
    const days = [
        {
            dayNumber: 1,
            categories: {
                breakfasts: [libraryMeal('b1', [300, 400, 500])],
                meals: [libraryMeal('m1', [400, 500, 550, 600])],
                sideSalads: [{ id: 's1', macros: { calories: 150 } }],
                desserts: [{ id: 'd1', macros: { calories: 150 } }],
                soup: [{ id: 'soup1', macros: { calories: 150 } }],
            },
        },
    ];

    it('detects library-backed days', () => {
        expect(daysUseLibraryPortions(days)).toBe(true);
        expect(daysUseLibraryPortions([{ categories: { breakfasts: [{ id: 'x' }], meals: [] } }])).toBe(false);
    });

    it('loads the 1800 authored tabs without scaling', () => {
        const [day] = applyLibraryTierPortions(days, 1800);

        expect(day.categories.breakfasts[0].macros.calories).toBe(400);
        expect(day.categories.meals[0].macros.calories).toBe(500);
        expect(day.categories.breakfasts[0].isScaled).toBe(false);
        expect(day.categories.meals[0].libraryCalorieTier).toBe(500);
        expect(day.categories.desserts).toHaveLength(1);
        expect(day.categories.soup).toHaveLength(0);
    });

    it('loads the 2000 authored 600 kcal main tabs', () => {
        const [day] = applyLibraryTierPortions(days, 2000);

        expect(day.categories.breakfasts[0].macros.calories).toBe(500);
        expect(day.categories.meals[0].macros.calories).toBe(600);
        expect(day.categories.meals[0].libraryCalorieTier).toBe(600);
        expect(day.categories.desserts).toHaveLength(1);
    });

    it('hides dessert at 1250 and keeps one-size sides', () => {
        const [day] = applyLibraryTierPortions(days, 1250);

        expect(day.categories.breakfasts[0].macros.calories).toBe(300);
        expect(day.categories.meals[0].macros.calories).toBe(400);
        expect(day.categories.desserts).toHaveLength(0);
        expect(day.categories.sideSalads[0].macros.calories).toBe(150);
    });
});
