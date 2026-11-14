/** @typedef {'protein' | 'carb' | 'herb_spice' | 'vegetable' | 'fat' | 'sauce' | 'other'} MealScalingRole */

const PROTEIN_NEEDLES = [
    'chicken',
    'beef',
    'salmon',
    'shrimp',
    'tuna',
    'turkey',
    'lamb',
    'pork',
    'tofu',
    'lentil',
    'chickpea',
    'bean',
    'egg',
];

const CARB_NEEDLES = [
    'rice',
    'quinoa',
    'couscous',
    'bread',
    'flatbread',
    'pasta',
    'potato',
    'sweet potato',
];

const HERB_NEEDLES = [
    'parsley',
    'mint',
    'rosemary',
    'thyme',
    'basil',
    'cilantro',
    'coriander',
    'dill',
    'saffron',
    'turmeric',
    'cumin',
    'paprika',
    'cinnamon',
    'pepper',
    'ginger',
    'chili',
    'spice',
    'za',
];

const SAUCE_NEEDLES = [
    'sauce',
    'dressing',
    'marinade',
    'broth',
    'stock',
    'paste',
    'pesto',
    'hummus',
    'tahini',
];

const FAT_NEEDLES = [' oil', 'oil ', 'butter', 'ghee', 'avocado'];

/**
 * @param {string | undefined} name
 * @returns {MealScalingRole}
 */
export function mealScalingRoleFromName(name) {
    const normalized = String(name ?? '').toLowerCase();

    if (normalized === '') {
        return 'other';
    }

    if (SAUCE_NEEDLES.some((needle) => normalized.includes(needle))) {
        return 'sauce';
    }

    if (FAT_NEEDLES.some((needle) => normalized.includes(needle))) {
        return 'fat';
    }

    if (PROTEIN_NEEDLES.some((needle) => normalized.includes(needle))) {
        return 'protein';
    }

    if (CARB_NEEDLES.some((needle) => normalized.includes(needle))) {
        return 'carb';
    }

    if (HERB_NEEDLES.some((needle) => normalized.includes(needle))) {
        return 'herb_spice';
    }

    if (
        normalized.includes('zucchini') ||
        normalized.includes('tomato') ||
        normalized.includes('onion') ||
        normalized.includes('pepper (') ||
        normalized.includes('cauliflower') ||
        normalized.includes('carrot') ||
        normalized.includes('spinach') ||
        normalized.includes('kale') ||
        normalized.includes('eggplant') ||
        normalized.includes('cucumber') ||
        normalized.includes('celery') ||
        normalized.includes('broccoli')
    ) {
        return 'vegetable';
    }

    return 'other';
}

/**
 * @param {number} proteinMultiplier
 * @param {number} carbMultiplier
 */
export function herbFlavorMultiplier(proteinMultiplier, carbMultiplier) {
    const raw = Math.sqrt(Math.max(0, proteinMultiplier) * Math.max(0, carbMultiplier));

    return Math.max(0.5, Math.min(2, raw));
}

/**
 * @param {Array<{ name?: string; baseline_amount_grams?: number; adapted_amount_grams?: number }>} [ingredients]
 */
export function mealCardUsesMacroFirstAdaptation(ingredients) {
    if (!Array.isArray(ingredients) || ingredients.length === 0) {
        return false;
    }

    return ingredients.some((row) => {
        const baseline = Number(row.baseline_amount_grams ?? 0);
        const adapted = Number(row.adapted_amount_grams ?? 0);

        return baseline > 0 && Math.abs(adapted - baseline) > 0.05;
    });
}
