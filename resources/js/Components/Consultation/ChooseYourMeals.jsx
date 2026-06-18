import { AnimatePresence, motion } from 'framer-motion';
import { useCallback, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import PillButton from '../Atoms/Button/Button.jsx';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';
import StackedDeckCarousel from '../MealCard/StackedDeckCarousel.jsx';
import MealCardClientViewNano from '../MealCardClientViewNano.jsx';

/** @typedef {{ id: string; mealType?: string; category?: string; caloriesNumber?: number }} ConsultationMeal */

/** @typedef {'breakfasts' | 'meals' | 'sideSalads' | 'desserts' | 'soup'} SelectionCategoryKey */

/** Required categories for Full Craft NEXT (soup is optional). */
export const FULL_CRAFT_REQUIRED_SELECTION_KEYS = Object.freeze(
    /** @type {const} */ (['breakfasts', 'meals', 'sideSalads', 'desserts']),
);

/**
 * Match consultation meals to a category label used in fixtures (`mealType` / `category`).
 *
 * @param {ConsultationMeal} meal
 * @param {string} mealTypeLabel
 */
export function mealMatchesConsultationCategory(meal, mealTypeLabel) {
    const mt = meal.mealType ?? '';
    const cat = meal.category ?? '';
    if (mt === mealTypeLabel || cat === mealTypeLabel) {
        return true;
    }
    const normalized = mealTypeLabel.trim().toLowerCase();
    if (mt.toLowerCase() === normalized || cat.toLowerCase() === normalized) {
        return true;
    }
    if (normalized === 'side salad' || normalized === 'side salads') {
        return mt === 'Side salad' || cat === 'Side salad';
    }

    return false;
}

/**
 * @param {ConsultationMeal[]} source
 * @param {string} mealTypeLabel
 */
export function filterMealsByCategory(source, mealTypeLabel) {
    return source.filter((m) => mealMatchesConsultationCategory(m, mealTypeLabel));
}

/**
 * Demo fallback when no production schedule is loaded.
 *
 * @param {ConsultationMeal[]} source
 */
export function soupOfTheDayMeals(source) {
    const soups = filterMealsByCategory(source, 'Soup');

    return soups.length > 0 ? [soups[0]] : [];
}

/** Max cards shown per category deck in consultation (matches fixture / product caps). */
export const CONSULTATION_DECK_OPTION_LIMITS = Object.freeze({
    breakfast: 2,
    meal: 4,
    sidesalad: 2,
    dessert: 2,
});

const CONSULTATION_SLOT_MEAL_TYPE_LABELS = Object.freeze({
    breakfast: 'Breakfast',
    meal: 'Meal',
    sidesalad: 'Side salad',
    dessert: 'Dessert',
    soup: 'Soup',
});

/**
 * Consultation decks always show a fixed number of options per slot — never the full library.
 *
 * @param {ConsultationMeal[]} source
 * @param {'breakfast' | 'meal' | 'sidesalad' | 'dessert' | 'soup'} slotKey
 */
export function consultationDeckOptionsForSlotKey(source, slotKey) {
    const mealTypeLabel = CONSULTATION_SLOT_MEAL_TYPE_LABELS[slotKey];
    if (!mealTypeLabel) {
        return [];
    }

    const filtered = source.filter((meal) => meal.mealType === mealTypeLabel);
    const limit = CONSULTATION_DECK_OPTION_LIMITS[slotKey];

    return limit !== undefined ? filtered.slice(0, limit) : filtered;
}

/**
 * Consultation UI only ever surfaces capped deck options — same shape as the original MOCK_MEALS fixture.
 *
 * @param {ConsultationMeal[]} source
 */
export function buildConsultationDeckCatalog(source) {
    /** @type {ConsultationMeal[]} */
    const catalog = [];
    const seen = new Set();

    for (const slotKey of /** @type {const} */ (['breakfast', 'meal', 'sidesalad', 'dessert'])) {
        for (const meal of consultationDeckOptionsForSlotKey(source, slotKey)) {
            if (!seen.has(meal.id)) {
                seen.add(meal.id);
                catalog.push(meal);
            }
        }
    }

    return catalog;
}

/** Default slot caps for Full Craft (soup optional: max 1 when opted in). */
export const DEFAULT_FULL_CRAFT_MAX_SELECTIONS = Object.freeze({
    breakfasts: 1,
    meals: 2,
    sideSalads: 1,
    desserts: 1,
    soup: 1,
});

export const FULL_CRAFT_CATEGORY_SECTIONS = Object.freeze([
    {
        selectionKey: 'breakfasts',
        deckSuffix: 'breakfast',
        header: 'Choose Your Breakfast',
        mealTypeLabel: 'Breakfast',
        defaultMax: 1,
    },
    {
        selectionKey: 'meals',
        deckSuffix: 'meal',
        header: 'Choose Your Meals of the Day',
        mealTypeLabel: 'Meal',
        defaultMax: 2,
    },
    {
        selectionKey: 'sideSalads',
        deckSuffix: 'sidesalad',
        header: 'Side Salads',
        mealTypeLabel: 'Side salad',
        defaultMax: 1,
    },
    {
        selectionKey: 'desserts',
        deckSuffix: 'dessert',
        header: 'Desserts',
        mealTypeLabel: 'Dessert',
        defaultMax: 1,
    },
    {
        selectionKey: 'soup',
        deckSuffix: 'soup',
        header: 'Soup for this day',
        mealTypeLabel: 'Soup',
        defaultMax: 1,
        soupOptional: true,
    },
]);

/** @typedef {{ calories: number; protein: number; carbs: number; fat: number }} MacroTotals */

/** Category rows for plan / admin day macro breakdown (soup omitted when empty). */
export const PLAN_MACRO_CATEGORY_ROWS = Object.freeze([
    { key: 'breakfasts', label: 'Breakfast', optional: false },
    { key: 'meals', label: 'Meals chosen', optional: false },
    { key: 'sideSalads', label: 'Side salad', optional: false },
    { key: 'desserts', label: 'Desserts', optional: false },
    { key: 'soup', label: 'Soup', optional: true },
]);

/**
 * Sum deck card macros for assigned / selected meals in one category.
 *
 * @param {ConsultationMeal[]} meals
 * @returns {MacroTotals}
 */
export function sumMealCardMacros(meals) {
    return (meals ?? []).reduce(
        (acc, meal) => {
            const macros = meal?.macros ?? {};

            return {
                calories: acc.calories + Number(macros.calories ?? 0),
                protein: acc.protein + Number(macros.protein ?? 0),
                carbs: acc.carbs + Number(macros.carbs ?? 0),
                fat: acc.fat + Number(macros.fat ?? 0),
            };
        },
        { calories: 0, protein: 0, carbs: 0, fat: 0 },
    );
}

/**
 * Build per-category macro segments for a day's assigned meals.
 *
 * @param {Partial<Record<SelectionCategoryKey, ConsultationMeal[]>> | null | undefined} categories
 * @returns {Array<{ key: SelectionCategoryKey; label: string; optional: boolean; itemCount: number; totals: MacroTotals }>}
 */
export function buildCategoryMacroBreakdown(categories) {
    /** @type {Array<{ key: SelectionCategoryKey; label: string; optional: boolean; itemCount: number; totals: MacroTotals }>} */
    const rows = [];

    for (const row of PLAN_MACRO_CATEGORY_ROWS) {
        const items = categories?.[row.key] ?? [];
        if (row.optional && items.length === 0) {
            continue;
        }

        rows.push({
            key: row.key,
            label: row.label,
            optional: row.optional,
            itemCount: items.length,
            totals: sumMealCardMacros(items),
        });
    }

    return rows;
}

/**
 * @param {Partial<Record<SelectionCategoryKey, ConsultationMeal[]>> | null | undefined} categories
 */
export function hasSoupChoiceForDay(categories) {
    return (categories?.soup ?? []).length > 0;
}

/**
 * Sum macros for a day's categories, omitting soup when none assigned.
 *
 * @param {Partial<Record<SelectionCategoryKey, ConsultationMeal[]>> | null | undefined} categories
 * @returns {MacroTotals}
 */
export function sumActiveDayMacros(categories) {
    if (!categories) {
        return { calories: 0, protein: 0, carbs: 0, fat: 0 };
    }

    const keys = /** @type {SelectionCategoryKey[]} */ ([
        'breakfasts',
        'meals',
        'sideSalads',
        'desserts',
        ...(hasSoupChoiceForDay(categories) ? ['soup'] : []),
    ]);

    return sumMealCardMacros(keys.flatMap((key) => categories[key] ?? []));
}

const PLAN_MACRO_CELL_META = Object.freeze([
    { key: 'calories', label: 'Calories', shortLabel: 'Cal', color: '#5A6B44' },
    { key: 'protein', label: 'Protein', shortLabel: 'Pro', color: '#916A00' },
    { key: 'carbs', label: 'Carbs', shortLabel: 'Carb', color: '#8F55A8' },
    { key: 'fat', label: 'Fat', shortLabel: 'Fat', color: '#2F4C9B' },
]);

const PLAN_MACRO_TABLE_GRID =
    'grid grid-cols-[5.5rem_repeat(4,minmax(0,1fr))] items-center gap-x-2 gap-y-2 sm:grid-cols-[6rem_repeat(4,minmax(0,1fr))] sm:gap-x-3';

/** @param {'calories' | 'protein' | 'carbs' | 'fat'} key @param {number | string | null | undefined} raw */
function formatPlanMacroValue(key, raw) {
    const n = Number(raw ?? 0);
    if (key === 'calories') {
        return String(Math.round(n));
    }
    if (!Number.isFinite(n)) {
        return '0';
    }
    return Number.isInteger(n) ? String(n) : String(Number.parseFloat(n.toFixed(1)));
}

/**
 * @param {object} props
 * @param {string} [props.planCategoryLabel]
 */
function PlanMacroTableHeader({ planCategoryLabel = '' }) {
    return (
        <>
            {planCategoryLabel ? (
                <span className="justify-self-start rounded-full bg-[#E8EFE0] px-2.5 py-1 font-montserrat text-[10px] font-semibold text-[#5A6B44] sm:px-3 sm:text-xs">
                    {planCategoryLabel}
                </span>
            ) : (
                <div aria-hidden="true" />
            )}
            {PLAN_MACRO_CELL_META.map((cell) => (
                <p
                    key={cell.key}
                    className="truncate text-center font-montserrat text-[9px] font-semibold uppercase tracking-[0.12em] sm:text-[10px]"
                    style={{ color: cell.color }}
                >
                    {cell.label}
                </p>
            ))}
        </>
    );
}

/**
 * Four aligned macro value cells — no per-row labels (master header supplies column names).
 *
 * @param {object} props
 * @param {MacroTotals} props.macros
 * @param {string} [props.cellClassName]
 * @param {string} [props.lastCellClassName]
 */
function PlanMacroValueCells({ macros, cellClassName = '', lastCellClassName = '' }) {
    return (
        <>
            {PLAN_MACRO_CELL_META.map((cell, index) => (
                <p
                    key={cell.key}
                    className={[
                        'truncate text-center text-sm font-bold tabular-nums leading-none sm:text-[15px]',
                        cellClassName,
                        index === PLAN_MACRO_CELL_META.length - 1 ? lastCellClassName : '',
                    ]
                        .join(' ')
                        .trim()}
                    style={{ color: cell.color }}
                >
                    {formatPlanMacroValue(cell.key, macros?.[cell.key])}
                </p>
            ))}
        </>
    );
}

/**
 * Compact inline macro row — fixed 4-column grid (no fluid shrink overlap).
 *
 * @param {object} props
 * @param {MacroTotals} props.macros
 * @param {string} [props.ariaLabel]
 */
export function PlanMacroSummaryRow({ macros, ariaLabel = 'Macros' }) {
    return (
        <div className="grid w-full grid-cols-4 gap-2 sm:gap-3" role="group" aria-label={ariaLabel}>
            {PLAN_MACRO_CELL_META.map((cell) => (
                <div key={cell.key} className="min-w-0 text-center">
                    <p
                        className="truncate text-sm font-bold tabular-nums leading-none sm:text-[15px]"
                        style={{ color: cell.color }}
                    >
                        {formatPlanMacroValue(cell.key, macros?.[cell.key])}
                    </p>
                </div>
            ))}
        </div>
    );
}

/**
 * Stacked category macro rows for meal plan detail / admin day views.
 *
 * @param {object} props
 * @param {Partial<Record<SelectionCategoryKey, ConsultationMeal[]>>} props.categories
 * @param {string} [props.dayLabel]
 */
export function PlanDayMacroBreakdown({ categories, dayLabel }) {
    const rows = useMemo(() => buildCategoryMacroBreakdown(categories), [categories]);

    if (rows.length === 0) {
        return null;
    }

    return (
        <>
            <p className="col-span-full mt-4 border-t border-gray-100 pt-3 font-montserrat text-[11px] font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                {dayLabel ? `${dayLabel} breakdown` : 'Choice breakdown'}
            </p>
            {rows.map((row) => (
                <div key={row.key} className="contents" role="row">
                    <p
                        className="rounded-l-[10px] bg-[#F8F9F6] py-2 pl-2 font-montserrat text-[11px] font-bold leading-tight text-[#262A22] sm:pl-3 sm:text-xs"
                        role="rowheader"
                    >
                        {row.label}
                    </p>
                    <PlanMacroValueCells
                        macros={row.totals}
                        cellClassName="bg-[#F8F9F6] py-2"
                        lastCellClassName="rounded-r-[10px]"
                    />
                </div>
            ))}
        </>
    );
}

/**
 * Optional soup opt-in control (Full Craft / meal plan detail).
 *
 * @param {object} props
 * @param {boolean} props.checked
 * @param {(next: boolean) => void} props.onChange
 * @param {string} [props.header]
 */
export function SoupOfTheDayOptIn({ checked, onChange, header = 'Soup for this day' }) {
    return (
        <div className="relative isolate w-full overflow-x-clip overflow-y-visible py-0.5">
            <p className="px-4 font-montserrat text-[15px] font-bold leading-snug tracking-tight text-[#262A22] sm:text-base md:px-0">
                {header}
            </p>
            <button
                type="button"
                aria-pressed={checked}
                className="mt-2 flex w-full max-w-full items-center justify-start gap-3 px-4 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/35 focus-visible:ring-offset-2 sm:mt-2.5 md:px-0"
                onClick={() => onChange(!checked)}
            >
                <SquareCheckbox checked={checked} presentational className="shrink-0" />
                <span className="min-w-0 truncate whitespace-nowrap font-body text-xs font-normal leading-none tracking-tight text-[#262A22] sm:text-sm">
                    I would like to add soup for this day (optional)
                </span>
            </button>
        </div>
    );
}

/**
 * Full-width macro panel for meal plan detail pages (day total + category breakdown).
 *
 * @param {object} props
 * @param {MacroTotals} props.activeDayTotals
 * @param {Partial<Record<SelectionCategoryKey, ConsultationMeal[]>>} [props.categories]
 * @param {string} [props.dayLabel]
 * @param {string} [props.planCategoryLabel]
 */
export function PlanMacroSummaryPanel({ activeDayTotals, categories, dayLabel = 'Day', planCategoryLabel = '' }) {
    return (
        <div className="w-full rounded-[12px] border border-gray-200 bg-white px-4 py-4 sm:px-5">
            <div className={PLAN_MACRO_TABLE_GRID} role="table" aria-label={`${dayLabel} macro summary`}>
                <PlanMacroTableHeader planCategoryLabel={planCategoryLabel} />

                <p className="font-montserrat text-[11px] font-bold uppercase tracking-[0.1em] text-[#5A6B44]">
                    {dayLabel} total
                </p>
                <PlanMacroValueCells macros={activeDayTotals} />

                {categories ? <PlanDayMacroBreakdown categories={categories} dayLabel={dayLabel} /> : null}
            </div>
        </div>
    );
}

/**
 * Full Craft: required slots satisfied (breakfast ×1, meals ×2, side ×1, dessert ×1). Soup optional.
 *
 * @param {Partial<Record<SelectionCategoryKey, string[]>> | null | undefined} categorySelections
 */
export function isFullCraftCategoriesComplete(categorySelections) {
    if (!categorySelections) {
        return false;
    }
    return FULL_CRAFT_REQUIRED_SELECTION_KEYS.every((key) => {
        const need = DEFAULT_FULL_CRAFT_MAX_SELECTIONS[key];
        const have = categorySelections[key]?.length ?? 0;
        return have === need;
    });
}

/**
 * @param {Partial<Record<SelectionCategoryKey, string[]>> | null | undefined} categorySelections
 * @returns {SelectionCategoryKey[]}
 */
export function getIncompleteFullCraftCategoryKeys(categorySelections) {
    if (!categorySelections) {
        return [...FULL_CRAFT_REQUIRED_SELECTION_KEYS];
    }
    return FULL_CRAFT_REQUIRED_SELECTION_KEYS.filter((key) => {
        const need = DEFAULT_FULL_CRAFT_MAX_SELECTIONS[key];
        const have = categorySelections[key]?.length ?? 0;
        return have !== need;
    });
}

const INCOMPLETE_SELECTION_LABELS = Object.freeze({
    breakfasts: 'breakfast',
    meals: '2 main meals',
    sideSalads: 'side salad',
    desserts: 'dessert',
});

/**
 * @param {SelectionCategoryKey[]} missingKeys
 */
export function incompleteSelectionWarningMessage(missingKeys) {
    if (missingKeys.length === 0) {
        return 'Select all required meals before continuing.';
    }

    const parts = missingKeys.map((key) => INCOMPLETE_SELECTION_LABELS[key] ?? key);

    if (parts.length === 1) {
        return `Please select a ${parts[0]} before continuing.`;
    }

    return `Please select: ${parts.join(', ')}.`;
}

/**
 * One consultation slot: section header, instructions, and `StackedDeckCarousel` centered in the frame.
 *
 * @param {object} props
 * @param {string} props.title
 * @param {ConsultationMeal[]} props.cards
 * @param {string[]} props.selectedIds
 * @param {number} props.maxSelected
 * @param {(meal: ConsultationMeal) => void} props.onSelect
 * @param {string} [props.deckScopeKey]
 * @param {number} [props.sectionStackOrder]
 * @param {boolean} [props.deckOnly]
 * @param {SelectionCategoryKey} [props.sectionKey]
 * @param {boolean} [props.validationFlash]
 * @param {boolean} [props.readOnly]
 * @param {(meal: ConsultationMeal) => void} [props.onViewDetails]
 * @param {(meal: ConsultationMeal) => void} [props.onEditMeal]
 */
export function MealSlotCarousel({
    title,
    cards,
    selectedIds,
    maxSelected,
    onSelect,
    deckScopeKey,
    sectionStackOrder = 0,
    deckOnly = false,
    sectionKey,
    validationFlash = false,
    readOnly = false,
    onViewDetails,
    onEditMeal,
}) {
    const selectedSet = new Set(selectedIds);
    const atLimit = selectedIds.length >= maxSelected;
    const stackZ = 35 + sectionStackOrder * 6;
    const showSwipeHint = cards.length > 2;

    const deckSubheader = (() => {
        if (readOnly) {
            return showSwipeHint ? `${cards.length} assigned • Swipe the deck to browse` : `${cards.length} assigned`;
        }
        const selectionPart = maxSelected === 1 ? 'Select 1' : `Select exactly ${maxSelected}`;
        const countPart = `${selectedIds.length}/${maxSelected} selected`;
        return showSwipeHint
            ? `${selectionPart} • ${countPart} • Swipe the deck to browse`
            : `${selectionPart} • ${countPart}`;
    })();

    return (
        <div
            data-mc-section={sectionKey ?? ''}
            className={[
                'relative isolate w-full overflow-x-clip overflow-y-visible rounded-xl py-0 transition-[box-shadow] duration-300',
                validationFlash ? 'ring-2 ring-[#C44F5D] ring-offset-2 ring-offset-white' : '',
            ]
                .join(' ')
                .trim()}
            style={{ zIndex: stackZ }}
        >
            {!deckOnly ? (
                <div className="mx-auto min-w-0 max-w-full px-4 text-center md:px-0">
                    <p className="font-montserrat text-[15px] font-bold leading-snug tracking-tight text-[#262A22] sm:text-base">
                        {title}
                    </p>
                    <p className="mt-0.5 font-body text-xs leading-snug text-[#555555] sm:mt-1 sm:text-sm">
                        {deckSubheader}
                    </p>
                </div>
            ) : null}

            <div
                className={`relative mx-auto flex w-full max-w-full flex-col items-center justify-center overflow-y-visible px-4 [-webkit-overflow-scrolling:touch] max-md:overflow-x-clip md:overflow-x-visible md:px-0 ${deckOnly ? 'mt-0 min-h-[calc(min(90vw,280px)+5.5rem)] py-1.5' : 'mt-0.5 min-h-[calc(min(90vw,280px)+5.5rem)] py-1'}`}
                data-consultation-deck=""
            >
                {cards.length === 0 ? (
                    <p className="font-body text-sm text-[#666666]">
                        {readOnly ? 'No meal assigned for this slot yet.' : 'No options match this slot yet.'}
                    </p>
                ) : (
                    <div
                        className={[
                            'w-full',
                            cards.length === 2 ? 'md:mx-auto md:max-w-[680px]' : '',
                        ]
                            .filter(Boolean)
                            .join(' ')}
                    >
                        <div className="relative z-0 w-full min-w-0">
                            <StackedDeckCarousel
                                title=""
                                meals={cards}
                                deckScopeKey={deckScopeKey}
                                getKey={(m) => /** @type {ConsultationMeal} */ (m).id}
                                renderCard={(m, _idx, { isFront, deckLayout }) => {
                                    const meal = /** @type {ConsultationMeal} */ (m);
                                    const isSelected = selectedSet.has(meal.id);
                                    const isDisabled = !readOnly && !isSelected && atLimit;

                                    return (
                                        <MealCardClientViewNano
                                            deck
                                            ribbon={deckLayout === 'ribbon'}
                                            alignActionsBottom={deckLayout === 'staticPair'}
                                            deckStackRole={isFront ? 'front' : 'back'}
                                            title={meal.title ?? ''}
                                            imageUrl={meal.imageUrl}
                                            macros={meal.macros}
                                            selected={!readOnly && isSelected}
                                            assigned={readOnly && isSelected}
                                            disabled={isDisabled}
                                            hideCraftButton={readOnly}
                                            imageLoading={isFront ? 'eager' : 'lazy'}
                                            imageAlt={meal.title ?? ''}
                                            onToggleSelected={readOnly ? undefined : () => onSelect(meal)}
                                            onViewDetails={() => onViewDetails?.(meal)}
                                            onEdit={onEditMeal ? () => onEditMeal(meal) : undefined}
                                            vibrantCraftWhenAtLimit={!readOnly && isDisabled}
                                        />
                                    );
                                }}
                            />
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}

/**
 * @param {object} props
 * @param {() => void} [props.onFooterBack]
 * @param {() => void} [props.onFooterNext]
 * @param {boolean} [props.footerNextDisabled]
 * @param {string} [props.footerNextLabel]
 * @param {string} [props.footerIncompleteMessage]
 * @param {ConsultationMeal[]} [props.scheduledSoupMeals]
 * @param {ConsultationMeal[]} [props.soupCatalogMeals] Full menu catalog for soup fallback (deck meals omit soup).
 * @param {(enabled: boolean) => void} [props.onSoupOptInChange]
 * @param {string} [props.panelClassName] Height class for the viewport-locked panel shell.
 */
export default function ChooseYourMeals({
    dayName = '',
    totalKcal = 0,
    summaryLabel,
    targetCalories = 1200,
    layout = 'custom',
    meals = [],
    maxSelectionsByCategory,
    categorySelections,
    onToggleCategory,
    deckScopePrefix = '',
    selections = [],
    onSelectMeal,
    maxSelected = 1,
    deckScopeKey = 'choose-meals-deck',
    children,
    craftTitle,
    dayProgressLabel,
    hintText = 'Fill every required slot to continue.',
    navigation,
    onFooterBack,
    onFooterNext,
    footerNextDisabled = false,
    footerNextLabel = 'NEXT',
    footerIncompleteMessage = 'Select all required meals before continuing.',
    scheduledSoupMeals = [],
    soupCatalogMeals = [],
    onSoupOptInChange,
    panelClassName = 'h-[100dvh] min-h-screen',
}) {
    const craftingSubtitle = `CRAFTING YOUR ${String(dayName).trim().toUpperCase()}`;

    const [soupOptIn, setSoupOptIn] = useState(() => (categorySelections?.soup?.length ?? 0) > 0);

    const [validationFlashKeys, setValidationFlashKeys] = useState(/** @type {SelectionCategoryKey[]} */ ([]));
    const [incompleteWarning, setIncompleteWarning] = useState(/** @type {string | null} */ (null));

    const scrollContainerRef = useRef(/** @type {HTMLDivElement | null} */ (null));

    useLayoutEffect(() => {
        const scroller = scrollContainerRef.current;
        if (scroller) {
            scroller.scrollTop = 0;
        }

        window.scrollTo(0, 0);
    }, [dayName, dayProgressLabel, deckScopePrefix]);

    const wheelDeltaY = useCallback((event, element) => {
        if (event.deltaMode === 1) {
            return event.deltaY * 16;
        }

        if (event.deltaMode === 2) {
            return event.deltaY * element.clientHeight;
        }

        return event.deltaY;
    }, []);

    const forwardWheelToMealScroller = useCallback(
        (event) => {
            const scroller = scrollContainerRef.current;
            if (!scroller) {
                return;
            }

            const deltaY = wheelDeltaY(event, scroller);
            if (deltaY === 0 || Math.abs(deltaY) <= Math.abs(event.deltaX)) {
                return;
            }

            if (!(event.target instanceof Node) || !scroller.contains(event.target)) {
                return;
            }

            const maxScrollTop = scroller.scrollHeight - scroller.clientHeight;
            if (maxScrollTop <= 0) {
                return;
            }

            const canScrollDown = scroller.scrollTop < maxScrollTop - 1;
            const canScrollUp = scroller.scrollTop > 0;

            if ((deltaY > 0 && canScrollDown) || (deltaY < 0 && canScrollUp)) {
                scroller.scrollTop = Math.max(0, Math.min(maxScrollTop, scroller.scrollTop + deltaY));
                event.preventDefault();
                event.stopPropagation();
            }
        },
        [wheelDeltaY],
    );

    useLayoutEffect(() => {
        const scroller = scrollContainerRef.current;
        if (!scroller) {
            return undefined;
        }

        scroller.addEventListener('wheel', forwardWheelToMealScroller, { capture: true, passive: false });
        document.addEventListener('wheel', forwardWheelToMealScroller, { capture: true, passive: false });

        return () => {
            scroller.removeEventListener('wheel', forwardWheelToMealScroller, { capture: true });
            document.removeEventListener('wheel', forwardWheelToMealScroller, { capture: true });
        };
    }, [forwardWheelToMealScroller]);

    useEffect(() => {
        setSoupOptIn((categorySelections?.soup?.length ?? 0) > 0);
    }, [deckScopePrefix]);

    useEffect(() => {
        if ((categorySelections?.soup?.length ?? 0) > 0) {
            setSoupOptIn(true);
        }
    }, [categorySelections?.soup]);

    useEffect(() => {
        if (layout === 'categories' && categorySelections && isFullCraftCategoriesComplete(categorySelections)) {
            setValidationFlashKeys([]);
            setIncompleteWarning(null);
        }
    }, [layout, categorySelections]);

    useEffect(() => {
        if (layout !== 'categories' && !footerNextDisabled) {
            setIncompleteWarning(null);
        }
    }, [layout, footerNextDisabled]);

    const soupSectionDef = FULL_CRAFT_CATEGORY_SECTIONS.find((s) => s.selectionKey === 'soup');

    const categoriesComplete = useMemo(
        () => (layout === 'categories' ? isFullCraftCategoriesComplete(categorySelections) : true),
        [layout, categorySelections],
    );

    /** Full Craft: gate only on required slot counts; other layouts defer to `footerNextDisabled`. */
    const craftFooterDisabled = layout === 'categories' ? !categoriesComplete : footerNextDisabled;

    const showIncompleteValidation = useCallback(() => {
        if (layout === 'categories') {
            if (!categorySelections) {
                setValidationFlashKeys([...FULL_CRAFT_REQUIRED_SELECTION_KEYS]);
                setIncompleteWarning(incompleteSelectionWarningMessage([...FULL_CRAFT_REQUIRED_SELECTION_KEYS]));
                window.setTimeout(() => setValidationFlashKeys([]), 2200);
                return;
            }

            const missing = getIncompleteFullCraftCategoryKeys(categorySelections);
            setValidationFlashKeys(missing);
            setIncompleteWarning(incompleteSelectionWarningMessage(missing));
            window.setTimeout(() => setValidationFlashKeys([]), 2200);
            return;
        }

        setIncompleteWarning(footerIncompleteMessage);
    }, [layout, categorySelections, footerIncompleteMessage]);

    const handleFooterNextClick = useCallback(() => {
        if (craftFooterDisabled) {
            showIncompleteValidation();
            return;
        }

        setIncompleteWarning(null);
        onFooterNext?.();
    }, [craftFooterDisabled, onFooterNext, showIncompleteValidation]);

    const categorySections = useMemo(() => {
        if (layout !== 'categories' || !meals?.length || !categorySelections || typeof onToggleCategory !== 'function') {
            return null;
        }

        const nonSoup = FULL_CRAFT_CATEGORY_SECTIONS.filter((def) => def.selectionKey !== 'soup');

        return nonSoup.map((def, idx) => {
            const cards = filterMealsByCategory(meals, def.mealTypeLabel);
            const max =
                maxSelectionsByCategory?.[def.selectionKey] !== undefined
                    ? /** @type {number} */ (maxSelectionsByCategory[def.selectionKey])
                    : def.defaultMax;
            const selectedIds = categorySelections[def.selectionKey] ?? [];
            const prefix = deckScopePrefix ? `${deckScopePrefix}-` : '';
            const flash = validationFlashKeys.includes(def.selectionKey);

            return (
                <MealSlotCarousel
                    key={def.selectionKey}
                    sectionKey={def.selectionKey}
                    validationFlash={flash}
                    sectionStackOrder={idx}
                    title={def.header}
                    deckScopeKey={`${prefix}${def.deckSuffix}`}
                    cards={cards}
                    selectedIds={selectedIds}
                    maxSelected={max}
                    onSelect={(meal) => onToggleCategory(def.selectionKey, meal)}
                />
            );
        });
    }, [
        layout,
        meals,
        categorySelections,
        onToggleCategory,
        maxSelectionsByCategory,
        deckScopePrefix,
        validationFlashKeys,
    ]);

    const soupDeckMeals = useMemo(() => {
        if (scheduledSoupMeals.length > 0) {
            return scheduledSoupMeals;
        }

        const catalog = soupCatalogMeals.length > 0 ? soupCatalogMeals : meals;

        return soupOfTheDayMeals(catalog);
    }, [meals, scheduledSoupMeals, soupCatalogMeals]);

    const soupBlock =
        layout === 'categories' &&
        meals?.length &&
        categorySelections &&
        soupSectionDef &&
        typeof onToggleCategory === 'function' ? (
            <div
                className="relative isolate w-full overflow-x-clip overflow-y-visible py-0.5"
                style={{ zIndex: 35 + FULL_CRAFT_CATEGORY_SECTIONS.length * 6 }}
            >
                <SoupOfTheDayOptIn
                    checked={soupOptIn}
                    header={soupSectionDef.header}
                    onChange={(next) => {
                        setSoupOptIn(next);
                        if (!next) {
                            onSoupOptInChange?.(false);
                        }
                    }}
                />

                <AnimatePresence initial={false}>
                    {soupOptIn ? (
                        <motion.div
                            key="soup-deck"
                            initial={{ opacity: 0, y: 12, scale: 0.97 }}
                            animate={{ opacity: 1, y: 0, scale: 1 }}
                            exit={{ opacity: 0, y: 8, scale: 0.98 }}
                            transition={{ type: 'spring', stiffness: 320, damping: 34 }}
                            className="overflow-hidden"
                        >
                            <MealSlotCarousel
                                sectionStackOrder={FULL_CRAFT_CATEGORY_SECTIONS.length}
                                deckOnly
                                title=""
                                deckScopeKey={`${deckScopePrefix ? `${deckScopePrefix}-` : ''}${soupSectionDef.deckSuffix}`}
                                cards={soupDeckMeals}
                                selectedIds={categorySelections.soup ?? []}
                                maxSelected={
                                    maxSelectionsByCategory?.soup !== undefined
                                        ? /** @type {number} */ (maxSelectionsByCategory.soup)
                                        : soupSectionDef.defaultMax
                                }
                                onSelect={(meal) => onToggleCategory('soup', meal)}
                            />
                        </motion.div>
                    ) : null}
                </AnimatePresence>
            </div>
        ) : null;

    const legacySingleDeck =
        layout !== 'categories' &&
        !children &&
        Array.isArray(selections) &&
        meals?.length &&
        typeof onSelectMeal === 'function';

    const mainScrollable =
        layout === 'categories' ? (
            <div className="flex flex-col gap-1.5 md:gap-2">
                {categorySections}
                {soupBlock}
            </div>
        ) : children ? (
            children
        ) : legacySingleDeck ? (
            <MealSlotCarousel
                sectionStackOrder={0}
                title="Meals"
                cards={meals}
                selectedIds={selections}
                maxSelected={maxSelected}
                onSelect={onSelectMeal}
                deckScopeKey={deckScopeKey}
            />
        ) : null;

    const showStickyFooterNav = typeof onFooterNext === 'function';

    const showLegacyNavigation = typeof onFooterNext !== 'function' && navigation;

    return (
        <section
            className={`box-border flex w-full flex-col overflow-x-clip border border-gray-200 bg-white shadow-sm max-md:rounded-none max-md:border-x-0 max-md:shadow-none md:rounded-[12px] ${panelClassName}`.trim()}
        >
            <div className="shrink-0 border-b border-gray-200 px-4 py-3 text-left max-md:px-4 sm:px-5 sm:py-4 md:p-6">
                <div className="min-w-0 space-y-1 sm:space-y-1.5">
                    <p className="font-montserrat text-[15px] font-bold leading-snug tracking-tight text-[#262A22] sm:text-[16px]">
                        {craftingSubtitle}
                    </p>
                    {dayProgressLabel ? (
                        <p className="font-body text-sm leading-snug text-[#555555]">{dayProgressLabel}</p>
                    ) : null}
                    {craftTitle ? (
                        <p className="min-w-0 truncate whitespace-nowrap font-montserrat text-[10px] font-bold leading-none tracking-[0.1em] text-[#555555] sm:text-[11px] sm:tracking-[0.12em] md:text-xs md:tracking-[0.14em]">
                            <span className="uppercase">{craftTitle}</span>{' '}
                            <span className="font-normal normal-case tracking-normal text-[#6B7280]">of</span>{' '}
                            <span className="tabular-nums tracking-normal text-[#6B7280]">{Math.round(targetCalories)} CAL</span>
                        </p>
                    ) : null}
                </div>
            </div>

            <div className="flex min-h-0 flex-1 flex-col overflow-x-clip">
                <div
                    ref={scrollContainerRef}
                    className="mc-choose-meals-scroll min-h-0 flex-1 overflow-y-auto overscroll-y-contain pt-2 max-md:px-0 max-md:pb-4 md:px-5 md:pb-8 md:pt-4 [-webkit-overflow-scrolling:touch]"
                >
                    <div className="relative z-0 min-w-0 space-y-0">{mainScrollable}</div>
                </div>

                <div className="z-[120] shrink-0 border-t border-gray-200 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-[0_-4px_24px_rgba(15,23,42,0.06)] max-md:px-4 md:sticky md:bottom-0 md:px-6">
                    {incompleteWarning ? (
                        <div
                            className="mb-3 rounded-[12px] border border-red-200 bg-red-50 px-4 py-3"
                            role="alert"
                            aria-live="polite"
                        >
                            <p className="font-body text-sm font-semibold text-red-800">{incompleteWarning}</p>
                        </div>
                    ) : null}

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        {summaryLabel ? (
                            <p className="font-montserrat text-sm font-bold text-[#262A22]">{summaryLabel}</p>
                        ) : (
                            <span className="min-w-0 flex-1" />
                        )}
                        <p className="font-montserrat text-sm font-bold tabular-nums text-[#1F2937]">
                            Total: {Math.round(totalKcal)} kcal
                        </p>
                    </div>
                    {hintText ? <p className="mt-1.5 font-body text-xs text-[#555555]">{hintText}</p> : null}

                    {showStickyFooterNav ? (
                        <div
                            className={`mt-3 flex flex-wrap items-center gap-3 ${typeof onFooterBack === 'function' ? 'justify-between' : 'justify-center'}`}
                        >
                            {typeof onFooterBack === 'function' ? (
                                <PillButton
                                    type="button"
                                    label="BACK"
                                    variant="outline"
                                    size="md"
                                    className="min-w-[120px] px-10"
                                    onClick={onFooterBack}
                                />
                            ) : (
                                <span className="hidden min-w-[120px] sm:block" aria-hidden="true" />
                            )}
                            <PillButton
                                type="button"
                                label={footerNextLabel}
                                variant="primary"
                                size="md"
                                aria-disabled={craftFooterDisabled}
                                className={['min-w-[140px] px-10', craftFooterDisabled ? 'opacity-60' : ''].join(' ')}
                                onClick={handleFooterNextClick}
                            />
                        </div>
                    ) : null}
                </div>

                {showLegacyNavigation ? (
                    <div className="relative z-[70] shrink-0 border-t border-gray-200/80 bg-white">{navigation}</div>
                ) : null}
            </div>
        </section>
    );
}
