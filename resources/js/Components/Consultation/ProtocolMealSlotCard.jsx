import Button from '../Atoms/Button/Button.jsx';
import MealCardClientViewNano from '../MealCardClientViewNano.jsx';

/** Same Nano footprint for breakfast, mains, and every selected slot. */
const SELECTED_CARD_WIDTH = 'w-[280px] max-w-[280px] shrink-0 snap-start sm:w-full sm:max-w-[280px] sm:snap-none';

/**
 * Mobile: horizontal snap scroll — full-width 280px cards; next card peeks on the right.
 * sm+: centered grid (one or two 280px tracks).
 *
 * @param {number} count
 */
function mealCardsLayoutClass(count) {
    if (count <= 1) {
        return 'flex w-full justify-center gap-3 overflow-x-visible px-0';
    }

    return [
        'flex w-full gap-3 overflow-x-auto overscroll-x-contain pl-3 pr-4 pb-1',
        'snap-x snap-mandatory [-webkit-overflow-scrolling:touch] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
        'sm:grid sm:justify-center sm:justify-items-stretch sm:gap-3 sm:overflow-visible sm:px-0 sm:pb-0 sm:snap-none',
        'sm:grid-cols-[repeat(2,minmax(0,280px))]',
    ].join(' ');
}

/**
 * Day-overview slot card: olive border, SEE OTHER OPTIONS, selected old-style meal cards.
 *
 * @param {object} props
 * @param {string} props.title
 * @param {Array<object>} props.selectedMeals
 * @param {boolean} [props.multiSelect]
 * @param {() => void} [props.onSeeOtherOptions]
 * @param {(meal: object) => void} [props.onViewDetails]
 * @param {(meal: object) => void} [props.onEditMeal]
 * @param {string} [props.className]
 */
export default function ProtocolMealSlotCard({
    title,
    selectedMeals = [],
    multiSelect = false,
    onSeeOtherOptions,
    onViewDetails,
    onEditMeal,
    className = '',
}) {
    const meals = Array.isArray(selectedMeals) ? selectedMeals : [];

    return (
        <section
            className={[
                'relative rounded-[12px] border border-[#5A6B44] bg-white',
                className,
            ]
                .join(' ')
                .trim()}
            aria-label={title}
        >
            {typeof onSeeOtherOptions === 'function' ? (
                <div className="absolute right-2 top-2 z-10 sm:right-3 sm:top-3">
                    <Button
                        type="button"
                        label="See other options"
                        variant="secondary"
                        size="sm"
                        onClick={onSeeOtherOptions}
                        className="!h-7 !min-h-0 !rounded-[8px] !px-2 !py-0 !text-[9px] !leading-none !tracking-wide sm:!text-[10px]"
                    />
                </div>
            ) : null}

            <div className={`px-3 pb-3 pt-3 ${typeof onSeeOtherOptions === 'function' ? 'pr-[7.5rem] sm:pr-32' : ''}`}>
                <h3 className="font-montserrat text-sm font-bold text-[#262A22] sm:text-base">{title}</h3>
            </div>

            <div className={multiSelect || meals.length > 0 ? 'px-3 pb-3' : 'pb-1'}>
                {meals.length === 0 ? (
                    <p className="px-4 py-6 text-center font-body text-sm text-[#555555]">
                        No meal selected yet.
                    </p>
                ) : (
                    <div className={mealCardsLayoutClass(meals.length)}>
                        {meals.map((meal, index) => (
                            <div key={String(meal?.id ?? index)} className={SELECTED_CARD_WIDTH}>
                                <MealCardClientViewNano
                                    deck
                                    alignActionsBottom
                                    hideCraftButton
                                    selected
                                    title={String(meal?.title ?? '').trim() || 'Meal'}
                                    imageUrl={typeof meal?.imageUrl === 'string' ? meal.imageUrl : undefined}
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
                )}
            </div>
        </section>
    );
}
