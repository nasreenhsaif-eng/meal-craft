import { describe, expect, it } from 'vitest';
import {
    aggregateSelectedDayShoppingIngredients,
    mergeShoppingListItems,
    parseIngredientDisplayLine,
} from './dayShoppingList.js';

describe('parseIngredientDisplayLine', () => {
    it('parses grams prefix lines', () => {
        expect(parseIngredientDisplayLine('120g Beef Liver')).toEqual({
            name: 'Beef Liver',
            amount: 120,
            unit: 'g',
        });
    });

    it('returns name-only when no quantity', () => {
        expect(parseIngredientDisplayLine('Salt')).toEqual({
            name: 'Salt',
            amount: 0,
            unit: 'g',
        });
    });
});

describe('aggregateSelectedDayShoppingIngredients', () => {
    it('sums kitchen ingredient rows across selected meals', () => {
        const list = aggregateSelectedDayShoppingIngredients({
            breakfasts: [
                {
                    id: '1',
                    kitchenIngredientRows: [
                        {
                            ingredientId: 10,
                            selectedName: 'Eggs',
                            amount: '100',
                            unit: 'g',
                        },
                    ],
                },
            ],
            meals: [
                {
                    id: '2',
                    kitchenIngredientRows: [
                        {
                            ingredientId: 10,
                            selectedName: 'Eggs',
                            amount: '50',
                            unit: 'g',
                        },
                        {
                            ingredientId: 11,
                            selectedName: 'Spinach',
                            amount: '80',
                            unit: 'g',
                        },
                    ],
                },
            ],
        });

        expect(list).toEqual([
            { key: 'id:10', ingredientId: 10, name: 'Eggs', unit: 'g', amount: 150 },
            { key: 'id:11', ingredientId: 11, name: 'Spinach', unit: 'g', amount: 80 },
        ]);
    });

    it('falls back to detailView ingredient strings', () => {
        const list = aggregateSelectedDayShoppingIngredients({
            meals: [
                {
                    id: '3',
                    detailView: { ingredients: ['90g Quinoa', '30g Olive Oil'] },
                },
            ],
        });

        expect(list.map((row) => row.name)).toEqual(['Olive Oil', 'Quinoa']);
        expect(list.find((row) => row.name === 'Quinoa')?.amount).toBe(90);
    });
});

describe('mergeShoppingListItems', () => {
    it('adds amounts for matching keys', () => {
        const merged = mergeShoppingListItems(
            [{ key: 'id:1', ingredientId: 1, name: 'Eggs', unit: 'g', amount: 100 }],
            [{ key: 'id:1', ingredientId: 1, name: 'Eggs', unit: 'g', amount: 40 }],
        );

        expect(merged).toEqual([
            { key: 'id:1', ingredientId: 1, name: 'Eggs', unit: 'g', amount: 140 },
        ]);
    });
});
