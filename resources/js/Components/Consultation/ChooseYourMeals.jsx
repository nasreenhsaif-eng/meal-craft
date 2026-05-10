import { AnimatePresence, motion } from 'framer-motion';
import { useCallback, useEffect, useMemo, useState } from 'react';
import PillButton from '../Atoms/Button/Button.jsx';
import SquareCheckbox from '../Atoms/SquareCheckbox.jsx';
import StackedDeckCarousel from '../StackedDeckCarousel.jsx';
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
        header: 'Soup of the Day',
        mealTypeLabel: 'Soup',
        defaultMax: 1,
        soupOptional: true,
    },
]);

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
}) {
    const selectedSet = new Set(selectedIds);
    const atLimit = selectedIds.length >= maxSelected;
    const stackZ = 35 + sectionStackOrder * 6;

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
                        {maxSelected === 1 ? 'Select 1' : `Select exactly ${maxSelected}`} • {selectedIds.length}/{maxSelected}{' '}
                        selected • Swipe the deck to browse
                    </p>
                </div>
            ) : null}

            <div
                className={`relative mx-auto flex w-full max-w-full flex-col items-center justify-center overflow-x-clip overflow-y-visible [-webkit-overflow-scrolling:touch] ${deckOnly ? 'mt-0 min-h-[calc(min(90vw,270px)+5.5rem)] py-1.5' : 'mt-0.5 min-h-[calc(min(90vw,270px)+5.5rem)] py-1'}`}
                data-consultation-deck=""
            >
                {cards.length === 0 ? (
                    <p className="font-body text-sm text-[#666666]">No options match this slot yet.</p>
                ) : (
                    <div className="flex w-full max-w-full justify-center">
                        <div className="relative z-0 w-full min-w-0 max-w-full">
                            <StackedDeckCarousel
                                title=""
                                meals={cards}
                                deckScopeKey={deckScopeKey}
                                getKey={(m) => /** @type {ConsultationMeal} */ (m).id}
                                renderCard={(m, _idx, { isFront, deckLayout }) => {
                                    const meal = /** @type {ConsultationMeal} */ (m);
                                    const isSelected = selectedSet.has(meal.id);
                                    const isDisabled = !isSelected && atLimit;

                                    return (
                                        <MealCardClientViewNano
                                            deck
                                            ribbon={deckLayout === 'ribbon'}
                                            deckStackRole={isFront ? 'front' : 'back'}
                                            title={meal.title ?? ''}
                                            imageUrl={meal.imageUrl}
                                            macros={meal.macros}
                                            selected={isSelected}
                                            disabled={isDisabled}
                                            imageLoading={isFront ? 'eager' : 'lazy'}
                                            imageAlt={meal.title ?? ''}
                                            onToggleSelected={() => onSelect(meal)}
                                            onViewDetails={() => {}}
                                            vibrantCraftWhenAtLimit={isDisabled}
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
 * @param {(enabled: boolean) => void} [props.onSoupOptInChange]
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
    onSoupOptInChange,
}) {
    const craftingSubtitle = `CRAFTING YOUR ${String(dayName).trim().toUpperCase()}`;

    const [soupOptIn, setSoupOptIn] = useState(() => (categorySelections?.soup?.length ?? 0) > 0);

    const [validationFlashKeys, setValidationFlashKeys] = useState(/** @type {SelectionCategoryKey[]} */ ([]));

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
        }
    }, [layout, categorySelections]);

    const soupSectionDef = FULL_CRAFT_CATEGORY_SECTIONS.find((s) => s.selectionKey === 'soup');

    const categoriesComplete = useMemo(
        () => (layout === 'categories' ? isFullCraftCategoriesComplete(categorySelections) : true),
        [layout, categorySelections],
    );

    /** Full Craft: gate only on required slot counts; other layouts defer to `footerNextDisabled`. */
    const craftFooterDisabled = layout === 'categories' ? !categoriesComplete : footerNextDisabled;

    const triggerIncompleteFlash = useCallback(() => {
        if (!categorySelections) {
            return;
        }
        const missing = getIncompleteFullCraftCategoryKeys(categorySelections);
        setValidationFlashKeys(missing);
        window.setTimeout(() => setValidationFlashKeys([]), 2200);
    }, [categorySelections]);

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
                <p className="px-4 font-montserrat text-[15px] font-bold leading-snug tracking-tight text-[#262A22] sm:text-base md:px-0">
                    {soupSectionDef.header}
                </p>
                <button
                    type="button"
                    aria-pressed={soupOptIn}
                    className="mt-2 flex w-full max-w-full items-center justify-start gap-3 px-4 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/35 focus-visible:ring-offset-2 sm:mt-2.5 md:px-0"
                    onClick={() => {
                        const next = !soupOptIn;
                        setSoupOptIn(next);
                        if (!next) {
                            onSoupOptInChange?.(false);
                        }
                    }}
                >
                    <SquareCheckbox checked={soupOptIn} presentational className="shrink-0" />
                    <span className="min-w-0 truncate whitespace-nowrap font-body text-xs font-normal leading-none tracking-tight text-[#262A22] sm:text-sm">
                        I would like Soup of the Day (Optional)
                    </span>
                </button>

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
                                cards={filterMealsByCategory(meals, soupSectionDef.mealTypeLabel)}
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

    const scrollAreaBack =
        layout === 'categories' && typeof onFooterBack === 'function' ? (
            <div className="mt-3 border-t border-gray-200/80 pt-3">
                <PillButton label="BACK" variant="outline" size="md" className="min-w-[120px] px-10" onClick={onFooterBack} />
            </div>
        ) : null;

    const mainScrollable =
        layout === 'categories' ? (
            <div className="flex flex-col gap-1.5 md:gap-2">
                {categorySections}
                {soupBlock}
                {scrollAreaBack}
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

    const scrollBottomNext =
        typeof onFooterNext === 'function' ? (
            <div
                className="mt-6 border-t border-gray-200/90 px-4 pb-4 pt-6 max-md:px-4 md:px-6"
                onClick={() => {
                    if (layout === 'categories' && !categoriesComplete) {
                        triggerIncompleteFlash();
                    }
                }}
            >
                <div
                    className={`flex flex-wrap items-center gap-3 ${layout !== 'categories' && typeof onFooterBack === 'function' ? 'justify-between' : 'justify-center'}`}
                >
                    {layout !== 'categories' && typeof onFooterBack === 'function' ? (
                        <PillButton
                            type="button"
                            label="BACK"
                            variant="outline"
                            size="md"
                            className="min-w-[120px] px-10"
                            onClick={onFooterBack}
                        />
                    ) : null}
                    <div className="inline-flex min-h-[50px] min-w-[140px] items-center justify-center">
                        <PillButton
                            type="button"
                            label={footerNextLabel}
                            variant="primary"
                            size="md"
                            disabled={craftFooterDisabled}
                            aria-disabled={craftFooterDisabled}
                            className={[
                                'min-w-[140px] px-10',
                                craftFooterDisabled ? 'pointer-events-none opacity-40' : '',
                            ].join(' ')}
                            onClick={(e) => {
                                e.stopPropagation();
                                if (!craftFooterDisabled) {
                                    onFooterNext?.();
                                }
                            }}
                        />
                    </div>
                </div>
            </div>
        ) : null;

    const showLegacyNavigation = typeof onFooterNext !== 'function' && navigation;

    return (
        <section className="box-border flex h-[100dvh] min-h-screen w-full flex-col overflow-x-clip border border-gray-200 bg-white shadow-sm max-md:rounded-none max-md:border-x-0 md:rounded-[12px]">
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
                <div className="mc-choose-meals-scroll min-h-0 flex-1 overflow-y-auto overscroll-y-contain pt-2 max-md:px-0 max-md:pb-4 md:px-5 md:pb-8 md:pt-4 [-webkit-overflow-scrolling:touch]">
                    <div className="relative z-0 min-w-0 space-y-0">{mainScrollable}</div>
                    {scrollBottomNext}
                </div>

                <div className="sticky bottom-0 z-[120] shrink-0 border-t border-gray-200 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))] shadow-[0_-4px_24px_rgba(15,23,42,0.06)] max-md:px-4 md:px-6">
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
                </div>

                {showLegacyNavigation ? (
                    <div className="relative z-[70] shrink-0 border-t border-gray-200/80 bg-white">{navigation}</div>
                ) : null}
            </div>
        </section>
    );
}
