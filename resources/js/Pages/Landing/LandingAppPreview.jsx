import { useEffect, useState } from 'react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { WelcomeBackInner } from '../App/Home.jsx';
import { MealPlanSummaryInner } from '../App/MealPlanSummary.jsx';
import { LANDING_PREVIEW_CRAFT_PLAN, LANDING_PREVIEW_PROFILE } from './landingAppPreviewFixtures.js';

const HOLD_MS = 3800;
const SLIDE = { type: 'spring', stiffness: 260, damping: 30, mass: 0.85 };

const SCREENS = [
    { id: 'home', label: 'Your targets' },
    { id: 'day', label: "Today's meals" },
    { id: 'nutrition', label: 'Nutritional summary' },
];

/**
 * Marketing phone that cycles the real customer app screens.
 *
 * @param {{ className?: string }} props
 */
export default function LandingAppPreview({ className = '' }) {
    const reduceMotion = Boolean(useReducedMotion());
    const nutritionIndex = SCREENS.findIndex((screen) => screen.id === 'nutrition');
    const [index, setIndex] = useState(reduceMotion ? nutritionIndex : 0);

    useEffect(() => {
        if (reduceMotion) {
            setIndex(nutritionIndex);

            return undefined;
        }

        const timer = window.setInterval(() => {
            setIndex((current) => (current + 1) % SCREENS.length);
        }, HOLD_MS);

        return () => window.clearInterval(timer);
    }, [reduceMotion, nutritionIndex]);

    const screen = SCREENS[index] ?? SCREENS[1];

    return (
        <div className={['flex w-full max-w-[340px] flex-col items-center', className].join(' ')}>
            <div
                className="relative h-[min(620px,70vh)] w-full overflow-hidden"
                aria-live="polite"
                aria-atomic="true"
            >
                <AnimatePresence mode="wait" initial={false}>
                    <motion.div
                        key={screen.id}
                        className="absolute inset-0"
                        initial={reduceMotion ? false : { opacity: 0, x: 28 }}
                        animate={{ opacity: 1, x: 0 }}
                        exit={reduceMotion ? undefined : { opacity: 0, x: -28 }}
                        transition={reduceMotion ? { duration: 0 } : SLIDE}
                    >
                        {screen.id === 'home' ? (
                            <WelcomeBackInner
                                preview
                                customerName="Nasreen"
                                profile={LANDING_PREVIEW_PROFILE}
                                chooseByFridayLabel="24 Oct"
                                onChooseMeals={() => {}}
                            />
                        ) : null}
                        {screen.id === 'day' ? (
                            <MealPlanSummaryInner
                                preview
                                customerName="Nasreen"
                                craftPlan={LANDING_PREVIEW_CRAFT_PLAN}
                                sex="female"
                                activityLevel="moderately_active"
                                dailyCalorieTarget={2000}
                                enableMealDetailModal={false}
                                initialStep="day"
                                initialContentTab="meals"
                            />
                        ) : null}
                        {screen.id === 'nutrition' ? (
                            <MealPlanSummaryInner
                                preview
                                customerName="Nasreen"
                                craftPlan={LANDING_PREVIEW_CRAFT_PLAN}
                                sex="female"
                                activityLevel="moderately_active"
                                dailyCalorieTarget={2000}
                                enableMealDetailModal={false}
                                initialStep="day"
                                initialContentTab="macronutrients"
                            />
                        ) : null}
                    </motion.div>
                </AnimatePresence>
            </div>

            <p className="mt-3 font-montserrat text-sm font-semibold text-brand-primary">{screen.label}</p>
        </div>
    );
}
