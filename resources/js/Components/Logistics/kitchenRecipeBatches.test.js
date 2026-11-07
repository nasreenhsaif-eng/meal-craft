import { describe, expect, it } from 'vitest';
import {
    aggregateBulkIngredientsFromRecipeBatches,
    aggregateKitchenBaseRecipeBatches,
    aggregateKitchenRecipeBatches,
    kitchenMealIngredientSignature,
    kitchenRecipeTypeLabel,
    mergeKitchenRecipeBatches,
    scaleKitchenRecipeBatchIngredients,
} from './kitchenRecipeBatches.js';

/** @returns {import('./kitchenRecipeBatches.js').AdaptedKitchenMeal} */
function meal(overrides = {}) {
    return {
        mealId: 10,
        mealName: 'Salmon bowl',
        slot: 'main',
        imageUrl: '/images/meals/salmon.jpg',
        instructions: '1. Season. 2. Bake.',
        customerName: 'Guest',
        ingredients: [
            { id: 1, name: 'Atlantic salmon', adaptedAmountGrams: 150, adaptedAmount: null, unit: 'g' },
            { id: 2, name: 'Rice', adaptedAmountGrams: 80, adaptedAmount: null, unit: 'g' },
        ],
        ...overrides,
    };
}

describe('kitchenMealIngredientSignature', () => {
    it('is order-independent for the same grams', () => {
        const a = kitchenMealIngredientSignature([
            { id: 2, name: 'Rice', adaptedAmountGrams: 80, adaptedAmount: null, unit: 'g' },
            { id: 1, name: 'Atlantic salmon', adaptedAmountGrams: 150, adaptedAmount: null, unit: 'g' },
        ]);
        const b = kitchenMealIngredientSignature([
            { id: 1, name: 'Atlantic salmon', adaptedAmountGrams: 150, adaptedAmount: null, unit: 'g' },
            { id: 2, name: 'Rice', adaptedAmountGrams: 80, adaptedAmount: null, unit: 'g' },
        ]);

        expect(a).toBe(b);
    });
});

describe('aggregateKitchenRecipeBatches', () => {
    it('counts two customers with the same meal and grams as two portions', () => {
        const batches = aggregateKitchenRecipeBatches([
            meal({ customerName: 'Jordan' }),
            meal({ customerName: 'Sam' }),
        ]);

        expect(batches).toHaveLength(1);
        expect(batches[0].mealName).toBe('Salmon bowl');
        expect(batches[0].portions).toBe(2);
        expect(batches[0].perPortionIngredients).toHaveLength(2);
    });

    it('keeps separate rows when adapted grams differ', () => {
        const batches = aggregateKitchenRecipeBatches([
            meal({ customerName: 'Jordan' }),
            meal({
                customerName: 'Sam',
                ingredients: [
                    { id: 1, name: 'Atlantic salmon', adaptedAmountGrams: 180, adaptedAmount: null, unit: 'g' },
                    { id: 2, name: 'Rice', adaptedAmountGrams: 80, adaptedAmount: null, unit: 'g' },
                ],
            }),
        ]);

        expect(batches).toHaveLength(2);
        expect(batches.every((batch) => batch.portions === 1)).toBe(true);
    });
});

describe('scaleKitchenRecipeBatchIngredients', () => {
    it('multiplies per-portion grams by portions', () => {
        const [batch] = aggregateKitchenRecipeBatches([meal(), meal()]);
        const scaled = scaleKitchenRecipeBatchIngredients(batch);

        expect(scaled.find((row) => row.name === 'Atlantic salmon')?.totalGrams).toBe(300);
        expect(scaled.find((row) => row.name === 'Rice')?.totalGrams).toBe(160);
        expect(scaled.find((row) => row.name === 'Atlantic salmon')?.displayLine).toContain('300');
    });
});

describe('aggregateBulkIngredientsFromRecipeBatches', () => {
    it('sums the same ingredient across recipe batches', () => {
        const batches = aggregateKitchenRecipeBatches([
            meal({ customerName: 'Jordan' }),
            meal({ customerName: 'Sam' }),
            meal({
                mealId: 20,
                mealName: 'Rice side',
                customerName: 'Alex',
                ingredients: [
                    { id: 2, name: 'Rice', adaptedAmountGrams: 100, adaptedAmount: null, unit: 'g' },
                ],
            }),
        ]);

        const bulk = aggregateBulkIngredientsFromRecipeBatches(batches);
        const salmon = bulk.find((row) => row.ingredient === 'Atlantic salmon');
        const rice = bulk.find((row) => row.ingredient === 'Rice');

        expect(salmon?.totalAmount).toBe(300);
        expect(salmon?.recipeCount).toBe(1);
        expect(salmon?.portionCount).toBe(2);
        expect(salmon?.usedInRecipes).toEqual(['Salmon bowl']);
        expect(rice?.totalAmount).toBe(260);
        expect(rice?.recipeCount).toBe(2);
        expect(rice?.portionCount).toBe(3);
        expect(rice?.usedInRecipes).toEqual(['Rice side', 'Salmon bowl']);
    });
});

describe('aggregateKitchenBaseRecipeBatches', () => {
    it('adds base recipes with total grams and scaled components', () => {
        const baseBatches = aggregateKitchenBaseRecipeBatches([
            {
                id: 901,
                name: 'Marinara Sauce (Base)',
                instructions: '1. Simmer.',
                finished_weight_grams: 500,
                total_adapted_grams: 650,
                usage_count: 13,
                components: [
                    { id: 11, name: 'Tomato', amount_grams: 400 },
                    { id: 12, name: 'Onion', amount_grams: 60 },
                ],
            },
        ]);

        expect(baseBatches).toHaveLength(1);
        expect(baseBatches[0].kind).toBe('base');
        expect(baseBatches[0].typeLabel).toBe('base recipe');
        expect(baseBatches[0].portionsLabel).toBe('650g');
        expect(baseBatches[0].perPortionIngredients.find((row) => row.name === 'Tomato')?.adaptedAmountGrams).toBe(
            520,
        );

        const merged = mergeKitchenRecipeBatches(
            aggregateKitchenRecipeBatches([
                meal({ slot: 'dessert', mealName: 'Brownie', mealId: 3 }),
                meal({ slot: 'breakfast', mealName: 'Eggs', mealId: 2 }),
                meal({ slot: 'main', mealName: 'Salmon bowl', mealId: 1 }),
            ]),
            baseBatches,
        );
        expect(merged.map((batch) => batch.typeLabel)).toEqual([
            'breakfast',
            'meal',
            'dessert',
            'base recipe',
        ]);

        const scaled = scaleKitchenRecipeBatchIngredients(baseBatches[0]);
        expect(scaled.find((row) => row.name === 'Tomato')?.totalGrams).toBe(520);
    });
});

describe('kitchenRecipeTypeLabel', () => {
    it('maps slots to kitchen type labels', () => {
        expect(kitchenRecipeTypeLabel({ slot: 'breakfast' })).toBe('breakfast');
        expect(kitchenRecipeTypeLabel({ slot: 'main' })).toBe('meal');
        expect(kitchenRecipeTypeLabel({ slot: 'side_salad' })).toBe('side salad');
        expect(kitchenRecipeTypeLabel({ slot: 'soup' })).toBe('soup');
        expect(kitchenRecipeTypeLabel({ slot: 'dessert' })).toBe('dessert');
        expect(kitchenRecipeTypeLabel({ kind: 'base' })).toBe('base recipe');
    });
});
