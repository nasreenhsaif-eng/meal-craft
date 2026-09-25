import { useMemo, useState } from 'react';
import ChooseYourMeals, {
    applyDeckSelectionToggle,
    applyFixedChoiceToggle,
    buildWeeklyConsultationDisplayDecks,
    DEFAULT_FULL_CRAFT_MAX_SELECTIONS,
    FIXED_CHOICE_CATEGORY_KEYS,
    soupOfTheDayMeals,
} from './ChooseYourMeals.jsx';
import { consultationMeals } from '../../consultation/consultationMockMeals.js';
import { withConsultationMobileFrame } from '../../Pages/Consultation/consultationStoryDecorators.jsx';

const mealRowDemo = consultationMeals.filter((m) => m.mealType === 'Meal');
const scheduledSoupDemo = soupOfTheDayMeals(consultationMeals);

const fullCraftDisplayDecks = buildWeeklyConsultationDisplayDecks({
    meals: consultationMeals,
    scheduledSoupMeals: scheduledSoupDemo,
    soupCatalogMeals: consultationMeals,
    includeBreakfast: true,
});

function emptyCategorySelections() {
    return {
        breakfasts: [],
        meals: [],
        sideSalads: [],
        desserts: [],
        soup: [],
    };
}

/** Pre-seed so slot cards and fixed-choice sides show selected Nano meals immediately. */
function seededFullCraftSelections() {
    const breakfastId = fullCraftDisplayDecks.breakfasts[0]?.id;
    const mealIds = fullCraftDisplayDecks.meals.slice(0, 2).map((meal) => meal.id);
    const sideId = fullCraftDisplayDecks.sideSalads[0]?.id;

    return {
        breakfasts: breakfastId ? [String(breakfastId)] : [],
        meals: mealIds.map((id) => String(id)),
        sideSalads: sideId ? [String(sideId)] : [],
        desserts: [],
        soup: [],
    };
}

function sumSelectionCalories(categorySelections) {
    const byId = new Map(consultationMeals.map((meal) => [String(meal.id), meal]));
    const ids = [
        ...categorySelections.breakfasts,
        ...categorySelections.meals,
        ...categorySelections.sideSalads,
        ...categorySelections.desserts,
        ...categorySelections.soup,
    ];

    return ids.reduce((acc, id) => acc + (byId.get(String(id))?.caloriesNumber ?? 0), 0);
}

export default {
    title: 'Design System/05. Templates & Pages/Crafted for you/ChooseYourMeals',
    component: ChooseYourMeals,
    decorators: withConsultationMobileFrame,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
        docs: {
            description: {
                component:
                    'Consultation meal curation panel — single-deck or full-craft category layouts used by Crafted for YOU.',
            },
        },
    },
};

/**
 * Legacy single-deck API (`selections` as id array) — `layout="custom"`.
 */
export const SingleDeckPropsApi = {
    name: 'Single deck',
    render: () => {
        const maxSelected = 4;
        const [selections, setSelections] = useState(/** @type {string[]} */ ([]));

        const onSelectMeal = (meal) => {
            const id = /** @type {{ id: string }} */ (meal).id;
            setSelections((prev) => {
                if (prev.includes(id)) {
                    return prev.filter((x) => x !== id);
                }
                if (prev.length >= maxSelected) {
                    return prev;
                }
                return [...prev, id];
            });
        };

        const totalKcal = useMemo(() => {
            const byId = new Map(mealRowDemo.map((m) => [m.id, m]));
            return selections.reduce((acc, id) => acc + (byId.get(id)?.caloriesNumber ?? 0), 0);
        }, [selections]);

        return (
            <ChooseYourMeals
                documentScroll
                panelClassName="w-full"
                layout="custom"
                dayName="Tuesday"
                totalKcal={totalKcal}
                summaryLabel="Tue selections"
                meals={mealRowDemo}
                selections={selections}
                onSelectMeal={onSelectMeal}
                maxSelected={maxSelected}
                deckScopeKey="story-choose-your-meals-single"
                craftTitle="Full Craft"
                targetCalories={1200}
                dayProgressLabel="Day 1 of 5"
            />
        );
    },
};

/**
 * Production Full Craft day view: selected meal cards + SEE OTHER OPTIONS modal + sides toggles.
 */
export const VerticalFullCraftCategories = {
    name: 'Full craft — cards + options modal',
    render: () => {
        const [categorySelections, setCategorySelections] = useState(seededFullCraftSelections);

        const onToggleCategory = (categoryKey, meal) => {
            const id = String(/** @type {{ id: string }} */ (meal).id);

            setCategorySelections((prev) => {
                if (FIXED_CHOICE_CATEGORY_KEYS.includes(categoryKey)) {
                    const { next, blocked } = applyFixedChoiceToggle(prev, categoryKey, id);

                    return blocked ? prev : next;
                }

                const max =
                    DEFAULT_FULL_CRAFT_MAX_SELECTIONS[
                        /** @type {keyof typeof DEFAULT_FULL_CRAFT_MAX_SELECTIONS} */ (categoryKey)
                    ] ?? 1;

                return {
                    ...prev,
                    [categoryKey]: applyDeckSelectionToggle(prev[categoryKey] ?? [], id, max),
                };
            });
        };

        const totalKcal = useMemo(() => sumSelectionCalories(categorySelections), [categorySelections]);

        return (
            <ChooseYourMeals
                documentScroll
                panelClassName="w-full"
                layout="categories"
                protocolSelectedLayout
                dayName="Monday"
                totalKcal={totalKcal}
                summaryLabel="Mon selections"
                meals={consultationMeals}
                displayDecks={fullCraftDisplayDecks}
                soupCatalogMeals={consultationMeals}
                scheduledSoupMeals={scheduledSoupDemo}
                categorySelections={categorySelections}
                maxSelectionsByCategory={DEFAULT_FULL_CRAFT_MAX_SELECTIONS}
                onToggleCategory={onToggleCategory}
                onClearFixedChoiceCategory={(categoryKey) => {
                    setCategorySelections((prev) => ({ ...prev, [categoryKey]: [] }));
                }}
                deckScopePrefix="story-day"
                craftTitle="Full Craft"
                targetCalories={2000}
                dayProgressLabel="Day 1 of 5"
                onFooterBack={() => {}}
                onFooterNext={() => {}}
                onViewDetails={() => {}}
            />
        );
    },
};

/**
 * Empty day — open SEE OTHER OPTIONS to pick meals from scratch.
 */
export const FullCraftEmptyThenOptions = {
    name: 'Full craft — empty then options',
    render: () => {
        const [categorySelections, setCategorySelections] = useState(emptyCategorySelections);

        const onToggleCategory = (categoryKey, meal) => {
            const id = String(/** @type {{ id: string }} */ (meal).id);

            setCategorySelections((prev) => {
                if (FIXED_CHOICE_CATEGORY_KEYS.includes(categoryKey)) {
                    const { next, blocked } = applyFixedChoiceToggle(prev, categoryKey, id);

                    return blocked ? prev : next;
                }

                const max =
                    DEFAULT_FULL_CRAFT_MAX_SELECTIONS[
                        /** @type {keyof typeof DEFAULT_FULL_CRAFT_MAX_SELECTIONS} */ (categoryKey)
                    ] ?? 1;

                return {
                    ...prev,
                    [categoryKey]: applyDeckSelectionToggle(prev[categoryKey] ?? [], id, max),
                };
            });
        };

        const totalKcal = useMemo(() => sumSelectionCalories(categorySelections), [categorySelections]);

        return (
            <ChooseYourMeals
                documentScroll
                panelClassName="w-full"
                layout="categories"
                protocolSelectedLayout
                dayName="Wednesday"
                totalKcal={totalKcal}
                meals={consultationMeals}
                displayDecks={fullCraftDisplayDecks}
                soupCatalogMeals={consultationMeals}
                scheduledSoupMeals={scheduledSoupDemo}
                categorySelections={categorySelections}
                maxSelectionsByCategory={DEFAULT_FULL_CRAFT_MAX_SELECTIONS}
                onToggleCategory={onToggleCategory}
                onClearFixedChoiceCategory={(categoryKey) => {
                    setCategorySelections((prev) => ({ ...prev, [categoryKey]: [] }));
                }}
                deckScopePrefix="story-empty"
                craftTitle="Full Craft"
                targetCalories={2000}
                dayProgressLabel="Day 2 of 5"
                onFooterBack={() => {}}
                onFooterNext={() => {}}
                onViewDetails={() => {}}
            />
        );
    },
};
