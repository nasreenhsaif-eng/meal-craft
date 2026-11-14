import { consultationMeals } from '../../consultation/consultationMockMeals.js';
import { mealDetailViewFixture } from '../../Components/Molecules/MealDetailView/mealDetailViewFixture.ts';

const breakfast = consultationMeals.find((m) => String(m.mealType).toLowerCase() === 'breakfast');
const mainMeals = consultationMeals.filter((m) => String(m.mealType).toLowerCase() === 'meal').slice(0, 2);
const sideSalad = consultationMeals.find((m) => String(m.mealType).toLowerCase().includes('side'));
const dessert = consultationMeals.find((m) => String(m.mealType).toLowerCase() === 'dessert');

/**
 * @param {number} factor
 */
function scaledNutritionalData(factor) {
    const base = mealDetailViewFixture.nutritionalData;

    return {
        valueColumnLabel: 'Per serving',
        sections: (base?.sections ?? []).map((section) => ({
            title: section.title,
            rows: (section.rows ?? []).map((row) => {
                const n = Number.parseFloat(String(row.value).replace(/,/g, ''));
                if (!Number.isFinite(n)) {
                    return { ...row };
                }

                const scaled = n * factor;

                return {
                    ...row,
                    value:
                        row.label === 'Total calories'
                            ? String(Math.round(scaled))
                            : String(Number(scaled.toFixed(1))),
                };
            }),
        })),
    };
}

/**
 * @param {object | undefined} meal
 * @param {{
 *   safetyAlerts?: Array<{ label: string; variant?: string }>;
 *   sickleCellHighlights?: string[];
 *   nutritionFactor?: number;
 * }} [highlights]
 */
function withSummaryDetailView(meal, highlights = {}) {
    if (!meal) {
        return null;
    }

    return {
        ...meal,
        detailView: {
            ...mealDetailViewFixture,
            shortDescription: meal.title
                ? `${meal.title} — adapted for your craft plan.`
                : mealDetailViewFixture.shortDescription,
            imageUrl: meal.imageUrl,
            imageAlt: meal.title,
            safetyAlerts: highlights.safetyAlerts ?? mealDetailViewFixture.safetyAlerts,
            sickleCellHighlights:
                highlights.sickleCellHighlights ??
                meal.nutrientHighlights ??
                mealDetailViewFixture.sickleCellHighlights,
            hasG6pdTrigger: false,
            nutritionalData: scaledNutritionalData(highlights.nutritionFactor ?? 0.45),
            nutritionSubheading: 'Adapted for your plan',
        },
    };
}

const sundayCategories = {
    breakfasts: [
        withSummaryDetailView(breakfast, {
            safetyAlerts: [
                { label: 'Eggs', variant: 'allergy' },
                { label: 'Nightshades', variant: 'allergy' },
            ],
            sickleCellHighlights: [
                'Folate',
                'Vitamin B12',
                'Vitamin B6',
                'Vitamin D & Calcium',
                'Zinc',
                'Antioxidants',
            ],
            nutritionFactor: 0.4,
        }),
    ].filter(Boolean),
    meals: mainMeals
        .map((meal, index) =>
            withSummaryDetailView(meal, {
                safetyAlerts:
                    index === 0
                        ? [
                              { label: 'Fish', variant: 'allergy' },
                              { label: 'Nightshades', variant: 'allergy' },
                          ]
                        : [{ label: 'Nightshades', variant: 'allergy' }],
                sickleCellHighlights: ['Vitamin B12', 'Vitamin B6', 'Antioxidants'],
                nutritionFactor: index === 0 ? 0.55 : 0.5,
            }),
        )
        .filter(Boolean),
    sideSalads: [
        withSummaryDetailView(sideSalad, {
            sickleCellHighlights: ['Folate'],
            nutritionFactor: 0.25,
        }),
    ].filter(Boolean),
    desserts: [
        withSummaryDetailView(dessert, {
            sickleCellHighlights: ['Calcium'],
            nutritionFactor: 0.2,
        }),
    ].filter(Boolean),
    soup: [],
};

const DAY_META = [
    { dayNumber: 1, label: 'Sunday', dateLabel: '18 Oct' },
    { dayNumber: 2, label: 'Monday', dateLabel: '19 Oct' },
    { dayNumber: 3, label: 'Tuesday', dateLabel: '20 Oct' },
    { dayNumber: 4, label: 'Wednesday', dateLabel: '21 Oct' },
    { dayNumber: 5, label: 'Thursday', dateLabel: '22 Oct' },
    { dayNumber: 6, label: 'Friday', dateLabel: '23 Oct' },
    { dayNumber: 7, label: 'Saturday', dateLabel: '24 Oct' },
];

/** Same craft-plan fixture Storybook uses for Meal Plan Summary. */
export const LANDING_PREVIEW_CRAFT_PLAN = {
    craftKey: 'full',
    craftTitle: 'Full Craft',
    weekDuration: 7,
    planTierCalories: 2000,
    dietProtocol: 'balanced',
    planDateRange: {
        startsOn: '2026-10-18',
        endsOn: '2026-10-24',
        label: '18th October to 24th October',
    },
    days: DAY_META.map((day) => ({
        ...day,
        includeSoup: false,
        categories: sundayCategories,
    })),
};

export const LANDING_PREVIEW_PROFILE = {
    dailyCaloriesMin: 1900,
    dailyCaloriesMax: 2100,
    dailyCalorieTarget: 2000,
    macroSplitStyle: 'balanced',
};
