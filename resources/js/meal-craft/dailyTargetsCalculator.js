/** @typedef {'male' | 'female'} CustomerSex */

/** @typedef {'sedentary' | 'light' | 'moderate' | 'active' | 'very_active'} ActivityLevel */

/** @typedef {'lose_weight' | 'maintain' | 'gain_muscle'} CustomerGoal */

/** @typedef {'balanced' | 'high_protein'} MacroSplitStyle */

/**
 * @typedef {{
 *   weightKg?: number | null;
 *   weight_kg?: number | null;
 *   heightCm?: number | null;
 *   height_cm?: number | null;
 *   age?: number | null;
 *   sex?: CustomerSex | string | null;
 *   activityLevel?: ActivityLevel | string | null;
 *   activity_level?: ActivityLevel | string | null;
 *   goal?: CustomerGoal | string | null;
 *   macroSplitStyle?: MacroSplitStyle | string | null;
 *   macro_split_style?: MacroSplitStyle | string | null;
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
 * }} DailyTargetsResult
 */

export const MIN_DAILY_CALORIES = 1200;

export const ACTIVITY_MULTIPLIERS = {
    sedentary: 1.2,
    light: 1.375,
    moderate: 1.55,
    active: 1.725,
    very_active: 1.9,
};

export const GOAL_CALORIE_ADJUSTMENTS = {
    lose_weight: -0.1,
    maintain: 0,
    gain_muscle: 0.1,
};

export const MACRO_PRESETS = {
    balanced: {
        proteinPercentage: 30,
        carbPercentage: 40,
        fatPercentage: 30,
    },
    high_protein: {
        proteinPercentage: 45,
        carbPercentage: 25,
        fatPercentage: 30,
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
 * @param {OnboardingProfileInput} profile
 * @returns {ActivityLevel}
 */
function resolveActivityLevel(profile) {
    const level = profile.activityLevel ?? profile.activity_level ?? 'moderate';

    return ACTIVITY_MULTIPLIERS[level] ? level : 'moderate';
}

/**
 * @param {OnboardingProfileInput} profile
 * @returns {CustomerGoal}
 */
export function resolveCustomerGoal(profile) {
    const explicitGoal = profile.goal;

    if (explicitGoal && GOAL_CALORIE_ADJUSTMENTS[explicitGoal]) {
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
 * @param {ActivityLevel} activityLevel
 */
export function calculateTdee(bmr, activityLevel) {
    const multiplier = ACTIVITY_MULTIPLIERS[activityLevel] ?? ACTIVITY_MULTIPLIERS.moderate;

    return bmr * multiplier;
}

/**
 * @param {number} tdee
 * @param {CustomerGoal} goal
 */
export function applyGoalCalorieAdjustment(tdee, goal) {
    const adjustment = GOAL_CALORIE_ADJUSTMENTS[goal] ?? 0;

    return Math.max(MIN_DAILY_CALORIES, tdee * (1 + adjustment));
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

    const style = profile.macroSplitStyle ?? profile.macro_split_style ?? 'balanced';

    return MACRO_PRESETS[style] ?? MACRO_PRESETS.balanced;
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
    const activityLevel = resolveActivityLevel(profile);
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
export function formatMacroGrams(grams) {
    return `${new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }).format(grams)}g`;
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
