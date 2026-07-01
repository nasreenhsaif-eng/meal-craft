import type { DayMicronutrientRow } from './aggregateDayNutritionalData.ts';
import {
    FLOOR_RDI_TARGET_PERCENT,
    NUTRIENT_RDI_BY_LABEL,
    isMicronutrientTierEnforced,
} from './nutrientDailyRdi.ts';

/** Plain Greek yogurt — calcium per 100 g (matches ingredients.csv). */
export const GREEK_YOGURT_CALCIUM_MG_PER_100G = 110;

export type LiverMealOption = {
    title: string;
    description: string;
    k2Note: string;
};

/** Rotation slot-5 liver mains — dedicated liver or minced liver blends. */
export const LIVER_K2_MEAL_OPTIONS: LiverMealOption[] = [
    {
        title: 'Seared Beef Liver w Roasted Beetroot, Chard & Chimichurri',
        description: 'Dedicated beef liver with roasted roots, chard, and purple cabbage — higher fiber than rice-heavy liver plates.',
        k2Note: '150 g beef liver + beetroot, chard, cabbage, and carrots',
    },
    {
        title: 'Sautéed Chicken Liver w Garlicky Cabbage, Bok Choy & Peppers',
        description: 'Pan-sautéed chicken liver with shredded cabbage, bok choy, peppers, and greens — rich in B12, iron, and fiber.',
        k2Note: '150 g chicken liver + cabbage, bok choy, and peppers',
    },
    {
        title: 'Beef & Liver Kefta w Herb Salad & Tahini',
        description: 'Spiced patties with minced liver blended into the beef.',
        k2Note: '22 g beef liver + 128 g ground beef (150 g total meat)',
    },
    {
        title: 'Chili Beef Stuffed Peppers',
        description: 'Savory stuffed peppers with liver blended into the beef filling.',
        k2Note: '20 g beef liver + 130 g ground beef (150 g total meat)',
    },
    {
        title: 'Eggplant & Ground Beef Stew w Quinoa Bread',
        description: 'Skillet stew with minced liver blended into seasoned ground beef.',
        k2Note: '30 g beef liver + 120 g ground beef (150 g total meat)',
    },
    {
        title: 'Spiced Beef & Liver Meatballs w Roasted Tomato Couscous',
        description: 'North African–spiced meatballs with minced liver in marinara over couscous.',
        k2Note: '22 g beef liver + 128 g ground beef (150 g total meat)',
    },
    {
        title: 'Beef & Liver Stuffed Zucchini w Marinara & Basil',
        description: 'Zucchini boats filled with seasoned beef and minced liver, baked in marinara.',
        k2Note: '20 g beef liver + 130 g ground beef (150 g total meat)',
    },
];

export type MicronutrientGapAction = {
    label: string;
    type: 'edit_meals';
};

export type MicronutrientGapGuidance = {
    id: string;
    nutrient: string;
    title: string;
    body: string;
    bullets?: string[];
    liverMeals?: LiverMealOption[];
    actions?: MicronutrientGapAction[];
    showSupplementGuide?: boolean;
};

type MealWithTitle = {
    title?: string;
};

/**
 * @param {Partial<Record<string, MealWithTitle[]>> | null | undefined} categories
 */
export function dayHasLiverMain(categories: Partial<Record<string, MealWithTitle[]>> | null | undefined): boolean {
    const mains = categories?.meals ?? [];

    return mains.some((meal) => (meal.title ?? '').toLowerCase().includes('liver'));
}

/**
 * @param {Partial<Record<string, MealWithTitle[]>> | null | undefined} categories
 */
export function liverMealsOnDay(
    categories: Partial<Record<string, MealWithTitle[]>> | null | undefined,
): LiverMealOption[] {
    const titles = new Set((categories?.meals ?? []).map((meal) => meal.title ?? ''));

    return LIVER_K2_MEAL_OPTIONS.filter((option) => titles.has(option.title));
}

/**
 * @param {number} gapMg
 */
export function yogurtGramsForCalciumGap(gapMg: number): number {
    if (gapMg <= 0) {
        return 0;
    }

    const rawGrams = (gapMg / GREEK_YOGURT_CALCIUM_MG_PER_100G) * 100;

    return Math.ceil(rawGrams / 25) * 25;
}

/**
 * @param {DayMicronutrientRow[]} rows
 * @param {string} label
 */
function rowForLabel(rows: DayMicronutrientRow[], label: string): DayMicronutrientRow | undefined {
    return rows.find((row) => row.label === label);
}

/**
 * @param {number | null | undefined} rdiPercent
 */
function isBelowFloor(rdiPercent: number | null | undefined): boolean {
    return rdiPercent != null && rdiPercent < FLOOR_RDI_TARGET_PERCENT;
}

/**
 * @param {string | null | undefined} dietProtocol
 */
function isNutrientDenseProtocol(dietProtocol: string | null | undefined): boolean {
    return dietProtocol === 'nutrient_dense';
}

/**
 * Build contextual guidance when day micronutrients fall below the 98% floor.
 *
 * @param {DayMicronutrientRow[]} rows
 * @param {number} planTierCalories
 * @param {Partial<Record<string, MealWithTitle[]>> | null | undefined} categories
 * @param {string | null | undefined} [dietProtocol]
 */
export function buildDayMicronutrientGuidance(
    rows: DayMicronutrientRow[],
    planTierCalories: number,
    categories: Partial<Record<string, MealWithTitle[]>> | null | undefined = null,
    dietProtocol: string | null | undefined = null,
): MicronutrientGapGuidance[] {
    if (!isMicronutrientTierEnforced(planTierCalories)) {
        return [];
    }

    /** @type {MicronutrientGapGuidance[]} */
    const guidance: MicronutrientGapGuidance[] = [];

    const k2Row = rowForLabel(rows, 'Vitamin K2 (mcg)');
    if (k2Row && isBelowFloor(k2Row.rdiPercent)) {
        const hasLiver = dayHasLiverMain(categories);
        const onDay = liverMealsOnDay(categories);

        guidance.push({
            id: 'vitamin-k2',
            nutrient: 'Vitamin K2',
            title: hasLiver
                ? 'Vitamin K2 is still below target today'
                : 'Choose a liver main to raise vitamin K2',
            body: hasLiver
                ? `Your day includes ${onDay.map((meal) => meal.title.split(' w ')[0]).join(' or ')}, but K2 is still at ${k2Row.formattedRdiPercent} of daily need. Consider swapping your other main for a dedicated liver dish, or add a D3 + MK-7 supplement (see below).`
                : `Today is at ${k2Row.formattedRdiPercent} of vitamin K2 need. Beef liver is the most reliable whole-food source in Meal Craft — swap in today's liver main (slot 5) via Edit selections.`,
            liverMeals: LIVER_K2_MEAL_OPTIONS,
            actions: [{ label: 'Edit main meals', type: 'edit_meals' }],
            showSupplementGuide: true,
        });
    }

    const calciumRow = rowForLabel(rows, 'Calcium (mg)');
    if (calciumRow && isBelowFloor(calciumRow.rdiPercent)) {
        const rdi = NUTRIENT_RDI_BY_LABEL['Calcium (mg)'] ?? 1000;
        const targetMg = (rdi * FLOOR_RDI_TARGET_PERCENT) / 100;
        const gapMg = Math.max(0, targetMg - calciumRow.total);
        const yogurtGrams = yogurtGramsForCalciumGap(gapMg);
        const calciumMgFromYogurt = Math.round((yogurtGrams * GREEK_YOGURT_CALCIUM_MG_PER_100G) / 100);
        const nutrientDense = isNutrientDenseProtocol(dietProtocol);

        guidance.push({
            id: 'calcium',
            nutrient: 'Calcium',
            title: 'Calcium is below target from meals alone',
            body:
                nutrientDense && yogurtGrams > 0
                    ? `Your Nutrient Density plan already includes dairy — Greek yogurt chia desserts, kefir breakfasts, and other fermented dairy. Today is still at ${calciumRow.formattedRdiPercent} of calcium need; about ${yogurtGrams} g extra plain Greek yogurt on the side (~${calciumMgFromYogurt} mg) could help close the gap toward 98% of daily need.`
                    : nutrientDense
                      ? `Today's calcium total is ${calciumRow.formattedTotal} mg (${calciumRow.formattedRdiPercent} RDI). Greek yogurt desserts, kefir, tahini, sardines, and leafy greens in this plan all contribute; an extra plain yogurt serving can help if you're still short.`
                      : yogurtGrams > 0
                        ? `Meal Craft menus are dairy-free. If you add plain Greek yogurt on the side, about ${yogurtGrams} g (~${calciumMgFromYogurt} mg calcium) would help close today's gap toward 98% of daily need.`
                        : `Today's calcium total is ${calciumRow.formattedTotal} mg (${calciumRow.formattedRdiPercent} RDI). Leafy greens, tahini, and sardines in your plan help, but dairy-free days often need an extra calcium source.`,
            bullets: nutrientDense
                ? [
                      'Delivered meals in this protocol include dairy — Greek yogurt chia puddings and kefir are already in rotation.',
                      'Sesame/tahini, sardines, and leafy greens also contribute; extra plain yogurt helps when you are still below target.',
                  ]
                : [
                      'Plain, unsweetened Greek yogurt is the simplest add-on — not included in your delivered meals.',
                      'Sesame/tahini, sardines, and leafy greens already contribute; yogurt fills what food alone may miss.',
                  ],
        });
    }

    const vitaminDRow = rowForLabel(rows, 'Vitamin D (mcg)');
    const k2Low = k2Row != null && isBelowFloor(k2Row.rdiPercent);
    const vitaminDLow = vitaminDRow != null && isBelowFloor(vitaminDRow.rdiPercent);

    if (vitaminDLow || k2Low) {
        guidance.push({
            id: 'vitamin-d-k2',
            nutrient: 'Vitamin D & K2',
            title: 'Sunlight and D3 + MK-7 work together',
            body: 'Vitamin D is hard to reach from food alone. Pair sensible sun exposure with a quality D3 + K2 (MK-7) supplement so calcium goes to bones, not soft tissue.',
            bullets: [
                'Aim for 10–20 minutes of midday sun on arms and legs several times per week when safe — adjust for skin tone and climate.',
                'Supplement cholecalciferol (vitamin D3) with menaquinone-7 (MK-7) — the long-acting K2 form often paired in D3+K2 products.',
                'Take D3+K2 with a meal that includes fat (egg, tahini, olive oil) for absorption.',
            ],
            showSupplementGuide: true,
        });
    }

    const magnesiumRow = rowForLabel(rows, 'Magnesium (mg)');
    if (magnesiumRow && isBelowFloor(magnesiumRow.rdiPercent)) {
        guidance.push({
            id: 'magnesium',
            nutrient: 'Magnesium',
            title: 'Magnesium is below target',
            body: `Today is at ${magnesiumRow.formattedRdiPercent} of magnesium need. Pick side salad + soup, and favor pumpkin seeds, tahini, and cocoa-rich desserts.`,
            bullets: ['Leafy greens, legumes, and fish mains in your rotation all contribute magnesium.'],
        });
    }

    const ironRow = rowForLabel(rows, 'Iron (mg)');
    if (ironRow && isBelowFloor(ironRow.rdiPercent)) {
        guidance.push({
            id: 'iron',
            nutrient: 'Iron',
            title: 'Iron is below target',
            body: `Today is at ${ironRow.formattedRdiPercent} of iron need. Liver mains, sardines, and tahini-heavy salads are your strongest whole-food levers.`,
            actions: [{ label: 'Edit main meals', type: 'edit_meals' }],
        });
    }

    const fiberRow = rowForLabel(rows, 'Fiber (g)');
    if (fiberRow && isBelowFloor(fiberRow.rdiPercent)) {
        guidance.push({
            id: 'fiber',
            nutrient: 'Fiber',
            title: 'Fiber is below target',
            body: `Today is at ${fiberRow.formattedRdiPercent} of fiber need. Side salad + soup picks and legume-forward vegan mains help most.`,
        });
    }

    const folateRow = rowForLabel(rows, 'Folate B9 (mcg)');
    if (folateRow && isBelowFloor(folateRow.rdiPercent)) {
        guidance.push({
            id: 'folate',
            nutrient: 'Folate',
            title: 'Folate is below target',
            body: `Today is at ${folateRow.formattedRdiPercent} of folate need. Miso soups, leafy greens, and liver rotation days close this gap fastest.`,
        });
    }

    return guidance;
}

export const BIOAVAILABLE_SUPPLEMENT_GUIDE = {
    title: 'Most bioavailable forms & reputable sourcing',
    intro: 'Food first — use supplements to fill gaps your plan cannot cover (especially D, K2, and optional calcium).',
    sections: [
        {
            heading: 'Vitamin D3',
            points: [
                'Form: cholecalciferol (D3), not ergocalciferol (D2).',
                'Typical maintenance: 1,000–2,000 IU daily; confirm dose with your clinician if you have labs.',
                'Look for brands with third-party testing (NSF, USP, or ConsumerLab verified).',
            ],
        },
        {
            heading: 'Vitamin K2 (MK-7)',
            points: [
                'Form: menaquinone-7 (MK-7) — longer half-life than MK-4; common in D3+K2 combos.',
                'Often labeled “K2 MK-7” or “menaquinone-7”; 90–120 mcg MK-7 is a typical pairing with D3.',
                'Take alongside D3 so K2 helps direct calcium metabolism.',
            ],
        },
        {
            heading: 'Calcium (if not using yogurt)',
            points: [
                'Form: calcium citrate (gentler, less constipating) or food-based sources first.',
                'Split doses under 500 mg for better absorption; do not exceed total daily needs without medical guidance.',
            ],
        },
        {
            heading: 'Other common gaps',
            points: [
                'Magnesium glycinate — well absorbed, less GI upset than oxide.',
                'Iron — heme iron from meat in your plan is preferred; supplement only if clinically indicated.',
                'B12 — methylcobalamin or adenosylcobalamin; your liver and fish mains already supply significant B12.',
            ],
        },
        {
            heading: 'Choosing a trustworthy brand',
            points: [
                'Third-party tested (NSF Certified for Sport, USP, Informed Choice, or published COA).',
                'Minimal fillers, clear IU/mcg labeling, and stated forms (D3, MK-7, not proprietary blends only).',
                'Examples often cited for quality: Thorne, Pure Encapsulations, Life Extension, Nordic Naturals (D3), and Jarrow MK-7 — compare labels to your needs.',
            ],
        },
    ],
};
