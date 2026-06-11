import { WHEEL_HEIGHT, WHEEL_ITEM_HEIGHT, WHEEL_PAD_COUNT } from './wheelConstants.js';

/** @typedef {'selected' | 'adjacent' | 'far' | 'hidden'} WheelItemTier */

const WHEEL_MASK_IMAGE =
    'linear-gradient(to bottom, transparent, black 20%, black 80%, transparent)';

/**
 * @param {number} itemIndex
 * @param {number} scrollTop
 * @returns {number}
 */
export function getWheelItemOffset(itemIndex, scrollTop) {
    const listIndex = WHEEL_PAD_COUNT + itemIndex;
    const itemCenter = listIndex * WHEEL_ITEM_HEIGHT + WHEEL_ITEM_HEIGHT / 2;
    const viewportCenter = scrollTop + WHEEL_HEIGHT / 2;

    return (itemCenter - viewportCenter) / WHEEL_ITEM_HEIGHT;
}

/**
 * @param {number} offset
 * @returns {WheelItemTier}
 */
export function getWheelItemTier(offset) {
    const distance = Math.abs(offset);

    if (distance <= 0.5) {
        return 'selected';
    }

    if (distance <= 1.5) {
        return 'adjacent';
    }

    if (distance <= 2.5) {
        return 'far';
    }

    return 'hidden';
}

/**
 * @param {number} from
 * @param {number} to
 * @param {number} progress
 * @returns {number}
 */
function lerp(from, to, progress) {
    return from + (to - from) * progress;
}

/**
 * @param {number} offset Signed distance from the viewport center in row units.
 * @returns {{ opacity: number; rotateX: number; scale: number; tier: WheelItemTier }}
 */
export function getWheelItemVisual(offset) {
    const tier = getWheelItemTier(offset);
    const distance = Math.abs(offset);
    const sign = offset < 0 ? 1 : offset > 0 ? -1 : 0;

    if (tier === 'hidden') {
        return { opacity: 0, rotateX: sign * 50, scale: 0.85, tier };
    }

    if (distance <= 1) {
        return {
            opacity: lerp(1, 0.45, distance),
            rotateX: sign * lerp(0, 25, distance),
            scale: lerp(1, 0.96, distance),
            tier: distance <= 0.5 ? 'selected' : 'adjacent',
        };
    }

    if (distance <= 2) {
        const progress = distance - 1;

        return {
            opacity: lerp(0.45, 0.15, progress),
            rotateX: sign * lerp(25, 50, progress),
            scale: lerp(0.96, 0.9, progress),
            tier: 'far',
        };
    }

    const progress = distance - 2;

    return {
        opacity: lerp(0.15, 0, progress),
        rotateX: sign * lerp(50, 60, progress),
        scale: lerp(0.9, 0.85, progress),
        tier: 'far',
    };
}

export { WHEEL_MASK_IMAGE };
