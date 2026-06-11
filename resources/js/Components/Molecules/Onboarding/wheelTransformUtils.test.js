import { describe, expect, it } from 'vitest';
import { getWheelItemOffset, getWheelItemTier, getWheelItemVisual } from './wheelTransformUtils.js';
import { WHEEL_ITEM_HEIGHT } from './wheelConstants.js';

describe('wheelTransformUtils', () => {
    it('returns zero offset for the centered item', () => {
        const scrollTop = 3 * WHEEL_ITEM_HEIGHT;
        const offset = getWheelItemOffset(3, scrollTop);

        expect(offset).toBeCloseTo(0, 5);
    });

    it('classifies tiers by distance from center', () => {
        expect(getWheelItemTier(0)).toBe('selected');
        expect(getWheelItemTier(1)).toBe('adjacent');
        expect(getWheelItemTier(-1)).toBe('adjacent');
        expect(getWheelItemTier(2)).toBe('far');
        expect(getWheelItemTier(3)).toBe('hidden');
    });

    it('applies the requested rotateX, opacity, and scale bands', () => {
        const center = getWheelItemVisual(0);
        const adjacentAbove = getWheelItemVisual(-1);
        const farBelow = getWheelItemVisual(2);

        expect(center.opacity).toBe(1);
        expect(center.rotateX).toBe(0);
        expect(center.scale).toBe(1);

        expect(adjacentAbove.opacity).toBe(0.45);
        expect(adjacentAbove.rotateX).toBe(25);
        expect(adjacentAbove.scale).toBe(0.96);

        expect(farBelow.opacity).toBe(0.15);
        expect(farBelow.rotateX).toBe(-50);
        expect(farBelow.scale).toBe(0.9);
    });

    it('returns positive and negative offsets around the centered row', () => {
        const scrollTop = 3 * WHEEL_ITEM_HEIGHT;

        expect(getWheelItemOffset(3, scrollTop)).toBeCloseTo(0, 5);
        expect(getWheelItemOffset(1, scrollTop)).toBeLessThan(0);
        expect(getWheelItemOffset(5, scrollTop)).toBeGreaterThan(0);
    });
});
