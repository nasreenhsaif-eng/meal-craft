import { useCallback, useEffect, useLayoutEffect, useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import Button from '../../Components/Atoms/Button.jsx';
import MealCardClientViewNano from '../../Components/MealCardClientViewNano.jsx';
import { listDayMealsWithHighlights } from '../../Components/Consultation/DayNutritionalSummaryPanel.jsx';
import CookMealCard from '../../Components/Molecules/Customer/CookMealCard.jsx';
import CustomerAppBottomNav from '../../Components/Molecules/Customer/CustomerAppBottomNav.jsx';
import CustomerDetailSection, {
    caloriesDisplay,
    displayValue,
    humanizeSlug,
    listDisplay,
} from '../../Components/Molecules/Customer/CustomerDetailSection.jsx';
import { DailyTargetsSummaryInner } from '../Onboarding/DailyTargetsSummary.jsx';
import CustomerInertiaShell from '../../Layouts/CustomerInertiaShell.jsx';
import {
    mealFromFavouriteRecipe,
    readCustomerFavourites,
    removeFavouriteRecipe,
    writeCustomerFavourites,
} from '../../customer/customerFavourites.js';
import {
    formatShoppingAmount,
    mergeShoppingListItems,
    readCustomerShoppingList,
    removeShoppingListKeys,
    writeCustomerShoppingList,
} from '../../customer/customerShoppingList.js';
import { resolveInertiaLayoutChild } from '../../lib/resolveInertiaLayoutChild.js';
import { resolveMealImageUrl } from '../../meal-library/resolveMealImageUrl.ts';

/** @typedef {'saved' | 'shopping' | 'targets' | 'body'} ToolsTabId */

const TOOLS_TABS = /** @type {const} */ ([
    { id: 'saved', label: 'Saved', heart: true },
    { id: 'shopping', label: 'Shopping list' },
    { id: 'targets', label: 'Daily targets' },
    { id: 'body', label: 'Body & plan' },
]);

/**
 * @param {{ className?: string; filled?: boolean }} props
 */
function HeartIcon({ className = '', filled = false }) {
    return (
        <svg className={className} width={14} height={14} viewBox="0 0 24 24" fill="none" aria-hidden>
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
 * @param {object | null | undefined} craftPlan
 * @returns {Map<string, object>}
 */
function mealsByIdFromCraftPlan(craftPlan) {
    /** @type {Map<string, object>} */
    const byId = new Map();

    for (const day of craftPlan?.days ?? []) {
        for (const meal of listDayMealsWithHighlights(day?.categories ?? {})) {
            const id = String(meal.id);

            if (!byId.has(id)) {
                byId.set(id, meal);
            }
        }
    }

    return byId;
}

/**
 * @param {object} props
 * @param {string} [props.customerName]
 * @param {object | null} [props.craftPlan]
 * @param {object} props.dailyTargets
 * @param {object | null} [props.bodyPlan]
 * @param {string} [props.profileEditUrl]
 * @param {string} [props.homeUrl]
 * @param {string} [props.profileUrl]
 * @param {string} [props.shopUrl]
 * @param {string} [props.mealPlanSummaryUrl]
 * @param {string} [props.toolsUrl]
 * @param {string} [props.cartUrl]
 * @param {string} [props.recipesUrl]
 */
export function MyToolsInner({
    customerName = '',
    craftPlan = null,
    dailyTargets,
    bodyPlan = null,
    profileEditUrl = '/onboarding/gender',
    homeUrl = '/app',
    profileUrl = '/app/profile',
    shopUrl = '/app/shop',
    mealPlanSummaryUrl = '/app/meal-plan',
    toolsUrl = '/app/tools',
    cartUrl = '/app/cart',
    recipesUrl = '/meal-plan/recipes',
}) {
    const page = usePage();
    const userId = page.props?.auth?.user?.id ?? null;
    const [shoppingList, setShoppingList] = useState(() => readCustomerShoppingList(userId));
    const [favourites, setFavourites] = useState(() => readCustomerFavourites(userId));
    const [selectedMealId, setSelectedMealId] = useState(/** @type {string | null} */ (null));
    /** @type {[ToolsTabId, import('react').Dispatch<import('react').SetStateAction<ToolsTabId>>]} */
    const [activeTab, setActiveTab] = useState(/** @type {ToolsTabId} */ ('saved'));

    useEffect(() => {
        setShoppingList(readCustomerShoppingList(userId));
        setFavourites(readCustomerFavourites(userId));
    }, [userId]);

    const liveMeals = useMemo(() => mealsByIdFromCraftPlan(craftPlan), [craftPlan]);

    const savedMeals = useMemo(
        () =>
            favourites.map((recipe) => {
                const live = liveMeals.get(recipe.id);

                return live ?? mealFromFavouriteRecipe(recipe);
            }),
        [favourites, liveMeals],
    );

    const selectedMeal = useMemo(
        () => savedMeals.find((meal) => String(meal.id) === String(selectedMealId)) ?? null,
        [savedMeals, selectedMealId],
    );

    const birthdayLabel =
        bodyPlan?.dateOfBirth != null && bodyPlan.dateOfBirth !== ''
            ? bodyPlan.age != null
                ? `${bodyPlan.dateOfBirth} (${bodyPlan.age})`
                : bodyPlan.dateOfBirth
            : bodyPlan?.age != null
              ? String(bodyPlan.age)
              : '—';

    const filterItems =
        Array.isArray(bodyPlan?.foodFilters) && bodyPlan.foodFilters.length > 0
            ? bodyPlan.foodFilters
            : bodyPlan?.allergies;

    /** @type {{ label: string; value: string; preserveCase?: boolean }[]} */
    const bodyPlanRows = [
        { label: 'Sex', value: displayValue(bodyPlan?.sexLabel) },
        { label: 'Diet protocol', value: displayValue(bodyPlan?.dietProtocolLabel) },
        { label: 'Birthday / age', value: birthdayLabel, preserveCase: true },
        { label: 'Height', value: bodyPlan?.heightCm != null ? `${bodyPlan.heightCm} cm` : '—' },
        { label: 'Weight', value: bodyPlan?.weightKg != null ? `${bodyPlan.weightKg} kg` : '—' },
        {
            label: 'Target weight',
            value: bodyPlan?.targetWeightKg != null ? `${bodyPlan.targetWeightKg} kg` : '—',
        },
        { label: 'Activity', value: displayValue(bodyPlan?.activityLevelLabel) },
        { label: 'Daily calorie range', value: caloriesDisplay(bodyPlan), preserveCase: true },
        { label: 'Macro style', value: humanizeSlug(bodyPlan?.macroSplitStyle) },
        { label: 'Food filters / allergies', value: listDisplay(filterItems) },
    ];

    useLayoutEffect(() => {
        window.scrollTo(0, 0);
    }, [activeTab, selectedMealId]);

    const handleClear = () => {
        setShoppingList([]);
        writeCustomerShoppingList(userId, []);
    };

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

    const handleRemoveFavourite = useCallback(
        (recipeId) => {
            setFavourites((prev) => {
                const next = removeFavouriteRecipe(prev, recipeId);
                writeCustomerFavourites(userId, next);

                return next;
            });
            setSelectedMealId((current) => (String(current) === String(recipeId) ? null : current));
        },
        [userId],
    );

    const handleSelectTab = useCallback((tabId) => {
        setActiveTab(tabId);
        setSelectedMealId(null);
    }, []);

    const shoppingPanel = (
        <section className="rounded-[16px] border border-gray-200 bg-white px-4 py-5 shadow-sm sm:px-6">
            <h2 className="font-montserrat text-lg font-bold tracking-tight text-[#262A22]">Shopping list</h2>
            <p className="mt-1 font-body text-sm text-[#555555]">Ingredients you checked on your DIY meals.</p>

            {shoppingList.length === 0 ? (
                <p className="mt-4 font-body text-sm text-[#555555]">Your shopping list is empty.</p>
            ) : (
                <ul className="mt-4 space-y-1" role="list">
                    {shoppingList.map((row) => (
                        <li
                            key={row.key}
                            className="flex items-center justify-between gap-3 border-b border-gray-100 px-1 py-2.5 last:border-b-0"
                        >
                            <span className="min-w-0 flex-1 font-body text-sm text-[#262A22]">{row.name}</span>
                            {formatShoppingAmount(row.amount, row.unit) ? (
                                <span className="shrink-0 font-montserrat text-sm font-semibold tabular-nums text-[#555555]">
                                    {formatShoppingAmount(row.amount, row.unit)}
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-4">
                <Button
                    type="button"
                    label="Clear shopping list"
                    variant="ghost"
                    size="sm"
                    className="w-full border border-[#C44F5D] text-[#C44F5D]"
                    disabled={shoppingList.length === 0}
                    onClick={handleClear}
                />
            </div>
        </section>
    );

    const targetsPanel = (
        <section className="rounded-[16px] border border-gray-200 bg-white px-4 py-5 shadow-sm sm:px-6">
            <DailyTargetsSummaryInner targets={dailyTargets} embedded />
        </section>
    );

    const savedPanel =
        selectedMeal != null ? (
            <div className="w-full min-w-0">
                <div className="mb-3 flex items-start justify-between gap-3">
                    <h2 className="font-montserrat text-xl font-bold tracking-tight text-[#262A22] sm:text-2xl">
                        Recipe details
                    </h2>
                    <Button
                        type="button"
                        label="Back"
                        variant="ghost"
                        size="sm"
                        onClick={() => setSelectedMealId(null)}
                        className="shrink-0"
                    />
                </div>
                <CookMealCard
                    meal={selectedMeal}
                    shoppingList={shoppingList}
                    onToggleIngredient={handleToggleIngredient}
                    favourited
                    onToggleFavourite={() => handleRemoveFavourite(selectedMeal.id)}
                />
            </div>
        ) : (
            <section className="rounded-[16px] border border-gray-200 bg-white px-4 py-5 shadow-sm sm:px-6">
                <h2 className="font-montserrat text-lg font-bold tracking-tight text-[#262A22]">Saved</h2>
                <p className="mt-1 font-body text-sm text-[#555555]">Recipes you saved while cooking it yourself.</p>

                {savedMeals.length === 0 ? (
                    <div className="mt-4 text-center">
                        <p className="font-body text-sm text-[#555555]">No favourites yet.</p>
                        <Link
                            href={recipesUrl}
                            className="mt-4 inline-flex font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                        >
                            Open Cook it yourself
                        </Link>
                    </div>
                ) : (
                    <ul className="mt-4 flex flex-wrap justify-center gap-4" role="list">
                        {savedMeals.map((meal) => {
                            const imageUrl = resolveMealImageUrl(meal.imageUrl) || undefined;

                            return (
                                <li key={meal.id} className="shrink-0">
                                    <MealCardClientViewNano
                                        deck
                                        alignActionsBottom
                                        hideCraftButton
                                        title={meal.title}
                                        imageUrl={imageUrl}
                                        macros={meal.macros}
                                        onViewDetails={() => setSelectedMealId(String(meal.id))}
                                    />
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>
        );

    const bodyPanel = (
        <CustomerDetailSection
            title="Body & plan"
            rows={bodyPlanRows}
            action={
                <Link
                    href={profileEditUrl}
                    className="shrink-0 pt-0.5 font-montserrat text-sm font-semibold text-[#5A6B44] hover:underline"
                >
                    Edit
                </Link>
            }
        />
    );

    const tabBody = (() => {
        switch (activeTab) {
            case 'shopping':
                return shoppingPanel;
            case 'targets':
                return targetsPanel;
            case 'body':
                return bodyPanel;
            case 'saved':
            default:
                return savedPanel;
        }
    })();

    return (
        <CustomerInertiaShell
            customerName={customerName}
            cartUrl={cartUrl}
            footerNav={
                <CustomerAppBottomNav
                    activeTab="tools"
                    homeUrl={homeUrl}
                    profileUrl={profileUrl}
                    shopUrl={shopUrl}
                    mealsUrl={mealPlanSummaryUrl}
                    toolsUrl={toolsUrl}
                />
            }
        >
            <div className="flex w-full min-w-0 flex-col gap-4">
                <div>
                    <h1 className="font-montserrat text-3xl font-semibold tracking-tight text-[#262A22]">My Tools</h1>
                    <p className="mt-2 font-body text-sm leading-relaxed text-[#555555] sm:text-base">
                        Saved recipes, shopping list, targets, and body & plan.
                    </p>
                </div>

                <div
                    className="flex gap-1.5 overflow-x-auto pb-0.5 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
                    role="tablist"
                    aria-label="My Tools"
                >
                    {TOOLS_TABS.map((tab) => {
                        const selected = activeTab === tab.id;

                        return (
                            <button
                                key={tab.id}
                                type="button"
                                role="tab"
                                aria-selected={selected}
                                onClick={() => handleSelectTab(/** @type {ToolsTabId} */ (tab.id))}
                                className={[
                                    'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 font-montserrat text-[11px] font-bold tracking-wide transition-colors',
                                    selected
                                        ? 'bg-[#5A6B44] text-white'
                                        : 'bg-white text-[#555555] ring-1 ring-gray-200 hover:text-[#262A22]',
                                ].join(' ')}
                            >
                                {'heart' in tab && tab.heart ? (
                                    <HeartIcon filled={selected} className="shrink-0" />
                                ) : null}
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                <div className="pb-2">{tabBody}</div>
            </div>
        </CustomerInertiaShell>
    );
}

/**
 * @param {object} props
 */
export default function MyTools(props) {
    return <MyToolsInner {...props} />;
}

MyTools.layout = (pageOrProps) => resolveInertiaLayoutChild(pageOrProps);
