import ProtocolMealRow from './ProtocolMealRow.jsx';

/**
 * Day-overview slot card: olive border, SEE OTHER OPTIONS, selected meal rows.
 *
 * Multi-select (mains): two cards side-by-side on desktop; horizontal scroll stack on mobile.
 *
 * @param {object} props
 * @param {string} props.title
 * @param {Array<object>} props.selectedMeals
 * @param {boolean} [props.multiSelect]
 * @param {() => void} [props.onSeeOtherOptions]
 * @param {(meal: object) => void} [props.onViewDetails]
 * @param {string} [props.className]
 */
export default function ProtocolMealSlotCard({
    title,
    selectedMeals = [],
    multiSelect = false,
    onSeeOtherOptions,
    onViewDetails,
    className = '',
}) {
    const meals = Array.isArray(selectedMeals) ? selectedMeals : [];
    const useDualLayout = multiSelect && meals.length > 1;

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
                ) : useDualLayout ? (
                    <div
                        className={[
                            // Mobile: horizontal scroll of narrower cards
                            'flex snap-x snap-mandatory gap-3 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                            // Desktop: both selections on one row
                            'md:grid md:grid-cols-2 md:gap-3 md:overflow-visible md:pb-0',
                        ].join(' ')}
                    >
                        {meals.map((meal, index) => (
                            <div
                                key={String(meal?.id ?? index)}
                                className={[
                                    'min-w-[78%] shrink-0 snap-start overflow-hidden rounded-[10px] border border-gray-200 bg-white',
                                    'md:min-w-0 md:shrink',
                                    index > 0 ? 'md:border-l-2 md:border-l-gray-200' : '',
                                ].join(' ')}
                            >
                                <ProtocolMealRow
                                    meal={meal}
                                    compact
                                    onViewDetails={
                                        typeof onViewDetails === 'function'
                                            ? () => onViewDetails(meal)
                                            : undefined
                                    }
                                />
                            </div>
                        ))}
                    </div>
                ) : (
                    <div>
                        {meals.map((meal, index) => (
                            <div key={String(meal?.id ?? index)}>
                                {index > 0 ? <div className="border-t-2 border-gray-200" /> : null}
                                <ProtocolMealRow
                                    meal={meal}
                                    compact
                                    onViewDetails={
                                        typeof onViewDetails === 'function'
                                            ? () => onViewDetails(meal)
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
