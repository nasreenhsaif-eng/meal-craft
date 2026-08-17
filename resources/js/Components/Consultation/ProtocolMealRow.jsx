import MacroGrid from '../MacroGrid.jsx';
import PillButton from '../Atoms/Button/Button.jsx';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';

/**
 * Horizontal meal row for TBD Weekly Protocol day overview + options list.
 *
 * Two frames: image (full row height) | short details (title, fluid MacroGrid, centered VIEW DETAILS).
 *
 * @param {object} props
 * @param {{ id?: string; title?: string; imageUrl?: string; macros?: { calories?: string|number; protein?: string|number; carbs?: string|number; fat?: string|number } }} props.meal
 * @param {boolean} [props.selected]
 * @param {boolean} [props.showCheckbox]
 * @param {boolean} [props.compact] Slightly tighter padding for dual-column overview cards.
 * @param {() => void} [props.onSelect]
 * @param {() => void} [props.onViewDetails]
 * @param {() => void} [props.onEdit]
 * @param {string} [props.className]
 */
export default function ProtocolMealRow({
    meal,
    selected = false,
    showCheckbox = false,
    compact = false,
    onSelect,
    onViewDetails,
    onEdit,
    className = '',
}) {
    const title = String(meal?.title ?? '').trim() || 'Meal';
    const imageUrl = typeof meal?.imageUrl === 'string' ? meal.imageUrl : '';
    const macros = meal?.macros ?? {};
    const imageWidth = compact
        ? 'w-[112px] sm:w-[128px]'
        : 'w-[120px] sm:w-[140px]';

    return (
        <div
            className={[
                'flex w-full items-stretch',
                // Compact overview: shared soft fill so breakfast / mains / sides match.
                compact ? 'gap-2.5 bg-[#F8F9F6] p-2 sm:gap-3 sm:p-2.5' : 'gap-3 px-3 py-3 sm:gap-3 sm:px-4',
                // Selection wash only on the options list (checkbox rows), not overview cards.
                !compact && selected ? 'bg-[#6E8C47]/5' : '',
                !compact && !selected ? 'bg-transparent' : '',
                className,
            ]
                .join(' ')
                .trim()}
        >
            {showCheckbox ? (
                <button
                    type="button"
                    className="mt-1 inline-flex size-5 shrink-0 self-start items-center justify-center rounded-[4px] border-0 bg-transparent p-0 outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-1"
                    onClick={onSelect}
                    aria-pressed={selected}
                    aria-label={selected ? `Deselect ${title}` : `Select ${title}`}
                >
                    <SquareCheckbox checked={selected} presentational />
                </button>
            ) : null}

            <button
                type="button"
                className={[
                    'shrink-0 self-stretch overflow-hidden rounded-[10px] border border-gray-200 bg-[#E8EFE0]',
                    imageWidth,
                    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]',
                ].join(' ')}
                onClick={onSelect}
                aria-label={onSelect ? `Select ${title}` : title}
            >
                {imageUrl ? (
                    <img src={imageUrl} alt="" className="h-full w-full object-cover" />
                ) : (
                    <div className="flex h-full w-full items-center justify-center font-montserrat text-xs font-semibold text-[#5A6B44]">
                        Meal
                    </div>
                )}
            </button>

            <div
                className={[
                    'flex min-w-0 flex-1 flex-col rounded-[10px] border border-gray-200 bg-white',
                    compact ? 'px-2.5 py-2 sm:px-3 sm:py-2.5' : 'px-3 py-2.5 sm:px-3.5 sm:py-3',
                ].join(' ')}
            >
                <p
                    className={[
                        'font-montserrat font-bold uppercase leading-snug tracking-tight text-[#262A22]',
                        compact ? 'line-clamp-2 text-[11px] sm:text-[12px]' : 'line-clamp-2 text-[13px] sm:text-sm',
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
                    className="mt-1.5 w-full max-w-full"
                    ariaLabel={`${title} macros`}
                />
                {typeof onViewDetails === 'function' || typeof onEdit === 'function' ? (
                    <div className="mt-2 flex flex-wrap justify-center gap-2">
                        {typeof onViewDetails === 'function' ? (
                            <PillButton
                                type="button"
                                label="VIEW DETAILS"
                                variant="secondary"
                                size="sm"
                                className="!h-8 !min-h-8 px-3 text-[11px]"
                                onClick={(event) => {
                                    event.stopPropagation();
                                    onViewDetails();
                                }}
                            />
                        ) : null}
                        {typeof onEdit === 'function' ? (
                            <PillButton
                                type="button"
                                label="EDIT"
                                variant="ghost"
                                size="sm"
                                className="!h-8 !min-h-8 px-3 text-[11px]"
                                onClick={(event) => {
                                    event.stopPropagation();
                                    onEdit();
                                }}
                            />
                        ) : null}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
