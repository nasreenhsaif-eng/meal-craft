import { useState } from 'react';
import MacroGrid from './MacroGrid.jsx';
import Button from './Atoms/Button.jsx';
import MealCraftLogo from './Atoms/Logo/MealCraftLogo.jsx';

/**
 * MealCardClientView — compact fixed-size card for Storybook / standalone previews.
 *
 * **Consultation stacked decks** use `MealCardClientViewNano.jsx` (`deck` + `ribbon`) — that file holds the carousel card layout used with `MealCard/StackedDeckCarousel.jsx`.
 *
 * @param {object} props
 * @param {string} props.title
 * @param {string} [props.imageUrl]
 * @param {string} [props.imageAlt]
 * @param {{ calories: string|number, protein: string|number, carbs: string|number, fat: string|number }} [props.macros] Macro grid between title and VIEW DETAILS when present.
 * @param {boolean} [props.selected]
 * @param {boolean} [props.disabled]
 * @param {() => void} [props.onToggleSelected]
 * @param {() => void} [props.onViewDetails]
 * @param {'eager'|'lazy'} [props.imageLoading]
 * @param {boolean} [props.ribbon] Desktop carousel ribbon: fill slide width (`StackedDeckCarousel` ≥768px).
 * @param {string} [props.className]
 */
export default function MealCardClientView({
    title,
    imageUrl,
    imageAlt = '',
    macros,
    selected = false,
    disabled = false,
    onToggleSelected,
    onViewDetails,
    imageLoading = 'lazy',
    ribbon = false,
    className = '',
}) {
    const [mediaFailed, setMediaFailed] = useState(false);
    const showImage = Boolean(imageUrl) && !mediaFailed;

    const radiantBorderClass = selected ? 'bg-gradient-to-br from-[#B8D49F] to-[#6E8C47] p-px' : 'bg-transparent p-0';
    const outerGlow = selected ? { filter: 'drop-shadow(0 0 4px rgba(184, 212, 159, 0.85))' } : undefined;

    const svgDeckShadow = selected
        ? undefined
        : {
              boxShadow: '0 3px 4px rgba(0,0,0,0.10), 0 8px 11px rgba(0,0,0,0.10)',
          };

    const sizeShell = ribbon
        ? 'relative h-full min-h-0 w-full min-w-0 max-w-full'
        : 'relative h-[285px] w-[203px] sm:h-[308px] sm:w-[233px]';

    return (
        <article
            className={`${sizeShell} flex min-h-0 flex-col ${radiantBorderClass} rounded-[10px] font-montserrat ${className}`.trim()}
            style={outerGlow}
        >
            <div
                className="relative flex min-h-0 flex-1 flex-col overflow-hidden rounded-[8px] border border-black/5 bg-white"
                style={svgDeckShadow}
            >
                {selected ? (
                    <div
                        className="pointer-events-none absolute right-3 top-3 z-30 rounded-full bg-gradient-to-br from-[#B8D49F] to-[#6E8C47] p-px"
                        style={{ filter: 'drop-shadow(0 0 4px rgba(184, 212, 159, 0.9))' }}
                    >
                        <div className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#5A6B44] text-white shadow-sm">
                            ✓
                        </div>
                    </div>
                ) : null}

                <div className="relative w-full overflow-hidden rounded-t-[8px] bg-[#F8F9F6] p-0">
                    <div className="aspect-square w-full">
                        {showImage ? (
                            <img
                                src={imageUrl}
                                alt={imageAlt || title}
                                className="absolute inset-0 h-full w-full object-cover"
                                loading={imageLoading}
                                decoding="async"
                                onError={() => setMediaFailed(true)}
                            />
                        ) : (
                            <div className="absolute inset-0 flex items-center justify-center bg-[#F8F9F6]">
                                <MealCraftLogo variant="seal-sm" width={84} className="opacity-70" alt="MealCraft" title="MealCraft" />
                            </div>
                        )}
                    </div>
                </div>

                <div className="relative flex flex-1 flex-col px-3 pb-3 pt-2">
                    <div className="flex min-h-[2.75rem] flex-1 items-start justify-center sm:min-h-[3rem]">
                        <h3
                            className="mt-0 w-full text-center text-[15px] font-bold leading-tight tracking-tight text-[#262A22] sm:text-[16px]"
                            style={{
                                display: '-webkit-box',
                                WebkitLineClamp: 2,
                                WebkitBoxOrient: 'vertical',
                                overflow: 'hidden',
                            }}
                        >
                            {title}
                        </h3>
                    </div>

                    {macros ? (
                        <div className="mt-1 flex w-full min-w-0 justify-center overflow-hidden">
                            <MacroGrid
                                calories={macros.calories}
                                protein={macros.protein}
                                carbs={macros.carbs}
                                fat={macros.fat}
                                compact
                                narrow
                            />
                        </div>
                    ) : null}

                    <div className="mt-2 grid gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            label="VIEW DETAILS"
                            aria-label={`View details for ${title}`}
                            className="w-full justify-center !text-[12px]"
                            onClick={(e) => {
                                e.stopPropagation();
                                onViewDetails?.();
                            }}
                        />
                        <Button
                            type="button"
                            variant={selected ? 'primary' : 'secondary'}
                            disabled={disabled}
                            label={selected ? 'SELECTED' : 'CRAFT THIS MEAL'}
                            aria-label={
                                selected
                                    ? `${title} is selected for your craft. Tap to remove.`
                                    : `Craft the ${title} meal`
                            }
                            className="w-full justify-center rounded-[12px] !h-[36px] !min-h-[36px] !px-4 !text-[12px]"
                            onClick={(e) => {
                                e.stopPropagation();
                                onToggleSelected?.();
                            }}
                        />
                    </div>
                </div>
            </div>
        </article>
    );
}
