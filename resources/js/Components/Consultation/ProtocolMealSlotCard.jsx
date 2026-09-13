import MealCardClientViewNano from '../MealCardClientViewNano.jsx';

/**
 * Full-width grid with 280px tracks + justify-center.
 * Shrink-wrapped flex (`w-max`) resolved to 100% and left the pair on the left edge.
 */
function mealCardsGridClass(count) {
    return [
        'grid w-full justify-center justify-items-stretch gap-3',
        count >= 2
            ? 'grid-cols-[minmax(0,280px)] sm:grid-cols-[repeat(2,minmax(0,280px))]'
            : 'grid-cols-[minmax(0,280px)]',
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
                <div className="absolute right-3 top-3 z-10">
                    <button
                        type="button"
                        onClick={onSeeOtherOptions}
                        className="rounded-[8px] border border-[#5A6B44]/40 bg-[#F8F9F6] px-2.5 py-1.5 font-montserrat text-[10px] font-bold uppercase tracking-wide text-[#5A6B44] hover:bg-[#6E8C47]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] sm:text-[11px]"
                    >
                        SEE OTHER OPTIONS
                    </button>
                </div>
            ) : null}

            <div className={`px-3 pb-3 pt-3 ${typeof onSeeOtherOptions === 'function' ? 'sm:pr-36' : ''}`}>
                <h3 className="font-montserrat text-sm font-bold text-[#262A22] sm:text-base">{title}</h3>
            </div>

            <div className={multiSelect || meals.length > 0 ? 'px-3 pb-3' : 'pb-1'}>
                {meals.length === 0 ? (
                    <p className="px-4 py-6 text-center font-body text-sm text-[#555555]">
                        No meal selected yet.
                    </p>
                ) : (
                    <div className={mealCardsGridClass(meals.length)}>
                        {meals.map((meal, index) => (
                            <div key={String(meal?.id ?? index)} className="min-w-0 w-full">
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
