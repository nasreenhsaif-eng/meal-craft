import { useCallback, useEffect, useLayoutEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button.jsx';
import OnboardingOptionButton from '../../Components/Atoms/Button/OnboardingOptionButton.jsx';
import SquareCheckbox from '../../Components/Atoms/Icons/SquareCheckbox.jsx';
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
import { MealNutritionSummaryTable } from '../../Components/Molecules/MealDetailView/MealDetailView.tsx';
import NutrientBadge from '../../Components/MealSystem/NutrientBadge.jsx';
import SafetyAlerts from '../../Components/MealSystem/SafetyAlerts.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import {
    addFavouriteRecipe,
    isFavouriteRecipe,
    readCustomerFavourites,
    removeFavouriteRecipe,
    writeCustomerFavourites,
} from '../../customer/customerFavourites.js';
import {
    formatShoppingAmount,
    mergeShoppingListItems,
    readCustomerShoppingList,
    removeShoppingListKeys,
    shoppingIngredientsForMeal,
    writeCustomerShoppingList,
} from '../../customer/customerShoppingList.js';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';
import { mealInstructionStepsForDisplay } from '../../meal-library/mealInstructionsDisplay.ts';
import { G6PD_HIGHLIGHT_BADGE } from '../../meal-library/mealSafetyAndSickle.ts';
import { resolveMealImageUrl } from '../../meal-library/resolveMealImageUrl.ts';

const PAGE_BG = 'bg-[#F8F9F6]';

/** @typedef {'days' | 'day' | 'meal'} CookStep */
/** @typedef {'meals' | 'macronutrients' | 'micronutrients' | 'allergies' | 'sickle'} DaySummaryTabId */

/**
 * @param {{ className?: string; filled?: boolean }} props
 */
function HeartIcon({ className = '', filled = false }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M19.5 12.572 12 20l-7.5-7.428A5 5 0 1 1 12 6.013a5 5 0 1 1 7.5 6.559Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
                fill={filled ? 'currentColor' : 'none'}
            />
        </svg>
    );
}

/**
 * Full DIY recipe detail (description, Get recipe, favourites, nutrition).
 *
 * @param {object} props
 * @param {ReturnType<typeof listDayMealsWithHighlights>[number]} props.meal
 * @param {import('../../Pages/MealPlanLibrary/dayShoppingList.js').DayShoppingIngredient[]} props.shoppingList
 * @param {(rows: import('../../Pages/MealPlanLibrary/dayShoppingList.js').DayShoppingIngredient[], shouldAdd: boolean) => void} props.onToggleIngredient
 * @param {boolean} props.favourited
 * @param {() => void} props.onToggleFavourite
 */
function CookMealCard({ meal, shoppingList, onToggleIngredient, favourited, onToggleFavourite }) {
    const [recipeOpen, setRecipeOpen] = useState(false);
    const detailView = meal.detailView ?? {};
    const mealBlurb = String(detailView.shortDescription || detailView.description || '').trim();
    const scBadges = (meal.sickleCellHighlights ?? []).filter((badge) => badge !== G6PD_HIGHLIGHT_BADGE);
    const shoppingRows = useMemo(() => shoppingIngredientsForMeal(meal), [meal]);
    const checkedKeys = useMemo(() => new Set(shoppingList.map((row) => row.key)), [shoppingList]);

    const instructionSections = Array.isArray(detailView.instructionSections)
        ? detailView.instructionSections
        : [];
    const hasInstructionSections = instructionSections.length > 0;
    const instructionSteps = mealInstructionStepsForDisplay(detailView.instructions ?? []);
    const nutritionalData = detailView.nutritionalData ?? null;
    const nutritionSubheading = String(detailView.nutritionSubheading ?? 'Per serving totals');

    return (
        <article className="rounded-[12px] border border-gray-200 bg-white px-3 py-4 sm:px-4">
            <p className="font-montserrat text-[10px] font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                {meal.category}
            </p>
            <h2 className="mt-1 font-montserrat text-lg font-bold tracking-tight text-[#262A22]">{meal.title}</h2>

            {mealBlurb ? (
                <section className="mt-3 space-y-1">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-[#374151]">
                        Description
                    </p>
                    <p className="font-body text-sm leading-relaxed text-[#555555]">{mealBlurb}</p>
                </section>
            ) : null}

            {meal.hasG6pdTrigger ? (
                <div className="mt-3 rounded-[12px] border-2 border-[#B91C1C] bg-[#FEF2F2] p-3">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#991B1B]">
                        G6PD safety
                    </p>
                    <div className="mt-2">
                        <NutrientBadge type="G6PD Safety Alert" />
                    </div>
                </div>
            ) : null}

            {meal.safetyAlerts?.length > 0 ? (
                <div className="mt-3 space-y-2">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                        Safety alerts
                    </p>
                    <SafetyAlerts alerts={meal.safetyAlerts} />
                </div>
            ) : null}

            {scBadges.length > 0 ? (
                <div className="mt-3 space-y-2">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                        Nutrient highlights
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {scBadges.map((badge) => (
                            <NutrientBadge key={`${meal.id}-${badge}`} type={badge} />
                        ))}
                    </div>
                    <button
                        type="button"
                        className="font-montserrat text-sm font-semibold text-[#5A6B44] underline underline-offset-2 hover:text-[#4A5A38]"
                        onClick={() => {
                            document
                                .getElementById(`cook-nutrition-${meal.id}`)
                                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }}
                    >
                        See full nutritional summary
                    </button>
                </div>
            ) : null}

            <div className="mt-4 flex items-center gap-2">
                <Button
                    type="button"
                    label={recipeOpen ? 'Hide recipe' : 'Get recipe'}
                    variant={recipeOpen ? 'ghost' : 'primary'}
                    size="sm"
                    onClick={() => setRecipeOpen((open) => !open)}
                    className="min-w-[8.5rem]"
                />
                <button
                    type="button"
                    onClick={onToggleFavourite}
                    aria-pressed={favourited}
                    aria-label={favourited ? 'Remove from favourites' : 'Add to favourites'}
                    className={[
                        'inline-flex h-10 w-10 items-center justify-center rounded-full border transition-colors',
                        favourited
                            ? 'border-[#C44F5D]/40 bg-[#FEF2F2] text-[#C44F5D]'
                            : 'border-gray-200 bg-white text-[#6B7280] hover:text-[#C44F5D]',
                    ].join(' ')}
                >
                    <HeartIcon filled={favourited} />
                </button>
            </div>

            {recipeOpen ? (
                <div className="mt-4 space-y-5 border-t border-gray-100 pt-4">
                    <section aria-labelledby={`cook-ingredients-${meal.id}`}>
                        <h3
                            id={`cook-ingredients-${meal.id}`}
                            className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]"
                        >
                            Ingredients
                        </h3>
                        {shoppingRows.length === 0 ? (
                            <p className="mt-2 font-body text-sm text-[#555555]">
                                No ingredient amounts available for this meal.
                            </p>
                        ) : (
                            <ul className="mt-2 space-y-1" role="list">
                                {shoppingRows.map((row) => {
                                    const checked = checkedKeys.has(row.key);
                                    const checkboxId = `cook-shop-${meal.id}-${row.key}`;

                                    return (
                                        <li key={row.key}>
                                            <label
                                                htmlFor={checkboxId}
                                                className="group/item flex cursor-pointer items-center gap-3 rounded-[10px] px-1 py-2 hover:bg-[#F8F9F6]"
                                                onMouseDown={(event) => {
                                                    // Keep the phone scroll position — focusing the
                                                    // sr-only checkbox scrolls the row under the footer.
                                                    event.preventDefault();
                                                }}
                                            >
                                                <input
                                                    id={checkboxId}
                                                    type="checkbox"
                                                    className="sr-only"
                                                    tabIndex={-1}
                                                    checked={checked}
                                                    onChange={() => onToggleIngredient([row], !checked)}
                                                />
                                                <SquareCheckbox checked={checked} presentational />
                                                <span className="min-w-0 flex-1 font-body text-sm text-[#262A22]">
                                                    {row.name}
                                                </span>
                                                <span className="shrink-0 font-montserrat text-sm font-semibold tabular-nums text-[#555555]">
                                                    {formatShoppingAmount(row.amount, row.unit)}
                                                </span>
                                            </label>
                                        </li>
                                    );
                                })}
                            </ul>
                        )}
                        <p className="mt-2 font-body text-xs text-[#6B7280]">
                            Check an ingredient to add it to your shopping list in My Tools.
                        </p>
                    </section>

                    <section aria-labelledby={`cook-instructions-${meal.id}`}>
                        <h3
                            id={`cook-instructions-${meal.id}`}
                            className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]"
                        >
                            Instructions
                        </h3>
                        {hasInstructionSections ? (
                            <div className="mt-3 space-y-5">
                                {instructionSections.map((section) => (
                                    <div key={section.title} className="space-y-2">
                                        <h4 className="font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                                            {section.title}
                                        </h4>
                                        <ol className="list-decimal space-y-3 pl-5 font-body text-sm leading-relaxed text-[#374151] marker:font-bold">
                                            {(section.steps ?? []).map((step, idx) => (
                                                <li key={`${section.title}-${idx}`} className="whitespace-pre-line pl-1">
                                                    {step}
                                                </li>
                                            ))}
                                        </ol>
                                    </div>
                                ))}
                            </div>
                        ) : instructionSteps.length > 0 ? (
                            <ol className="mt-3 list-decimal space-y-3 pl-5 font-body text-sm leading-relaxed text-[#374151] marker:font-bold">
                                {instructionSteps.map((step, idx) => (
                                    <li key={idx} className="whitespace-pre-line pl-1">
                                        {step}
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <p className="mt-2 font-body text-sm text-[#555555]">
                                No cooking instructions for this meal yet.
                            </p>
                        )}
                    </section>
                </div>
            ) : null}

            <section
                className="mt-4 space-y-2 border-t border-gray-100 pt-4"
                aria-labelledby={`cook-nutrition-${meal.id}`}
            >
                <div>
                    <h3
                        id={`cook-nutrition-${meal.id}`}
                        className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]"
                    >
                        Nutritional summary
                    </h3>
                    <p className="mt-1 font-body text-xs text-[#555555]">{nutritionSubheading}</p>
                </div>
                {nutritionalData?.sections?.length ? (
                    <MealNutritionSummaryTable data={nutritionalData} />
                ) : (
                    <p className="font-body text-sm text-[#555555]">No nutrition data for this meal.</p>
                )}
            </section>
        </article>
    );
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {object} [props.craftPlan]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.favouritesUrl]
 * @param {string} [props.toolsUrl]
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
    favouritesUrl = '/app/favourites',
    toolsUrl = '/app/tools',
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
                    : addFavouriteRecipe(prev, {
                          id,
                          title: meal.title ?? 'Meal',
                          imageUrl,
                          category: meal.category,
                      });
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
            footerNav={
                <CustomerAppBottomNav
                    activeTab="meals"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                    favouritesUrl={favouritesUrl}
                    toolsUrl={toolsUrl}
                />
            }
        >
            <div className={`min-h-full font-body ${PAGE_BG}`}>
                <div className="mx-auto w-full max-w-5xl px-2 py-3 sm:px-3 sm:py-4">{screen}</div>
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
