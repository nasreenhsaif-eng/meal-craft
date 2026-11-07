import { describe, expect, it } from 'vitest';
import {
    aggregateSelectedDayShoppingIngredients,
    canonicalShoppingIngredientName,
    explodePreparedBasePortion,
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

describe('canonicalShoppingIngredientName', () => {
    it('strips spoon prefixes and prep-state suffixes', () => {
        expect(canonicalShoppingIngredientName('(1 tbsp) Fresh Mint')).toBe('Fresh Mint');
        expect(canonicalShoppingIngredientName('Rosemary Garlic Chicken (Base)')).toBe(
            'Rosemary Garlic Chicken',
        );
        expect(canonicalShoppingIngredientName('Almond Flour (cooked plated portion)')).toBe(
            'Almond Flour',
        );
        expect(canonicalShoppingIngredientName('Garlic (Raw)')).toBe('Garlic (Raw)');
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
            { key: 'name:eggs|g', ingredientId: 10, name: 'Eggs', unit: 'g', amount: 150 },
            { key: 'name:spinach|g', ingredientId: 11, name: 'Spinach', unit: 'g', amount: 80 },
        ]);
    });

    it('merges the same ingredient from id rows and detailView name rows', () => {
        const list = aggregateSelectedDayShoppingIngredients({
            a: [
                {
                    kitchenIngredientRows: [
                        { ingredientId: 416, selectedName: 'Walnuts', amount: '10', unit: 'g' },
                    ],
                },
            ],
            b: [{ detailView: { ingredients: ['6g Walnuts'] } }],
        });

        expect(list).toEqual([
            { key: 'name:walnuts|g', ingredientId: 416, name: 'Walnuts', unit: 'g', amount: 16 },
        ]);
    });

    it('merges spoon-prefixed labels into one quantity', () => {
        const list = aggregateSelectedDayShoppingIngredients({
            a: [
                {
                    kitchenIngredientRows: [
                        {
                            ingredientId: 0,
                            selectedName: '(1 tbsp) Fresh Mint',
                            amount: '5',
                            unit: 'g',
                        },
                    ],
                },
            ],
            b: [
                {
                    kitchenIngredientRows: [
                        { ingredientId: 12, selectedName: 'Fresh Mint', amount: '2', unit: 'g' },
                    ],
                },
            ],
        });

        expect(list).toEqual([
            { key: 'name:fresh mint|g', ingredientId: 12, name: 'Fresh Mint', unit: 'g', amount: 7 },
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

    it('explodes prepared base recipes into scaled shopping components', () => {
        const list = aggregateSelectedDayShoppingIngredients(
            {
                meals: [
                    {
                        id: '4',
                        kitchenIngredientRows: [
                            {
                                ingredientId: 340,
                                selectedName: 'Rosemary Garlic Chicken (Base)',
                                amount: '150',
                                unit: 'g',
                            },
                            {
                                ingredientId: 99,
                                selectedName: 'Rocca',
                                amount: '40',
                                unit: 'g',
                            },
                        ],
                    },
                ],
            },
            [
                {
                    id: 340,
                    name: 'Rosemary Garlic Chicken (Base)',
                    is_prepared_base: true,
                    finished_weight_grams: 738,
                    components: [
                        { id: 1, name: 'Chicken Breast', amount_grams: 1000 },
                        { id: 2, name: 'Sea Salt', amount_grams: 11 },
                        { id: 3, name: 'Olive Oil (Extra Virgin)', amount_grams: 30 },
                    ],
                },
            ],
        );

        expect(list.find((row) => row.name === 'Rosemary Garlic Chicken')).toBeUndefined();
        expect(list.find((row) => row.name === 'Rosemary Garlic Chicken (Base)')).toBeUndefined();
        expect(list.find((row) => row.name === 'Rocca')).toEqual({
            key: 'name:rocca|g',
            ingredientId: 99,
            name: 'Rocca',
            unit: 'g',
            amount: 40,
        });
        expect(list.find((row) => row.name === 'Chicken Breast')?.amount).toBe(203.25);
        expect(list.find((row) => row.name === 'Sea Salt')?.amount).toBe(2.24);
        expect(list.find((row) => row.name === 'Olive Oil (Extra Virgin)')?.amount).toBe(6.1);
    });

    it('recursively explodes nested prepared bases', () => {
        const list = aggregateSelectedDayShoppingIngredients(
            {
                meals: [
                    {
                        id: '5',
                        kitchenIngredientRows: [
                            {
                                ingredientId: 500,
                                selectedName: 'Salad Kit (Base)',
                                amount: '100',
                                unit: 'g',
                            },
                        ],
                    },
                ],
            },
            [
                {
                    id: 500,
                    finished_weight_grams: 100,
                    components: [
                        { id: 501, name: 'Dressing (Base)', amount_grams: 40 },
                        { id: 20, name: 'Lettuce', amount_grams: 60 },
                    ],
                },
                {
                    id: 501,
                    finished_weight_grams: 200,
                    components: [
                        { id: 30, name: 'Olive Oil (Extra Virgin)', amount_grams: 150 },
                        { id: 31, name: 'Sea Salt', amount_grams: 50 },
                    ],
                },
            ],
        );

        expect(list.find((row) => row.name === 'Lettuce')?.amount).toBe(60);
        expect(list.find((row) => row.name === 'Olive Oil (Extra Virgin)')?.amount).toBe(30);
        expect(list.find((row) => row.name === 'Sea Salt')?.amount).toBe(10);
        expect(list.find((row) => /base/i.test(row.name))).toBeUndefined();
    });
});

describe('explodePreparedBasePortion', () => {
    it('falls back to raw component sum when finished weight is missing', () => {
        const rows = explodePreparedBasePortion(
            50,
            {
                id: 7,
                components: [
                    { id: 1, name: 'A', amount_grams: 80 },
                    { id: 2, name: 'B', amount_grams: 20 },
                ],
            },
            new Map(),
        );

        expect(rows.find((row) => row.name === 'A')?.amount).toBe(40);
        expect(rows.find((row) => row.name === 'B')?.amount).toBe(10);
    });
});

describe('mergeShoppingListItems', () => {
    it('adds amounts for matching keys', () => {
        const merged = mergeShoppingListItems(
            [{ key: 'id:1', ingredientId: 1, name: 'Eggs', unit: 'g', amount: 100 }],
            [{ key: 'id:1', ingredientId: 1, name: 'Eggs', unit: 'g', amount: 40 }],
        );

        expect(merged).toEqual([
            { key: 'name:eggs|g', ingredientId: 1, name: 'Eggs', unit: 'g', amount: 140 },
        ]);
    });
});
