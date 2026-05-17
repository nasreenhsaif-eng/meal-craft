import { describe, expect, it } from 'vitest';
import { reorderMealsInParentList } from './reorderMealRows';

describe('reorderMealsInParentList', () => {
    const parent = [
        { id: 'a', title: 'A' },
        { id: 'b', title: 'B' },
        { id: 'c', title: 'C' },
        { id: 'd', title: 'D' },
    ];

    it('reorders only visible rows within the parent list', () => {
        const visible = [
            { id: 'b', title: 'B' },
            { id: 'c', title: 'C' },
        ];

        const next = reorderMealsInParentList(parent, visible, 'b', 'c');

        expect(next.map((m) => m.id)).toEqual(['a', 'c', 'b', 'd']);
    });

    it('returns a copy when indices are unchanged', () => {
        const visible = [{ id: 'a', title: 'A' }];
        const next = reorderMealsInParentList(parent, visible, 'a', 'a');
        expect(next.map((m) => m.id)).toEqual(['a', 'b', 'c', 'd']);
        expect(next).not.toBe(parent);
    });
});
