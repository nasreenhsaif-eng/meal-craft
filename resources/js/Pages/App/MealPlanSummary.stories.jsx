import { MealPlanSummaryInner } from './MealPlanSummary.jsx';
import { consultationMeals } from '../../consultation/consultationMockMeals.js';
import { mealDetailViewFixture } from '../../Components/Molecules/MealDetailView/mealDetailViewFixture.ts';
import { withOnboardingMobileFrame } from '../Onboarding/onboardingStoryDecorators.jsx';

const breakfast = consultationMeals.find((m) => String(m.mealType).toLowerCase() === 'breakfast');
const mainMeals = consultationMeals.filter((m) => String(m.mealType).toLowerCase() === 'meal').slice(0, 2);
const sideSalad = consultationMeals.find((m) => String(m.mealType).toLowerCase().includes('side'));
const dessert = consultationMeals.find((m) => String(m.mealType).toLowerCase() === 'dessert');

/**
 * Scale fixture nutrition rows so day totals look realistic when several meals are summed.
 *
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
                    value: row.label === 'Total calories' ? String(Math.round(scaled)) : String(Number(scaled.toFixed(1))),
                };
            }),
        })),
    };
}

/**
 * @param {object} meal
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
                highlights.sickleCellHighlights ?? meal.nutrientHighlights ?? mealDetailViewFixture.sickleCellHighlights,
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
            sickleCellHighlights: ['Folate', 'Vitamin B12', 'Vitamin B6', 'Vitamin D & Calcium', 'Zinc', 'Antioxidants'],
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

const FULL_CRAFT_PLAN = {
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

export default {
    title: 'Design System/05. Templates & Pages/App/MealPlanSummary',
    component: MealPlanSummaryInner,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Customer meal-plan summary at `/app/meal-plan`. Pick a day, then scroll meal details. DIY shows Get recipe; Partner Kitchen shows ingredients only. Compact pills switch Meals / Macros / Micros / Allergy / Sickle.',
            },
        },
    },
};

/** Day picker with new hero copy — starts on the day list. */
export const Default = {
    name: 'Full week — 2000 kcal',
    parameters: {
        viewport: {
            defaultViewport: 'desktop',
        },
    },
    render: () => (
        <MealPlanSummaryInner
            customerName="Nasreen"
            craftPlan={FULL_CRAFT_PLAN}
            sex="female"
            activityLevel="moderately_active"
            dailyCalorieTarget={2000}
            enableMealDetailModal={false}
        />
    ),
};

/** Same flow inside the shared mobile story frame. */
export const MobileFrame = {
    name: 'Mobile — day list → full meal details',
    decorators: withOnboardingMobileFrame,
    parameters: {
        viewport: {
            defaultViewport: 'mobile1',
        },
    },
    render: () => (
        <MealPlanSummaryInner
            customerName="Nasreen"
            craftPlan={FULL_CRAFT_PLAN}
            sex="female"
            activityLevel="moderately_active"
            dailyCalorieTarget={2000}
            enableMealDetailModal={false}
        />
    ),
};

const SINGLE_DAY_PLAN = {
    ...FULL_CRAFT_PLAN,
    weekDuration: 1,
    planDateRange: {
        startsOn: '2026-10-18',
        endsOn: '2026-10-18',
        label: '18th October',
    },
    days: [
        {
            ...DAY_META[0],
            includeSoup: false,
            categories: sundayCategories,
        },
    ],
};

/** Single delivery day — skips day picker and opens full meal details. */
export const MobileSingleDay = {
    name: 'Mobile — single day opens full meals',
    decorators: withOnboardingMobileFrame,
    parameters: {
        viewport: {
            defaultViewport: 'mobile1',
        },
    },
    render: () => (
        <MealPlanSummaryInner
            customerName="Nasreen"
            craftPlan={SINGLE_DAY_PLAN}
            sex="female"
            activityLevel="moderately_active"
            dailyCalorieTarget={2000}
            enableMealDetailModal={false}
        />
    ),
};

/** Opens straight on Micronutrients for the first day. */
export const MobileMicronutrients = {
    name: 'Mobile — micronutrients pill',
    decorators: withOnboardingMobileFrame,
    parameters: {
        viewport: {
            defaultViewport: 'mobile1',
        },
    },
    render: () => (
        <MealPlanSummaryInner
            customerName="Nasreen"
            craftPlan={FULL_CRAFT_PLAN}
            sex="female"
            activityLevel="moderately_active"
            dailyCalorieTarget={2000}
            enableMealDetailModal={false}
            initialContentTab="micronutrients"
            initialMobileStep="day"
        />
    ),
};

/** Desktop opened on Micronutrients for the first day. */
export const DesktopMicronutrients = {
    name: 'Desktop — micronutrients pill',
    parameters: {
        viewport: {
            defaultViewport: 'desktop',
        },
    },
    render: () => (
        <MealPlanSummaryInner
            customerName="Nasreen"
            craftPlan={FULL_CRAFT_PLAN}
            sex="female"
            activityLevel="moderately_active"
            dailyCalorieTarget={2000}
            enableMealDetailModal={false}
            initialContentTab="micronutrients"
            initialStep="day"
        />
    ),
};
