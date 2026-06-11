/** @typedef {'male' | 'female'} CustomerSex */

/**
 * Canonical store activity levels (four-step wizard).
 * Legacy server/UI keys are normalized via {@link resolveActivityLevel}.
 *
 * @typedef {'sedentary' | 'lightly_active' | 'moderately_active' | 'very_active'} StoreActivityLevel
 */

/** @typedef {'lose_weight' | 'maintain' | 'gain_muscle'} CustomerGoal */

/** @typedef {'lose' | 'maintain' | 'gain'} WeightGoal */

/**
 * @typedef {'balanced' | 'ketobiotic' | 'cycle_sync' | 'thyroid' | 'sickle_cell_warrior' | 'sickle_cell'} DietProtocolId
 */

/**
 * @typedef {{
 *   weightKg?: number | null;
 *   weight_kg?: number | null;
 *   heightCm?: number | null;
 *   height_cm?: number | null;
 *   age?: number | null;
 *   sex?: CustomerSex | string | null;
 *   activityLevel?: string | null;
 *   activity_level?: string | null;
 *   goal?: CustomerGoal | string | null;
 *   weight_goal?: WeightGoal | string | null;
 *   weightGoal?: WeightGoal | string | null;
 *   diet_protocol?: DietProtocolId | string | null;
 *   dietProtocol?: DietProtocolId | string | null;
 *   dailyCalorieTarget?: number | null;
 *   daily_calorie_target?: number | null;
 *   proteinPercentage?: number | null;
 *   protein_percentage?: number | null;
 *   carbPercentage?: number | null;
 *   carb_percentage?: number | null;
 *   fatPercentage?: number | null;
 *   fat_percentage?: number | null;
 *   targetWeightKg?: number | null;
 *   target_weight_kg?: number | null;
 * }} OnboardingProfileInput
 */

/**
 * @typedef {{
 *   bmr: number;
 *   tdee: number;
 *   dailyCalories: number;
 *   dailyCaloriesMin: number;
 *   dailyCaloriesMax: number;
 *   dailyKjMin: number;
 *   dailyKjMax: number;
 *   proteinGrams: number;
 *   carbGrams: number;
 *   fatGrams: number;
 *   proteinPercentage: number;
 *   carbPercentage: number;
 *   fatPercentage: number;
 *   goal: CustomerGoal;
 *   weightGoal: WeightGoal;
 * }} DailyTargetsResult
 */

import { KCAL_TO_KJ } from './onboarding/onboardingConstants.js';
import { normalizeActivityLevel, normalizeDietProtocol, normalizeWeightGoal } from './onboarding/onboardingNormalize.js';

export const MIN_DAILY_CALORIES = 1200;

/** @type {Record<string, number>} */
export const ACTIVITY_MULTIPLIERS = {
    sedentary: 1.2,
    lightly_active: 1.375,
    moderately_active: 1.55,
    very_active: 1.725,
    light: 1.375,
    moderate: 1.375,
    active: 1.55,
};

/** @type {Record<DietProtocolId, { proteinPercentage: number; carbPercentage: number; fatPercentage: number }>} */
export const DIET_PROTOCOL_MACRO_PRESETS = {
    balanced: {
        proteinPercentage: 40,
        carbPercentage: 40,
        fatPercentage: 20,
    },
    ketobiotic: {
        proteinPercentage: 20,
        carbPercentage: 10,
        fatPercentage: 70,
    },
    cycle_sync: {
        proteinPercentage: 25,
        carbPercentage: 45,
        fatPercentage: 30,
    },
    thyroid: {
        proteinPercentage: 30,
        carbPercentage: 35,
        fatPercentage: 35,
    },
    sickle_cell_warrior: {
        proteinPercentage: 25,
        carbPercentage: 50,
        fatPercentage: 25,
    },
    sickle_cell: {
        proteinPercentage: 25,
        carbPercentage: 50,
        fatPercentage: 25,
    },
};

/**
 * @param {OnboardingProfileInput} profile
 */
function resolveWeightKg(profile) {
    return Number(profile.weightKg ?? profile.weight_kg ?? 0);
}

/**
 * @param {OnboardingProfileInput} profile
 */
function resolveHeightCm(profile) {
    return Number(profile.heightCm ?? profile.height_cm ?? 0);
}

/**
 * @param {OnboardingProfileInput} profile
 */
function resolveAge(profile) {
    return Number(profile.age ?? 0);
}

/**
 * @param {OnboardingProfileInput} profile
 * @returns {CustomerSex}
 */
function resolveSex(profile) {
    const sex = profile.sex ?? 'female';

    return sex === 'male' ? 'male' : 'female';
}

/**
 * @param {string | null | undefined} level
 * @returns {StoreActivityLevel}
 */
export function resolveActivityLevel(level) {
    return normalizeActivityLevel(level);
}

/**
 * @param {OnboardingProfileInput} profile
 * @returns {CustomerGoal}
 */
export function resolveCustomerGoal(profile) {
    const weightGoal = resolveWeightGoal(profile);

    if (weightGoal === 'lose') {
        return 'lose_weight';
    }

    if (weightGoal === 'gain') {
        return 'gain_muscle';
    }

    return 'maintain';
}

/**
 * @param {OnboardingProfileInput} profile
 * @returns {WeightGoal}
 */
export function resolveWeightGoal(profile) {
    const explicit =
        normalizeWeightGoal(profile.weight_goal) ??
        normalizeWeightGoal(profile.weightGoal) ??
        normalizeWeightGoal(profile.goal);

    if (explicit) {
        return explicit;
    }

    const weightKg = resolveWeightKg(profile);
    const targetWeightKg = Number(profile.targetWeightKg ?? profile.target_weight_kg ?? weightKg);

    if (targetWeightKg < weightKg - 0.5) {
        return 'lose';
    }

    if (targetWeightKg > weightKg + 0.5) {
        return 'gain';
    }

    return 'maintain';
}

/**
 * @param {number} tdee
 * @param {WeightGoal} weightGoal
 * @returns {{ min: number; max: number; midpoint: number }}
 */
export function calculateGoalCalorieRange(tdee, weightGoal) {
    const roundedTdee = Math.round(tdee);
    let min;
    let max;

    switch (weightGoal) {
        case 'lose':
            min = roundedTdee - 750;
            max = roundedTdee - 500;
            break;
        case 'gain':
            min = roundedTdee + 300;
            max = roundedTdee + 500;
            break;
        default:
            min = roundedTdee;
            max = roundedTdee + 100;
            break;
    }

    min = Math.max(MIN_DAILY_CALORIES, Math.round(min));
    max = Math.max(min, Math.round(max));

    return {
        min,
        max,
        midpoint: Math.round((min + max) / 2),
    };
}

/**
 * @param {number} kcal
 */
export function kcalToKj(kcal) {
    return Math.round(kcal * KCAL_TO_KJ);
}

/** @type {Record<WeightGoal, string>} */
export const WEIGHT_GOAL_SUMMARY_COPY = {
    lose: 'This calorie target will allow you to lose weight at a healthy and sustainable rate of 0.5 to 1 kilogram per week.',
    maintain: 'This calorie target allows you to maintain your current weight, within a margin of a kilogram.',
    gain: 'This calorie target will allow you to gain weight at a healthy and sustainable rate of 0.5 to 1 kilogram per week.',
};

/**
 * @param {WeightGoal | CustomerGoal | string | null | undefined} weightGoal
 */
export function goalSummaryCopy(weightGoal) {
    const normalized = normalizeWeightGoal(weightGoal) ?? 'maintain';

    return WEIGHT_GOAL_SUMMARY_COPY[normalized];
}

/**
 * @param {number} weightKg
 * @param {number} heightCm
 * @param {number} age
 * @param {CustomerSex} sex
 */
export function calculateMifflinStJeorBmr(weightKg, heightCm, age, sex) {
    const base = 10 * weightKg + 6.25 * heightCm - 5 * age;

    return sex === 'male' ? base + 5 : base - 161;
}

/**
 * @param {number} bmr
 * @param {string} activityLevel
 */
export function calculateTdee(bmr, activityLevel) {
    const normalized = resolveActivityLevel(activityLevel);
    const multiplier = ACTIVITY_MULTIPLIERS[normalized] ?? ACTIVITY_MULTIPLIERS.lightly_active;

    return bmr * multiplier;
}

/**
 * @param {number} tdee
 * @param {CustomerGoal | WeightGoal} goal
 */
export function applyGoalCalorieAdjustment(tdee, goal) {
    const weightGoal = normalizeWeightGoal(goal) ?? 'maintain';

    return calculateGoalCalorieRange(tdee, weightGoal).midpoint;
}

/**
 * @param {OnboardingProfileInput} profile
 */
function resolveMacroPercentages(profile) {
    const protocol = normalizeDietProtocol(profile.diet_protocol ?? profile.dietProtocol ?? 'balanced');

    return DIET_PROTOCOL_MACRO_PRESETS[protocol] ?? DIET_PROTOCOL_MACRO_PRESETS.balanced;
}

/**
 * @param {number} calories
 * @param {number} proteinPercentage
 * @param {number} carbPercentage
 * @param {number} fatPercentage
 */
export function calculateMacroGrams(calories, proteinPercentage, carbPercentage, fatPercentage) {
    const safeCalories = Math.max(0, calories);

    return {
        proteinGrams: Math.round((safeCalories * (proteinPercentage / 100)) / 4),
        carbGrams: Math.round((safeCalories * (carbPercentage / 100)) / 4),
        fatGrams: Math.round((safeCalories * (fatPercentage / 100)) / 9),
    };
}

/**
 * Compute daily calorie and macro targets from onboarding profile metrics.
 *
 * @param {OnboardingProfileInput} profile
 * @returns {DailyTargetsResult}
 */
export function calculateDailyTargets(profile) {
    const weightKg = resolveWeightKg(profile);
    const heightCm = resolveHeightCm(profile);
    const age = resolveAge(profile);
    const sex = resolveSex(profile);
    const activityLevel = resolveActivityLevel(profile.activityLevel ?? profile.activity_level ?? 'lightly_active');
    const weightGoal = resolveWeightGoal(profile);
    const goal = resolveCustomerGoal(profile);
    const macroPercentages = resolveMacroPercentages(profile);

    const bmr = calculateMifflinStJeorBmr(weightKg, heightCm, age, sex);
    const tdee = calculateTdee(bmr, activityLevel);
    const calorieRange = calculateGoalCalorieRange(tdee, weightGoal);

    const explicitCalories = profile.dailyCalorieTarget ?? profile.daily_calorie_target;
    const dailyCalories =
        explicitCalories != null && explicitCalories !== ''
            ? Math.max(MIN_DAILY_CALORIES, Math.round(Number(explicitCalories)))
            : calorieRange.midpoint;

    const dailyCaloriesMin =
        explicitCalories != null && explicitCalories !== '' ? dailyCalories : calorieRange.min;
    const dailyCaloriesMax =
        explicitCalories != null && explicitCalories !== '' ? dailyCalories : calorieRange.max;

    const macroGrams = calculateMacroGrams(
        dailyCalories,
        macroPercentages.proteinPercentage,
        macroPercentages.carbPercentage,
        macroPercentages.fatPercentage,
    );

    return {
        bmr: Math.round(bmr),
        tdee: Math.round(tdee),
        dailyCalories,
        dailyCaloriesMin,
        dailyCaloriesMax,
        dailyKjMin: kcalToKj(dailyCaloriesMin),
        dailyKjMax: kcalToKj(dailyCaloriesMax),
        goal,
        weightGoal,
        ...macroGrams,
        ...macroPercentages,
    };
}

/**
 * @param {number} min
 * @param {number} max
 */
export function formatCalorieRange(min, max) {
    const safeMin = Number.isFinite(min) ? min : Number.isFinite(max) ? max : 0;
    const safeMax = Number.isFinite(max) ? max : safeMin;

    if (safeMin === safeMax) {
        return formatCalorieTarget(safeMin);
    }

    return `${formatCalorieTarget(safeMin)} – ${formatCalorieTarget(safeMax)}`;
}

/**
 * @param {number} min
 * @param {number} max
 */
export function formatKjRange(min, max) {
    if (min === max) {
        return formatCalorieTarget(min);
    }

    return `${formatCalorieTarget(min)} – ${formatCalorieTarget(max)}`;
}

/**
 * @param {number} value
 */
export function formatCalorieTarget(value) {
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(value);
}

/**
 * @param {number} grams
 */
export function formatMacroGramsValue(grams) {
    return new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(grams);
}

/**
 * @param {number} grams
 */
export function formatMacroGrams(grams) {
    return `${formatMacroGramsValue(grams)}g`;
}

/**
 * @param {number} percentage
 */
export function formatMacroPercentage(percentage) {
    return `${Math.round(percentage)}%`;
}

/**
 * @param {OnboardingProfileInput} profile
 */
export function shouldShowCycleAnalysis(profile) {
    return resolveSex(profile) === 'female';
}
