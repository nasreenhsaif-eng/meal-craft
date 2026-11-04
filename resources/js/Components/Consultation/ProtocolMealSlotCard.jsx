import Button from '../Atoms/Button/Button.jsx';
import MealCraftLogo from '../Atoms/Logo/MealCraftLogo.jsx';
import MealCardClientViewNano from '../MealCardClientViewNano.jsx';

/** Fixed meal card width used across consultation selected slots. */
const CARD_PX = 240;

/**
 * Day-overview slot: olive border frame, title + SEE OTHER OPTIONS, selected meal cards.
 * Multi-select uses a horizontal peek carousel — one full 240px card, next card peeks
 * through to the olive border (full width of the framed content area).
 *
 * @param {object} props
 * @param {string} props.title
 * @param {Array<object>} props.selectedMeals
 * @param {boolean} [props.multiSelect]
 * @param {boolean} [props.isLoading] Animated seal while loading (hides meals until ready); quiet empty otherwise.
 * @param {() => void} [props.onSeeOtherOptions]
 * @param {(meal: object) => void} [props.onViewDetails]
 * @param {(meal: object) => void} [props.onEditMeal]
 * @param {string} [props.className]
 */
export default function ProtocolMealSlotCard({
    title,
    selectedMeals = [],
    multiSelect = false,
    isLoading = false,
    onSeeOtherOptions,
    onViewDetails,
    onEditMeal,
    className = '',
}) {
    const meals = Array.isArray(selectedMeals) ? selectedMeals : [];
    const usePeekCarousel = multiSelect || meals.length > 1;

    return (
        <section
            className={['relative w-full min-w-0 rounded-[12px] border border-[#5A6B44] bg-white', className]
                .join(' ')
                .trim()}
            aria-label={title}
        >
            <div className="flex items-start justify-between gap-2 px-3 pb-2 pt-3">
                <h3 className="min-w-0 pt-0.5 font-montserrat text-sm font-bold text-[#262A22] sm:text-base">
                    {title}
                </h3>
                {typeof onSeeOtherOptions === 'function' ? (
                    <Button
                        type="button"
                        label="See other options"
                        variant="secondary"
                        size="sm"
                        onClick={onSeeOtherOptions}
                        className="!h-7 !min-h-0 shrink-0 !rounded-[8px] !px-2 !py-0 !text-[9px] !leading-none !tracking-wide sm:!text-[10px]"
                    />
                ) : null}
            </div>

            <div className={!isLoading && meals.length > 0 ? 'px-3 pb-3' : 'pb-1'}>
                {isLoading ? (
                    <div
                        className="flex justify-center py-6"
                        role="status"
                        aria-live="polite"
                        aria-label="Loading meal options"
                    >
                        <MealCraftLogo variant="minimal-animated" width={88} alt="" loop />
                    </div>
                ) : meals.length === 0 ? (
                    <div className="px-3 pb-3" aria-hidden />
                ) : usePeekCarousel ? (
                    <div
                        className={[
                            /* py/px gutters keep card shadow + ring from being clipped by overflow-x */
                            'w-full min-w-0 overflow-x-auto overscroll-x-contain px-0.5 py-1.5',
                            'snap-x snap-mandatory [-webkit-overflow-scrolling:touch]',
                            '[scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                        ].join(' ')}
                    >
                        <div className="flex w-max gap-3">
                            {meals.map((meal, index) => (
                                <div
                                    key={String(meal?.id ?? index)}
                                    className="shrink-0 snap-start"
                                    style={{ width: CARD_PX }}
                                >
                                    <MealCardClientViewNano
                                        deck
                                        alignActionsBottom
                                        hideCraftButton
                                        selected
                                        title={String(meal?.title ?? '').trim() || 'Meal'}
                                        imageUrl={
                                            typeof meal?.imageUrl === 'string' ? meal.imageUrl : undefined
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
                    </div>
                ) : (
                    <div className="flex w-full justify-center py-1.5">
                        <div className="shrink-0" style={{ width: CARD_PX }}>
                            <MealCardClientViewNano
                                deck
                                alignActionsBottom
                                hideCraftButton
                                selected
                                title={String(meals[0]?.title ?? '').trim() || 'Meal'}
                                imageUrl={
                                    typeof meals[0]?.imageUrl === 'string' ? meals[0].imageUrl : undefined
                                }
                                macros={meals[0]?.macros}
                                onViewDetails={
                                    typeof onViewDetails === 'function'
                                        ? () => onViewDetails(meals[0])
                                        : undefined
                                }
                                onEdit={
                                    typeof onEditMeal === 'function'
                                        ? () => onEditMeal(meals[0])
                                        : undefined
                                }
                            />
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}
