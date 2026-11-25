import { useState } from 'react';
import CustomerMealCard from '../CustomerMealCard.jsx';
import StackedDeckCarousel from '../MealCard/StackedDeckCarousel.jsx';
import {
    FIXED_CHOICE_MAX_COUNT,
    FIXED_CHOICE_MIN_COUNT,
    FIXED_CHOICE_TOGGLE_OPTIONS,
    applyFixedChoiceToggle,
    countFixedChoiceSelections,
} from '../../consultation/fixedChoiceSelection.js';

/** @param {unknown} id */
function normalizeMealId(id) {
    if (id == null) {
        return '';
    }

    return String(id);
}

/**
 * Side salad, dessert, and soup — every option in front, same deck as breakfast and main meals.
 * Checked cards come from `categorySelections` (seeded from the admin portal).
 *
 * @param {object} props
 * @param {Partial<Record<'sideSalads'|'desserts'|'soup', string[]>>} props.categorySelections
 * @param {Partial<Record<'sideSalads'|'desserts'|'soup', object[]>>} props.displayDecks
 * @param {boolean} [props.readOnly]
 * @param {boolean} [props.validationFlash]
 * @param {(categoryKey: 'sideSalads'|'desserts'|'soup', meal: object) => void} [props.onSelectMeal]
 * @param {(meal: object) => void} [props.onViewDetails]
 * @param {(meal: object) => void} [props.onEditMeal]
 * @param {string} [props.deckScopePrefix]
 * @param {string} [props.className]
 */
export default function ProtocolFixedChoiceSides({
    categorySelections,
    displayDecks = {},
    readOnly = false,
    validationFlash = false,
    onSelectMeal,
    onViewDetails,
    onEditMeal,
    deckScopePrefix = '',
    className = '',
}) {
    const [limitSection, setLimitSection] = useState(/** @type {'sideSalads' | 'desserts' | 'soup' | null} */ (null));
    const pickEnabled = typeof onSelectMeal === 'function' && !readOnly;
    const fixedChoiceCount = countFixedChoiceSelections(categorySelections);
    const limitWarningMessage =
        'You can pick a maximum of 2 sides. Deselect one to choose a different option.';

    /**
     * @param {'sideSalads'|'desserts'|'soup'} categoryKey
     * @param {object} meal
     */
    const handleSelect = (categoryKey, meal) => {
        if (!pickEnabled || !meal?.id) {
            return;
        }

        const { blocked } = applyFixedChoiceToggle(categorySelections ?? {}, categoryKey, meal.id);

        if (blocked) {
            setLimitSection(categoryKey);

            return;
        }

        setLimitSection(null);
        onSelectMeal(categoryKey, meal);
    };

    return (
        <section
            className={[
                'flex flex-col gap-3',
                validationFlash ? 'rounded-xl ring-2 ring-[#C44F5D] ring-offset-2 ring-offset-white' : '',
                className,
            ]
                .join(' ')
                .trim()}
            aria-label="Pick 1 to 2 of 3 sides"
        >
            <div className="px-4 text-center">
                <h2 className="font-montserrat text-[15px] font-bold leading-snug tracking-tight text-heading sm:text-base">
                    Pick 1–2 of 3 sides
                </h2>
                <p className="mt-0.5 font-body text-xs leading-snug text-body sm:text-sm">
                    Side salad, soup, or dessert • {fixedChoiceCount}/{FIXED_CHOICE_MAX_COUNT} selected (min{' '}
                    {FIXED_CHOICE_MIN_COUNT})
                </p>
            </div>

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
                const selectedIds = new Set(
                    (categorySelections?.[option.selectionKey] ?? []).map((id) => normalizeMealId(id)),
                );
                const prefix = deckScopePrefix ? `${deckScopePrefix}-` : '';

                return (
                    <div key={option.selectionKey} className="relative w-full overflow-x-clip">
                        <div className="px-4 text-center">
                            <p className="font-montserrat text-[15px] font-bold leading-snug tracking-tight text-heading sm:text-base">
                                {option.header}
                            </p>
                            <p className="mt-0.5 font-body text-xs leading-snug text-body sm:text-sm">
                                {cards.length === 0
                                    ? `No ${option.label.toLowerCase()} options for this day yet.`
                                    : `${cards.length} option${cards.length === 1 ? '' : 's'} • ${selectedIds.size}/1 selected`}
                            </p>
                        </div>

                        {limitSection === option.selectionKey ? (
                            <p className="mx-auto mt-2 max-w-full px-4 text-center" role="alert">
                                <span className="inline-block rounded-[10px] border border-red-200 bg-red-50 px-3 py-2 font-body text-xs font-semibold text-red-800 sm:text-sm">
                                    {limitWarningMessage}
                                </span>
                            </p>
                        ) : null}

                        {cards.length > 0 ? (
                            <div className="relative mx-auto mt-0.5 flex w-full max-w-full flex-col items-center px-4 py-1">
                                <StackedDeckCarousel
                                    meals={cards}
                                    deckScopeKey={`${prefix}${option.deckSuffix}`}
                                    forceRibbon={cards.length > 1}
                                    getKey={(meal) => normalizeMealId(meal?.id)}
                                    renderCard={(meal, _index, { isFront, deckLayout }) => {
                                        const isSelected = selectedIds.has(normalizeMealId(meal?.id));

                                        return (
                                            <CustomerMealCard
                                                deck
                                                ribbon={deckLayout === 'ribbon'}
                                                alignActionsBottom={
                                                    deckLayout === 'staticPair' || deckLayout === 'ribbon'
                                                }
                                                deckStackRole={isFront ? 'front' : 'back'}
                                                title={String(meal?.title ?? '').trim() || 'Meal'}
                                                imageUrl={
                                                    typeof meal?.imageUrl === 'string' ? meal.imageUrl : undefined
                                                }
                                                macros={meal?.macros}
                                                selected={isSelected}
                                                disabled={false}
                                                hideCraftButton={readOnly}
                                                imageLoading={isFront ? 'eager' : 'lazy'}
                                                imageAlt={String(meal?.title ?? '')}
                                                onToggleSelected={
                                                    pickEnabled ? () => handleSelect(option.selectionKey, meal) : undefined
                                                }
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
                                        );
                                    }}
                                />
                            </div>
                        ) : null}
                    </div>
                );
            })}
        </section>
    );
}
