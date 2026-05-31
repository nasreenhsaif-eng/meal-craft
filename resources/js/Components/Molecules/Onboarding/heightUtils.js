/**
 * @param {number} min
 * @param {number} max
 * @param {number} [step]
 * @returns {number[]}
 */
export function buildIntegerRange(min, max, step = 1) {
    const values = [];

    for (let value = min; value <= max; value += step) {
        values.push(value);
    }

    return values;
}

/** @returns {number} default height in cm */
export function defaultHeightCm() {
    return 170;
}

/**
 * @param {number} cm
 * @returns {{ feet: number; inches: number }}
 */
export function cmToFeetInches(cm) {
    const totalInches = Math.round(cm / 2.54);
    const feet = Math.floor(totalInches / 12);
    const inches = totalInches % 12;

    return clampFeetInches(feet, inches);
}

/**
 * @param {number} feet
 * @param {number} inches
 * @returns {{ feet: number; inches: number }}
 */
export function clampFeetInches(feet, inches) {
    const clampedFeet = Math.min(Math.max(Math.round(feet), HEIGHT_FEET_MIN), HEIGHT_FEET_MAX);
    const clampedInches = Math.min(Math.max(Math.round(inches), 0), 11);

    return {
        feet: clampedFeet,
        inches: clampedInches,
    };
}

/**
 * @param {number} feet
 * @param {number} inches
 * @returns {number} height in cm
 */
export function feetInchesToCm(feet, inches) {
    const totalInches = feet * 12 + inches;

    return Math.round(totalInches * 2.54);
}

/**
 * @param {number} cm
 * @param {number} minCm
 * @param {number} maxCm
 */
export function clampHeightCm(cm, minCm = 100, maxCm = 250) {
    return Math.min(Math.max(Math.round(cm), minCm), maxCm);
}

export const HEIGHT_CM_MIN = 100;

export const HEIGHT_CM_MAX = 250;

export const HEIGHT_FEET_MIN = 3;

export const HEIGHT_FEET_MAX = 7;

export const HEIGHT_INCHES = buildIntegerRange(0, 11);

export const HEIGHT_CM_OPTIONS = buildIntegerRange(HEIGHT_CM_MIN, HEIGHT_CM_MAX);

export const HEIGHT_FEET_OPTIONS = buildIntegerRange(HEIGHT_FEET_MIN, HEIGHT_FEET_MAX);
