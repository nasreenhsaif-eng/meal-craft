import { describe, expect, it } from 'vitest';

import { resolveProtocolBreakfastSeedId } from './seedProtocolBreakfast.js';

const shakshuka = { id: '345', title: 'Deconstructed Shakshuka Skillet', isRecommended: true };
const chia = { id: '391', title: 'Mango Pumpkin Seed Greek Yogurt Chia Pudding', isRecommended: false };
const classicShakshuka = { id: '65', title: 'Deconstructed Shakshuka Skillet', isRecommended: false };

describe('resolveProtocolBreakfastSeedId', () => {
    it('does not seed from the catalog while the weekday schedule is still missing', () => {
        expect(
            resolveProtocolBreakfastSeedId({
                assignedBreakfasts: [],
                currentBreakfastId: '',
                protectExisting: true,
            }),
        ).toBeNull();
    });

    it('seeds the admin-recommended breakfast once the weekday schedule arrives', () => {
        expect(
            resolveProtocolBreakfastSeedId({
                assignedBreakfasts: [shakshuka, chia],
                currentBreakfastId: '',
                protectExisting: true,
            }),
        ).toBe('345');
    });

    it('replaces a classic-library id that is not on today\'s tiers deck', () => {
        expect(
            resolveProtocolBreakfastSeedId({
                assignedBreakfasts: [shakshuka, chia],
                currentBreakfastId: classicShakshuka.id,
                protectExisting: true,
            }),
        ).toBe('345');
    });

    it('keeps a Nutrient Density swap to chia that is still on the deck', () => {
        expect(
            resolveProtocolBreakfastSeedId({
                assignedBreakfasts: [shakshuka, chia],
                currentBreakfastId: chia.id,
                protectExisting: true,
            }),
        ).toBe('391');
    });

    it('always follows the recommended breakfast when existing picks are not protected', () => {
        expect(
            resolveProtocolBreakfastSeedId({
                assignedBreakfasts: [shakshuka, chia],
                currentBreakfastId: chia.id,
                protectExisting: false,
            }),
        ).toBe('345');
    });
});
