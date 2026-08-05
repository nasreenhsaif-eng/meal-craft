import MacroGrid from '../MacroGrid.jsx';
import PillButton from '../Atoms/Button/Button.jsx';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';

/**
 * Horizontal meal row for TBD Weekly Protocol day overview + options list.
 *
 * @param {object} props
 * @param {{ id?: string; title?: string; imageUrl?: string; macros?: { calories?: string|number; protein?: string|number; carbs?: string|number; fat?: string|number } }} props.meal
 * @param {boolean} [props.selected]
 * @param {boolean} [props.showCheckbox]
 * @param {boolean} [props.compact] Tighter macros / image for dual-column overview cards.
 * @param {() => void} [props.onSelect]
 * @param {() => void} [props.onViewDetails]
 * @param {string} [props.className]
 */
export default function ProtocolMealRow({
    meal,
    selected = false,
    showCheckbox = false,
    compact = false,
    onSelect,
    onViewDetails,
    className = '',
}) {
    const title = String(meal?.title ?? '').trim() || 'Meal';
    const imageUrl = typeof meal?.imageUrl === 'string' ? meal.imageUrl : '';
    const macros = meal?.macros ?? {};
    const imageSize = compact
        ? 'h-[72px] w-[72px] sm:h-[80px] sm:w-[80px]'
        : 'h-[88px] w-[88px] sm:h-[96px] sm:w-[96px]';

    return (
        <div
            className={[
                'flex w-full items-start',
                compact ? 'gap-2 px-2.5 py-2.5 sm:gap-2.5 sm:px-3' : 'gap-3 px-3 py-3 sm:gap-4 sm:px-4',
                selected ? 'bg-[#6E8C47]/5' : 'bg-transparent',
                className,
            ]
                .join(' ')
                .trim()}
        >
            {showCheckbox ? (
                <button
                    type="button"
                    className="mt-1 shrink-0"
                    onClick={onSelect}
                    aria-pressed={selected}
                    aria-label={selected ? `Deselect ${title}` : `Select ${title}`}
                >
                    <SquareCheckbox checked={selected} presentational />
                </button>
            ) : null}

            <button
                type="button"
                className="shrink-0 overflow-hidden rounded-[10px] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]"
                onClick={onSelect}
                aria-label={onSelect ? `Select ${title}` : title}
            >
                {imageUrl ? (
                    <img src={imageUrl} alt="" className={`${imageSize} object-cover`} />
                ) : (
                    <div
                        className={`flex items-center justify-center bg-[#E8EFE0] font-montserrat text-xs font-semibold text-[#5A6B44] ${imageSize}`}
                    >
                        Meal
                    </div>
                )}
            </button>

            <div className="min-w-0 flex-1">
                <p
                    className={[
                        'font-montserrat font-bold uppercase leading-snug tracking-tight text-[#262A22]',
                        compact ? 'line-clamp-2 text-[11px] sm:text-[12px]' : 'text-[13px] sm:text-sm',
                    ].join(' ')}
                >
                    {title}
                </p>
                <MacroGrid
                    compact
                    fluid
                    calories={macros.calories ?? 0}
                    protein={macros.protein ?? 0}
                    carbs={macros.carbs ?? 0}
                    fat={macros.fat ?? 0}
                    className={[
                        compact ? 'mt-1.5 max-w-full gap-x-0' : 'mt-2 max-w-full',
                    ].join(' ')}
                    ariaLabel={`${title} macros`}
                />
                {typeof onViewDetails === 'function' ? (
                    <PillButton
                        type="button"
                        label="VIEW DETAILS"
                        variant="secondary"
                        size="sm"
                        className="mt-2 !h-8 !min-h-8 px-3 text-[11px]"
                        onClick={(event) => {
                            event.stopPropagation();
                            onViewDetails();
                        }}
                    />
                ) : null}
            </div>
        </div>
    );
}
