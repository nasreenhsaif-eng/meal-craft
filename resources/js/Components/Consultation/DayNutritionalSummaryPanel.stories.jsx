import { useState } from 'react';
import DayNutritionalSummaryPanel, { DAY_SUMMARY_TABS } from './DayNutritionalSummaryPanel.jsx';
import FoodFilterPill from '../MealSystem/FoodFilterPill.jsx';
import { consultationMeals } from '../../consultation/consultationMockMeals.js';
import { mealDetailViewFixture } from '../Molecules/MealDetailView/mealDetailViewFixture.ts';
import { withConsultationMobileFrame } from '../../Pages/Consultation/consultationStoryDecorators.jsx';

/**
 * @param {object} meal
 * @param {number} [nutritionFactor]
 */
function withNutritionDetail(meal, nutritionFactor = 0.45) {
    const base = mealDetailViewFixture.nutritionalData;

    return {
        ...meal,
        detailView: {
            ...(meal.detailView ?? {}),
            title: meal.title,
            nutritionalData: {
                valueColumnLabel: 'Per serving',
                sections: (base?.sections ?? []).map((section) => ({
                    title: section.title,
                    rows: (section.rows ?? []).map((row) => {
                        const n = Number.parseFloat(String(row.value).replace(/,/g, ''));
                        if (!Number.isFinite(n)) {
                            return { ...row };
                        }

                        const scaled = n * nutritionFactor;

                        return {
                            ...row,
                            value:
                                row.label === 'Total calories'
                                    ? String(Math.round(scaled))
                                    : String(Number(scaled.toFixed(1))),
                        };
                    }),
                })),
            },
            safetyAlerts: meal.detailView?.safetyAlerts ?? [{ label: 'Eggs', variant: 'allergy' }],
            sickleCellHighlights: meal.detailView?.sickleCellHighlights ?? meal.nutrientHighlights ?? ['Folate'],
            hasG6pdTrigger: false,
        },
    };
}

const breakfasts = consultationMeals
    .filter((m) => String(m.mealType).toLowerCase() === 'breakfast')
    .slice(0, 1)
    .map((m) => withNutritionDetail(m, 0.4));
const meals = consultationMeals
    .filter((m) => String(m.mealType).toLowerCase() === 'meal')
    .slice(0, 2)
    .map((m, i) => withNutritionDetail(m, i === 0 ? 0.55 : 0.5));
const sideSalads = consultationMeals
    .filter((m) => String(m.mealType).toLowerCase().includes('side'))
    .slice(0, 1)
    .map((m) => withNutritionDetail(m, 0.25));
const desserts = consultationMeals
    .filter((m) => String(m.mealType).toLowerCase() === 'dessert')
    .slice(0, 1)
    .map((m) => withNutritionDetail(m, 0.2));

const fixtureCategories = {
    breakfasts,
    meals,
    sideSalads,
    desserts,
    soup: [],
};

export default {
    title: 'Design System/05. Templates & Pages/Crafted for you/DayNutritionalSummaryPanel',
    component: DayNutritionalSummaryPanel,
    decorators: withConsultationMobileFrame,
    parameters: {
        layout: 'fullscreen',
        docs: {
            description: {
                component:
                    'Day nutrition review tabs (meals, macros, micros, allergy, sickle) used on meal-plan summary and admin preview.',
            },
        },
    },
};

export const TabbedSummary = {
    name: 'Tabbed day summary',
    render: () => {
        const [tab, setTab] = useState(/** @type {string} */ ('meals'));

        return (
            <div className="w-full px-3 py-4 sm:mx-auto sm:max-w-[40rem] sm:px-6 sm:py-8">
                <div className="mb-4 flex flex-wrap gap-2">
                    {DAY_SUMMARY_TABS.map((entry) => (
                        <FoodFilterPill
                            key={entry.id}
                            label={entry.label}
                            isActive={tab === entry.id}
                            onClick={() => setTab(entry.id)}
                        />
                    ))}
                </div>
                <DayNutritionalSummaryPanel
                    tab={tab}
                    categories={fixtureCategories}
                    dayLabel="Monday"
                    planCategoryLabel="Full Craft"
                    planTierCalories={1500}
                    craftKey="full"
                    sex="female"
                    activityLevel="moderately_active"
                    dailyCalories={1500}
                    onOpenMeal={() => {}}
                />
            </div>
        );
    },
};

export const Macronutrients = {
    name: 'Macronutrients tab',
    render: () => (
        <div className="w-full px-3 py-4 sm:mx-auto sm:max-w-[40rem] sm:px-6 sm:py-8">
            <DayNutritionalSummaryPanel
                tab="macronutrients"
                categories={fixtureCategories}
                dayLabel="Monday"
                planCategoryLabel="Full Craft"
                planTierCalories={1500}
                craftKey="full"
            />
        </div>
    ),
};

export const Micronutrients = {
    name: 'Micronutrients tab',
    render: () => (
        <div className="w-full px-3 py-4 sm:mx-auto sm:max-w-[40rem] sm:px-6 sm:py-8">
            <DayNutritionalSummaryPanel
                tab="micronutrients"
                categories={fixtureCategories}
                dayLabel="Monday"
                planCategoryLabel="Full Craft"
                planTierCalories={2000}
                craftKey="full"
                sex="female"
                activityLevel="moderately_active"
                dailyCalories={2000}
            />
        </div>
    ),
};

export const MicronutrientsBelowFloor = {
    name: 'Micronutrients + caloric notice',
    render: () => (
        <div className="w-full px-3 py-4 sm:mx-auto sm:max-w-[40rem] sm:px-6 sm:py-8">
            <DayNutritionalSummaryPanel
                tab="micronutrients"
                categories={fixtureCategories}
                dayLabel="Monday"
                planCategoryLabel="Full Craft"
                planTierCalories={1200}
                craftKey="full"
                sex="female"
                activityLevel="moderately_active"
                dailyCalories={1200}
            />
        </div>
    ),
};
