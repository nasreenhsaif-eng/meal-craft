import { useState } from 'react';
import DayNutritionalSummaryPanel, { DAY_SUMMARY_TABS } from './DayNutritionalSummaryPanel.jsx';
import FoodFilterPill from '../MealSystem/FoodFilterPill.jsx';
import { consultationMeals } from '../../consultation/consultationMockMeals.js';
import { withConsultationMobileFrame } from '../../Pages/Consultation/consultationStoryDecorators.jsx';

const breakfasts = consultationMeals.filter((m) => String(m.mealType).toLowerCase() === 'breakfast').slice(0, 1);
const meals = consultationMeals.filter((m) => String(m.mealType).toLowerCase() === 'meal').slice(0, 2);
const sideSalads = consultationMeals.filter((m) => String(m.mealType).toLowerCase().includes('side')).slice(0, 1);
const desserts = consultationMeals.filter((m) => String(m.mealType).toLowerCase() === 'dessert').slice(0, 1);

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
