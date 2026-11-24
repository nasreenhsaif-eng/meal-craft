import { describe, expect, it } from 'vitest';
import { shoppingRowFromDisplayLine, shoppingRowsFromDetailView } from './customerShoppingList.js';

describe('shoppingRowFromDisplayLine', () => {
    it('keeps gram amounts from a recipe line', () => {
        expect(shoppingRowFromDisplayLine('125g Salmon (raw, before cooking)')).toMatchObject({
            name: 'Salmon',
            amount: 125,
            unit: 'g',
        });
    });

    it('keeps a line that has no gram amount', () => {
        expect(shoppingRowFromDisplayLine('2 eggs raw')).toMatchObject({
            name: '2 eggs raw',
            amount: 1,
            unit: 'item',
        });
    });

    it('skips the empty-ingredients placeholder', () => {
        expect(shoppingRowFromDisplayLine('No ingredients on file.')).toBeNull();
    });
});

describe('shoppingRowsFromDetailView', () => {
    it('uses salad and dressing sections when they are present', () => {
        const rows = shoppingRowsFromDetailView({
            ingredients: ['80g Romaine Lettuce'],
            ingredientSections: [
                { title: 'Salad', items: ['80g Romaine Lettuce'] },
                { title: 'Dressing', items: [{ line: '15g Olive Oil', ingredientId: 4 }] },
            ],
        });

        expect(rows.map((row) => row.name)).toEqual(['Romaine Lettuce', 'Olive Oil']);
    });
});
