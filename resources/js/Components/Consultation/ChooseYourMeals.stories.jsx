import { useMemo, useState } from 'react';
import ChooseYourMeals, {
    DEFAULT_FULL_CRAFT_MAX_SELECTIONS,
    buildConsultationDeckCatalog,
    soupOfTheDayMeals,
} from './ChooseYourMeals.jsx';
import { consultationMeals } from '../../Pages/Consultation/CraftedForYouPage.jsx';

const consultationDeckMeals = buildConsultationDeckCatalog(consultationMeals);
const mealRowDemo = consultationDeckMeals.filter((m) => m.mealType === 'Meal');
const scheduledSoupDemo = soupOfTheDayMeals(consultationMeals);

const emptyCategorySelections = () => ({
    breakfasts: [],
    meals: [],
    sideSalads: [],
    desserts: [],
    soup: [],
});

/** Matches curation day shell on CraftedForYouPage (viewport-locked panel + inner scroll). */
function CurationPanelShell({ children }) {
    return (
        <div className="min-h-screen bg-[#F8F9F6] p-4 md:p-8">
            <div className="mx-auto max-w-[1100px]">
                <div className="h-[calc(100dvh-8.5rem)] max-md:h-[calc(100dvh-7rem)] overflow-hidden">
                    {children}
                </div>
            </div>
        </div>
    );
}

export default {
    title: 'MealCraft/Consultation/ChooseYourMeals',
    component: ChooseYourMeals,
    parameters: {
        layout: 'fullscreen',
        a11y: { config: { rules: [{ id: 'color-contrast', enabled: true }] } },
    },
};

/**
 * Legacy single-deck API (`selections` as id array) — `layout="custom"`.
 */
export const SingleDeckPropsApi = {
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
            <CurationPanelShell>
                <ChooseYourMeals
                    panelClassName="h-full min-h-0"
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
            </CurationPanelShell>
        );
    },
};

/**
 * Full Craft category flow — capped deck catalog (2 / 4 / 2 / 2), optional soup, sticky footer nav.
 */
export const VerticalFullCraftCategories = {
    render: () => {
        const [categorySelections, setCategorySelections] = useState(emptyCategorySelections());

        const onToggleCategory = (categoryKey, meal) => {
            const id = /** @type {{ id: string }} */ (meal).id;
            const max = DEFAULT_FULL_CRAFT_MAX_SELECTIONS[categoryKey];
            setCategorySelections((prev) => {
                const existing = prev[categoryKey] ?? [];
                const isOn = existing.includes(id);
                let next = existing;
                if (isOn) {
                    next = existing.filter((x) => x !== id);
                } else if (existing.length < max) {
                    next = [...existing, id];
                }
                return { ...prev, [categoryKey]: next };
            });
        };

        const totalKcal = useMemo(() => {
            const byId = new Map(consultationMeals.map((m) => [m.id, m]));
            const ids = [
                ...categorySelections.breakfasts,
                ...categorySelections.meals,
                ...categorySelections.sideSalads,
                ...categorySelections.desserts,
                ...categorySelections.soup,
            ];
            return ids.reduce((acc, id) => acc + (byId.get(id)?.caloriesNumber ?? 0), 0);
        }, [categorySelections]);

        return (
            <CurationPanelShell>
                <ChooseYourMeals
                    panelClassName="h-full min-h-0"
                    layout="categories"
                    dayName="Monday"
                    totalKcal={totalKcal}
                    summaryLabel="Mon selections"
                    meals={consultationDeckMeals}
                    soupCatalogMeals={consultationMeals}
                    scheduledSoupMeals={scheduledSoupDemo}
                    categorySelections={categorySelections}
                    onToggleCategory={onToggleCategory}
                    onSoupOptInChange={(enabled) => {
                        if (!enabled) {
                            setCategorySelections((prev) => ({ ...prev, soup: [] }));
                        }
                    }}
                    deckScopePrefix="story-day"
                    craftTitle="Full Craft"
                    targetCalories={2000}
                    dayProgressLabel="Day 1 of 5"
                    onFooterBack={() => {}}
                    onFooterNext={() => {}}
                />
            </CurationPanelShell>
        );
    },
};
