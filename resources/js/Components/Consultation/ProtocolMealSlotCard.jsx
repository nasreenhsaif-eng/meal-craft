import ProtocolMealRow from './ProtocolMealRow.jsx';

/** Same footprint as one Main Meals column on desktop. */
const MEAL_CARD_WIDTH =
    'w-full min-w-0 max-w-full md:w-[calc(50%-0.375rem)] md:max-w-[calc(50%-0.375rem)]';

/**
 * Day-overview slot card: olive border, SEE OTHER OPTIONS, selected meal rows.
 *
 * Cards share the Main Meals size. One or two selections are centered in the section.
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
    const centerPair = meals.length > 0 && meals.length <= 2;

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
                    <div
                        className={[
                            'flex gap-3',
                            // Mobile: scroll when more than one card
                            meals.length > 1
                                ? 'snap-x snap-mandatory overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden md:overflow-visible md:pb-0'
                                : '',
                            // Desktop: center one or two cards at Main Meals width
                            centerPair ? 'justify-center md:flex-wrap' : 'md:flex-wrap md:justify-start',
                        ]
                            .join(' ')
                            .trim()}
                    >
                        {meals.map((meal, index) => (
                            <div
                                key={String(meal?.id ?? index)}
                                className={[
                                    MEAL_CARD_WIDTH,
                                    'overflow-hidden rounded-[10px] border border-gray-200 bg-[#F8F9F6]',
                                    meals.length > 1 ? 'min-w-[78%] shrink-0 snap-start md:min-w-0 md:shrink' : '',
                                ]
                                    .join(' ')
                                    .trim()}
                            >
                                <ProtocolMealRow
                                    meal={meal}
                                    compact
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
