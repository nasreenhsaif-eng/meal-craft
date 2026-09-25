import Button from '../Atoms/Button/Button.jsx';
import MealCraftLogo from '../Atoms/Logo/MealCraftLogo.jsx';
import MealCardClientViewNano from '../MealCardClientViewNano.jsx';

/**
 * Day-overview slot card: olive border, SEE OTHER OPTIONS, selected meal cards.
 * Cards are fixed 240px everywhere.
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

    const cardsLayout =
        meals.length <= 1
            ? 'flex w-full justify-center gap-3 overflow-x-visible px-0'
            : [
                  'flex w-full gap-3 overflow-x-auto overscroll-x-contain pb-1 pr-4',
                  'snap-x snap-mandatory [-webkit-overflow-scrolling:touch] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden',
                  'sm:grid sm:justify-center sm:justify-items-center sm:gap-3 sm:overflow-visible sm:px-0 sm:pb-0 sm:snap-none',
                  'sm:grid-cols-[repeat(2,240px)]',
              ].join(' ');

    return (
        <section
            className={['relative rounded-[12px] border border-[#5A6B44] bg-white', className]
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
                    <div
                        className="flex justify-center py-6"
                        role="status"
                        aria-live="polite"
                        aria-label="Loading meal options"
                    >
                        <MealCraftLogo variant="minimal-animated" width={88} alt="" />
                    </div>
                ) : (
                    <div className={cardsLayout}>
                        {meals.map((meal, index) => (
                            <div key={String(meal?.id ?? index)} className="shrink-0 snap-start">
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
