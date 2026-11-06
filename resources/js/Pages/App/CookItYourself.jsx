import { useCallback, useEffect, useLayoutEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button.jsx';
import OnboardingOptionButton from '../../Components/Atoms/Button/OnboardingOptionButton.jsx';
import { PLAN_MACRO_CATEGORY_ROWS } from '../../Components/Consultation/ChooseYourMeals.jsx';
import {
    DAY_SUMMARY_TABS,
    DayAllergySafetyTabPanel,
    DayMealsTabPanel,
    DayMacronutrientsTabPanel,
    DayMicronutrientsTabPanel,
    DaySickleCellTabPanel,
    listDayMealsWithHighlights,
} from '../../Components/Consultation/DayNutritionalSummaryPanel.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CookMealCard from '../../Components/Molecules/Customer/CookMealCard.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import {
    addFavouriteRecipe,
    favouriteRecipeFromMeal,
    isFavouriteRecipe,
    readCustomerFavourites,
    removeFavouriteRecipe,
    writeCustomerFavourites,
} from '../../customer/customerFavourites.js';
import {
    mergeShoppingListItems,
    readCustomerShoppingList,
    removeShoppingListKeys,
    writeCustomerShoppingList,
} from '../../customer/customerShoppingList.js';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';
import { resolveMealImageUrl } from '../../meal-library/resolveMealImageUrl.ts';

const PAGE_BG = 'bg-[#F8F9F6]';

/** @typedef {'days' | 'day' | 'meal'} CookStep */
/** @typedef {'meals' | 'macronutrients' | 'micronutrients' | 'allergies' | 'sickle'} DaySummaryTabId */

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {object} [props.craftPlan]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.toolsUrl]
 * @param {string} [props.cartUrl]
 * @param {string} [props.recipesUrl]
 * @param {string | null} [props.sex]
 * @param {string | null} [props.activityLevel]
 * @param {number | null} [props.dailyCalorieTarget]
 */
export function CookItYourselfInner({
    customerName = '',
    craftPlan = {},
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    toolsUrl = '/app/tools',
    cartUrl = '/app/cart',
    recipesUrl: _recipesUrl = '/meal-plan/recipes',
    sex = null,
    activityLevel = null,
    dailyCalorieTarget = null,
}) {
    const page = usePage();
    const userId = page.props?.auth?.user?.id ?? null;

    const days = craftPlan.days ?? [];
    const hasMultipleDays = days.length > 1;

    const [activeDay, setActiveDay] = useState(() => days[0]?.dayNumber ?? 1);
    /** @type {[CookStep, import('react').Dispatch<import('react').SetStateAction<CookStep>>]} */
    const [step, setStep] = useState(() => (hasMultipleDays ? 'days' : 'day'));
    /** @type {[DaySummaryTabId, import('react').Dispatch<import('react').SetStateAction<DaySummaryTabId>>]} */
    const [contentTab, setContentTab] = useState(/** @type {DaySummaryTabId} */ ('meals'));
    const [selectedMealId, setSelectedMealId] = useState(/** @type {string | null} */ (null));
    const [shoppingList, setShoppingList] = useState(() => readCustomerShoppingList(userId));
    const [favourites, setFavourites] = useState(() => readCustomerFavourites(userId));

    useEffect(() => {
        setShoppingList(readCustomerShoppingList(userId));
        setFavourites(readCustomerFavourites(userId));
    }, [userId]);

    const activeDayData = useMemo(
        () => days.find((day) => day.dayNumber === activeDay) ?? days[0] ?? null,
        [activeDay, days],
    );

    const activeDayCategories = useMemo(() => {
        if (!activeDayData?.categories) {
            return {};
        }

        /** @type {Record<string, unknown[]>} */
        const out = {};

        for (const row of PLAN_MACRO_CATEGORY_ROWS) {
            const items = activeDayData.categories?.[row.key] ?? [];
            if (row.optional && items.length === 0) {
                continue;
            }

            out[row.key] = items;
        }

        return out;
    }, [activeDayData]);

    const dayMeals = useMemo(
        () => listDayMealsWithHighlights(activeDayCategories),
        [activeDayCategories],
    );

    const selectedMeal = useMemo(
        () => dayMeals.find((meal) => String(meal.id) === String(selectedMealId)) ?? null,
        [dayMeals, selectedMealId],
    );

    const planCategoryLabel = `${craftPlan.craftTitle ?? 'Craft'} · ${craftPlan.planTierCalories ?? ''} kcal`.trim();
    const activeDayLabel = activeDayData?.dateLabel
        ? `${activeDayData.label} ${activeDayData.dateLabel}`
        : (activeDayData?.label ?? 'Day');

    useLayoutEffect(() => {
        window.scrollTo(0, 0);
    }, [activeDay, step, contentTab, selectedMealId]);

    const handleSelectDay = useCallback((dayNumber) => {
        setActiveDay(dayNumber);
        setContentTab('meals');
        setSelectedMealId(null);
        setStep('day');
    }, []);

    const handleViewMealDetails = useCallback((meal) => {
        setSelectedMealId(String(meal.id));
        setStep('meal');
    }, []);

    const handleBackToDay = useCallback(() => {
        setSelectedMealId(null);
        setContentTab('meals');
        setStep('day');
    }, []);

    const handleToggleIngredient = useCallback(
        (rows, shouldAdd) => {
            setShoppingList((prev) => {
                const next = shouldAdd
                    ? mergeShoppingListItems(prev, rows)
                    : removeShoppingListKeys(
                          prev,
                          rows.map((row) => row.key),
                      );
                writeCustomerShoppingList(userId, next);

                return next;
            });
        },
        [userId],
    );

    const handleToggleFavourite = useCallback(
        (meal) => {
            const id = String(meal.id ?? '');
            const imageUrl = resolveMealImageUrl(meal.imageUrl || meal.detailView?.imageUrl) || undefined;

            setFavourites((prev) => {
                const next = isFavouriteRecipe(prev, id)
                    ? removeFavouriteRecipe(prev, id)
                    : addFavouriteRecipe(prev, favouriteRecipeFromMeal(meal, imageUrl));
                writeCustomerFavourites(userId, next);

                return next;
            });
        },
        [userId],
    );

    const pageIntro = (
        <div className="mb-3 min-w-0">
            <h1 className="font-montserrat text-2xl font-bold tracking-tight text-[#262A22] sm:text-3xl">
                Cook it yourself
            </h1>
            <p className="mt-1 font-body text-sm leading-snug text-[#374151] sm:text-base">
                Open a day, get the recipe, and tick ingredients onto your shopping list.
            </p>
            <p className="mt-1 font-body text-xs text-[#6B7280] sm:text-sm">
                {planCategoryLabel}
                {craftPlan.planDateRange?.label ? ` · ${craftPlan.planDateRange.label}` : null}
                {days.length > 0 ? ` · ${days.length} day${days.length === 1 ? '' : 's'}` : null}
            </p>
        </div>
    );

    const daysScreen = (
        <>
            {pageIntro}
            <p className="mb-3 font-body text-sm text-[#555555]">Pick a day to open your recipes.</p>
            <div className="mx-auto flex w-full max-w-xl flex-col gap-2" role="list" aria-label="Plan days">
                {days.map((day) => {
                    const label = day.dateLabel ? `${day.label} ${day.dateLabel}` : day.label;

                    return (
                        <OnboardingOptionButton
                            key={day.dayNumber}
                            label={label}
                            align="center"
                            selected={day.dayNumber === activeDay}
                            onSelect={() => handleSelectDay(day.dayNumber)}
                        />
                    );
                })}
            </div>
        </>
    );

    const mealsTabBody = (
        <DayMealsTabPanel meals={dayMeals} onOpenMeal={handleViewMealDetails} fullDetails />
    );

    const dayTabBody = (() => {
        switch (contentTab) {
            case 'meals':
                return mealsTabBody;
            case 'macronutrients':
                return (
                    <DayMacronutrientsTabPanel
                        categories={activeDayCategories}
                        dayLabel={activeDayData?.label ?? 'Day'}
                        planCategoryLabel={planCategoryLabel}
                        craftKey={craftPlan.craftKey ?? 'full'}
                        planTierCalories={craftPlan.planTierCalories ?? 0}
                    />
                );
            case 'micronutrients':
                return (
                    <DayMicronutrientsTabPanel
                        categories={activeDayCategories}
                        dayLabel={activeDayData?.label ?? 'Day'}
                        planTierCalories={craftPlan.planTierCalories ?? 0}
                        sex={sex}
                        activityLevel={activityLevel}
                        dailyCalories={dailyCalorieTarget ?? craftPlan.planTierCalories ?? 0}
                    />
                );
            case 'allergies':
                return <DayAllergySafetyTabPanel categories={activeDayCategories} />;
            case 'sickle':
                return <DaySickleCellTabPanel categories={activeDayCategories} />;
            default:
                return null;
        }
    })();

    const dayScreen = (
        <>
            <div className="mb-2 min-w-0">
                {hasMultipleDays ? (
                    <>
                        <div className="flex items-start justify-between gap-3">
                            <h1 className="min-w-0 font-montserrat text-2xl font-bold tracking-tight text-[#262A22] sm:text-3xl">
                                {activeDayLabel}
                            </h1>
                            <Button
                                type="button"
                                label="Close"
                                variant="ghost"
                                size="sm"
                                onClick={() => setStep('days')}
                                className="shrink-0"
                            />
                        </div>
                        <p className="mt-0.5 font-body text-sm text-[#555555]">
                            Every meal, every ingredient, every detail.
                        </p>
                    </>
                ) : (
                    pageIntro
                )}
            </div>

            <div
                className="mb-3 flex gap-1.5 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                role="tablist"
                aria-label="Day information"
            >
                {DAY_SUMMARY_TABS.map((tab) => {
                    const selected = contentTab === tab.id;

                    return (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            aria-selected={selected}
                            onClick={() => setContentTab(/** @type {DaySummaryTabId} */ (tab.id))}
                            className={[
                                'shrink-0 rounded-full px-2.5 py-1 font-montserrat text-[11px] font-bold tracking-wide transition-colors',
                                selected
                                    ? 'bg-[#5A6B44] text-white'
                                    : 'bg-white text-[#555555] ring-1 ring-gray-200 hover:text-[#262A22]',
                            ].join(' ')}
                        >
                            {tab.label}
                        </button>
                    );
                })}
            </div>

            <div className="pb-6">{dayTabBody}</div>
        </>
    );

    const mealScreen =
        selectedMeal != null ? (
            <>
                <div className="mb-3 flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <p className="font-montserrat text-[10px] font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                            {activeDayLabel}
                        </p>
                        <h1 className="mt-0.5 font-montserrat text-xl font-bold tracking-tight text-[#262A22] sm:text-2xl">
                            Recipe details
                        </h1>
                    </div>
                    <Button
                        type="button"
                        label="Back"
                        variant="ghost"
                        size="sm"
                        onClick={handleBackToDay}
                        className="shrink-0"
                    />
                </div>
                <div className="pb-6">
                    <CookMealCard
                        meal={selectedMeal}
                        shoppingList={shoppingList}
                        onToggleIngredient={handleToggleIngredient}
                        favourited={isFavouriteRecipe(favourites, String(selectedMeal.id))}
                        onToggleFavourite={() => handleToggleFavourite(selectedMeal)}
                    />
                </div>
            </>
        ) : null;

    let screen = dayScreen;
    if (step === 'days' && hasMultipleDays) {
        screen = daysScreen;
    } else if (step === 'meal' && selectedMeal != null) {
        screen = mealScreen;
    }

    return (
        <CustomerInertiaShell
            customerName={customerName}
            cartUrl={cartUrl}
            footerNav={
                <CustomerAppBottomNav
                    activeTab="meals"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                    toolsUrl={toolsUrl}
                />
            }
        >
            <div className={`min-h-full font-body ${PAGE_BG}`}>
                <div className="mx-auto w-full max-w-5xl py-3 sm:py-4">{screen}</div>
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 */
export default function CookItYourself(props) {
    return <CookItYourselfInner {...props} />;
}

CookItYourself.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
