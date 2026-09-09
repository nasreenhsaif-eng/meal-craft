import { useState } from 'react';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';
import MealCardClientViewNano from '../MealCardClientViewNano.jsx';
import {
    FIXED_CHOICE_MAX_COUNT,
    FIXED_CHOICE_MIN_COUNT,
    FIXED_CHOICE_TOGGLE_OPTIONS,
    countFixedChoiceSelections,
    resolveFixedChoiceSelectedMeals,
} from '../../consultation/fixedChoiceSelection.js';

/** @param {unknown} id */
function normalizeMealId(id) {
    if (id == null) {
        return '';
    }

    return String(id);
}

/** Portrait Nano card footprint under a checked side category. */
const MEAL_CARD_WIDTH = 'w-full max-w-[280px] shrink-0';

/**
 * ND protocol sides: checkbox per category; expand selected old-style meal cards when checked.
 *
 * @param {object} props
 * @param {Partial<Record<'sideSalads'|'desserts'|'soup', string[]>>} props.categorySelections
 * @param {Partial<Record<'sideSalads'|'desserts'|'soup', object[]>>} props.displayDecks
 * @param {boolean} [props.readOnly]
 * @param {boolean} [props.validationFlash]
 * @param {(categoryKey: 'sideSalads'|'desserts'|'soup', meal: object) => void} [props.onSelectMeal]
 * @param {(categoryKey: 'sideSalads'|'desserts'|'soup') => void} [props.onClearCategory]
 * @param {(categoryKey: 'sideSalads'|'desserts'|'soup') => void} [props.onSeeOtherOptions]
 * @param {(meal: object) => void} [props.onViewDetails]
 * @param {(meal: object) => void} [props.onEditMeal]
 * @param {string} [props.className]
 */
export default function ProtocolFixedChoiceSides({
    categorySelections,
    displayDecks = {},
    readOnly = false,
    validationFlash = false,
    onSelectMeal,
    onClearCategory,
    onSeeOtherOptions,
    onViewDetails,
    onEditMeal,
    className = '',
}) {
    const [limitWarning, setLimitWarning] = useState(/** @type {string | null} */ (null));
    const pickEnabled = typeof onSelectMeal === 'function' && !readOnly;
    const fixedChoiceCount = countFixedChoiceSelections(categorySelections);

    /**
     * @param {'sideSalads'|'desserts'|'soup'} categoryKey
     * @param {object[]} cards
     * @param {boolean} isChecked
     */
    const toggleCategory = (categoryKey, cards, isChecked) => {
        if (!pickEnabled) {
            return;
        }

        setLimitWarning(null);

        if (isChecked) {
            onClearCategory?.(categoryKey);

            return;
        }

        if (fixedChoiceCount >= FIXED_CHOICE_MAX_COUNT) {
            setLimitWarning('You can pick a maximum of 2 sides. Deselect one to choose a different option.');

            return;
        }

        const recommended = cards.find((meal) => meal?.isRecommended) ?? cards[0];

        if (!recommended?.id) {
            return;
        }

        onSelectMeal(categoryKey, recommended);
    };

    return (
        <section
            className={[
                'rounded-[12px] border border-[#5A6B44] bg-white',
                validationFlash ? 'ring-2 ring-[#C44F5D] ring-offset-2' : '',
                className,
            ]
                .join(' ')
                .trim()}
            aria-label="Pick 1 to 2 of 3 sides"
        >
            <div className="border-b border-gray-100 px-4 py-3">
                <h2 className="font-montserrat text-base font-bold text-[#262A22] sm:text-lg">
                    Pick 1–2 of 3 sides
                </h2>
                <p className="mt-0.5 font-body text-sm text-[#555555]">
                    Side salad, soup, or dessert • {fixedChoiceCount}/{FIXED_CHOICE_MAX_COUNT} selected (min{' '}
                    {FIXED_CHOICE_MIN_COUNT})
                </p>
            </div>

            {limitWarning ? (
                <div className="border-b border-red-100 bg-red-50 px-4 py-2" role="alert" aria-live="polite">
                    <p className="font-body text-xs font-semibold text-red-800 sm:text-sm">{limitWarning}</p>
                </div>
            ) : null}

            <ul className="m-0 list-none divide-y divide-gray-100 p-0">
                {FIXED_CHOICE_TOGGLE_OPTIONS.map((option) => {
                    const assignedCards = displayDecks?.[option.selectionKey] ?? [];
                    const cards =
                        assignedCards.length > 0
                            ? assignedCards
                            : Object.values(displayDecks ?? {})
                                  .flat()
                                  .filter((meal) => {
                                      const label = String(meal?.mealType ?? meal?.category ?? '').toLowerCase();
                                      const expected = String(option.mealTypeLabel ?? option.label).toLowerCase();

                                      return label === expected || label === `${expected}s`;
                                  });
                    const selectedIds = (categorySelections?.[option.selectionKey] ?? []).map((id) =>
                        normalizeMealId(id),
                    );
                    const selectedMeals = resolveFixedChoiceSelectedMeals(
                        selectedIds,
                        cards,
                        displayDecks,
                    );
                    const isChecked = selectedMeals.length > 0 || selectedIds.length > 0;
                    const hasOptions = cards.length > 0;

                    return (
                        <li key={option.selectionKey} className="px-3 py-3 sm:px-4">
                            <div className="flex items-start gap-3">
                                <button
                                    type="button"
                                    className="mt-0.5 inline-flex size-5 shrink-0 items-center justify-center rounded-[4px] border-0 bg-transparent p-0 outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-1 disabled:opacity-50"
                                    disabled={!pickEnabled || !hasOptions}
                                    onClick={() => toggleCategory(option.selectionKey, cards, isChecked)}
                                    aria-pressed={isChecked}
                                    aria-label={
                                        isChecked
                                            ? `Deselect ${option.label}`
                                            : `Select ${option.label}`
                                    }
                                >
                                    <SquareCheckbox checked={isChecked} presentational />
                                </button>

                                <div className="min-w-0 flex-1">
                                    <div className="flex items-center justify-between gap-2">
                                        <button
                                            type="button"
                                            className="text-left font-montserrat text-sm font-bold text-[#262A22] disabled:opacity-50"
                                            disabled={!pickEnabled || !hasOptions}
                                            onClick={() => toggleCategory(option.selectionKey, cards, isChecked)}
                                        >
                                            {option.label}
                                        </button>

                                        {isChecked && hasOptions && typeof onSeeOtherOptions === 'function' && pickEnabled ? (
                                            <button
                                                type="button"
                                                onClick={() => onSeeOtherOptions(option.selectionKey)}
                                                className="shrink-0 rounded-[8px] border border-[#5A6B44]/40 bg-[#F8F9F6] px-2 py-1 font-montserrat text-[10px] font-bold uppercase tracking-wide text-[#5A6B44] hover:bg-[#6E8C47]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] sm:text-[11px]"
                                            >
                                                SEE OTHER OPTIONS
                                            </button>
                                        ) : null}
                                    </div>

                                    {!hasOptions ? (
                                        <p className="mt-1 font-body text-xs text-[#666666]">
                                            No {option.label.toLowerCase()} options for this day yet.
                                        </p>
                                    ) : null}

                                    {isChecked && selectedMeals.length > 0 ? (
                                        <div className="mt-3 flex flex-wrap justify-center gap-3">
                                            {selectedMeals.map((meal, index) => (
                                                <div
                                                    key={normalizeMealId(meal?.id) || index}
                                                    className={MEAL_CARD_WIDTH}
                                                >
                                                    <MealCardClientViewNano
                                                        deck
                                                        alignActionsBottom
                                                        hideCraftButton
                                                        selected
                                                        title={String(meal?.title ?? '').trim() || 'Meal'}
                                                        imageUrl={
                                                            typeof meal?.imageUrl === 'string'
                                                                ? meal.imageUrl
                                                                : undefined
                                                        }
                                                        macros={meal?.macros}
                                                        onViewDetails={
                                                            typeof onViewDetails === 'function'
                                                                ? () => onViewDetails(meal)
                                                                : undefined
                                                        }
                                                        onEdit={
                                                            typeof onEditMeal === 'function'
                                                                ? () => onEditMeal(meal)
                                                                : undefined
                                                        }
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    ) : null}
                                </div>
                            </div>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
