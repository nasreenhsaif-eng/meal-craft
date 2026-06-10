/** @typedef {'male' | 'female'} CustomerSex */

/**
 * Canonical store activity levels (four-step wizard).
 * Legacy server/UI keys are normalized via {@link resolveActivityLevel}.
 *
 * @typedef {'sedentary' | 'lightly_active' | 'moderately_active' | 'very_active'} StoreActivityLevel
 */

/** @typedef {'lose_weight' | 'maintain' | 'gain_muscle'} CustomerGoal */

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
 *   proteinGrams: number;
 *   carbGrams: number;
 *   fatGrams: number;
 *   proteinPercentage: number;
 *   carbPercentage: number;
 *   fatPercentage: number;
 *   goal: CustomerGoal;
 * }} DailyTargetsResult
 */

import { GOAL_CALORIE_DELTA } from './onboarding/onboardingConstants.js';
import { normalizeActivityLevel, normalizeDietProtocol } from './onboarding/onboardingNormalize.js';

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
        proteinPercentage: 30,
        carbPercentage: 40,
        fatPercentage: 30,
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
    const explicitGoal = profile.goal;

    if (explicitGoal === 'lose_weight' || explicitGoal === 'maintain' || explicitGoal === 'gain_muscle') {
        return explicitGoal;
    }

    const weightKg = resolveWeightKg(profile);
    const targetWeightKg = Number(profile.targetWeightKg ?? profile.target_weight_kg ?? weightKg);

    if (targetWeightKg < weightKg - 0.5) {
        return 'lose_weight';
    }

    if (targetWeightKg > weightKg + 0.5) {
        return 'gain_muscle';
    }

    return 'maintain';
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
 * @param {CustomerGoal} goal
 */
export function applyGoalCalorieAdjustment(tdee, goal) {
    if (goal === 'lose_weight') {
        return Math.max(MIN_DAILY_CALORIES, tdee - GOAL_CALORIE_DELTA.deficit);
    }

    if (goal === 'gain_muscle') {
        return Math.max(MIN_DAILY_CALORIES, tdee + GOAL_CALORIE_DELTA.surplus);
    }

    return Math.max(MIN_DAILY_CALORIES, tdee);
}

/**
 * @param {OnboardingProfileInput} profile
 */
function resolveMacroPercentages(profile) {
    const protein = profile.proteinPercentage ?? profile.protein_percentage;
    const carbs = profile.carbPercentage ?? profile.carb_percentage;
    const fat = profile.fatPercentage ?? profile.fat_percentage;

    if (protein != null && carbs != null && fat != null) {
        return {
            proteinPercentage: Number(protein),
            carbPercentage: Number(carbs),
            fatPercentage: Number(fat),
        };
    }

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
    const goal = resolveCustomerGoal(profile);
    const macroPercentages = resolveMacroPercentages(profile);

    const bmr = calculateMifflinStJeorBmr(weightKg, heightCm, age, sex);
    const tdee = calculateTdee(bmr, activityLevel);

    const explicitCalories = profile.dailyCalorieTarget ?? profile.daily_calorie_target;
    const dailyCalories =
        explicitCalories != null && explicitCalories !== ''
            ? Math.max(MIN_DAILY_CALORIES, Math.round(Number(explicitCalories)))
            : Math.round(applyGoalCalorieAdjustment(tdee, goal));

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
        goal,
        ...macroGrams,
        ...macroPercentages,
    };
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
