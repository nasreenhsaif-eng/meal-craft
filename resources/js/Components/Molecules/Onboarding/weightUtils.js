import { buildIntegerRange } from './heightUtils.js';

export const WEIGHT_KG_MIN = 40;

export const WEIGHT_KG_MAX = 200;

export const WEIGHT_LB_MIN = 90;

export const WEIGHT_LB_MAX = 450;

export const WEIGHT_KG_OPTIONS = buildIntegerRange(WEIGHT_KG_MIN, WEIGHT_KG_MAX);

export const WEIGHT_LB_OPTIONS = buildIntegerRange(WEIGHT_LB_MIN, WEIGHT_LB_MAX);

const LB_PER_KG = 2.2046226218;

/** @returns {number} default weight in kg */
export function defaultWeightKg() {
    return 70;
}

/** @returns {number} default weight in lb */
export function defaultWeightLb() {
    return 150;
}

/**
 * @param {number} kg
 */
export function clampWeightKg(kg) {
    return Math.min(Math.max(Math.round(kg), WEIGHT_KG_MIN), WEIGHT_KG_MAX);
}

/**
 * @param {number} lb
 */
export function clampWeightLb(lb) {
    return Math.min(Math.max(Math.round(lb), WEIGHT_LB_MIN), WEIGHT_LB_MAX);
}

/**
 * @param {number} kg
 * @returns {number}
 */
export function kgToLb(kg) {
    return clampWeightLb(Math.round(kg * LB_PER_KG));
}

/**
 * @param {number} lb
 * @returns {number}
 */
export function lbToKg(lb) {
    return clampWeightKg(Math.round((lb / LB_PER_KG) * 10) / 10);
}

/**
 * @param {number | null | undefined} kg
 * @returns {number}
 */
export function resolveWeightKg(kg) {
    const numeric = Number(kg);

    if (!Number.isFinite(numeric) || numeric <= 0) {
        return defaultWeightKg();
    }

    return clampWeightKg(numeric);
}

/**
 * @param {number | null | undefined} targetKg
 * @param {number | null | undefined} [currentWeightKg]
 * @returns {number}
 */
export function resolveTargetWeightKg(targetKg, currentWeightKg) {
    const numeric = Number(targetKg);

    if (Number.isFinite(numeric) && numeric > 0) {
        return clampWeightKg(numeric);
    }

    return resolveWeightKg(currentWeightKg);
}
