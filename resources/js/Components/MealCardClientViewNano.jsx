import { useState } from 'react';
import Button from './Atoms/Button.jsx';
import MealCraftLogo from './Atoms/Logo/MealCraftLogo.jsx';

/**
 * **Consultation / `StackedDeckCarousel` deck card** — use `deck` (+ optional `ribbon`, `deckStackRole`).
 * Standalone “nano” preview uses `deck={false}` (fixed 240×320).
 *
 * Nano mobile-first client card (square photo).
 *
 * @param {object} props
 * @param {string} props.title
 * @param {string} [props.imageUrl]
 * @param {string} [props.imageAlt]
 * @param {{ calories: string|number, protein: string|number, carbs: string|number, fat: string|number }} [props.macros] Unused on-card (no macro grid); calories roll up in consultation sidebar.
 * @param {boolean} [props.selected]
 * @param {boolean} [props.disabled]
 * @param {() => void} [props.onToggleSelected]
 * @param {() => void} [props.onViewDetails]
 * @param {boolean} [props.deck] Smaller sequential layout for stacked deck carousels.
 * @param {'front'|'back'|undefined} [props.deckStackRole] Depth cue for carousel: front gets a subtle left-facing stack shadow.
 * @param {'eager'|'lazy'} [props.imageLoading] Hero/front slides should use eager; stack backs use lazy.
 * @param {boolean} [props.ribbon] Desktop Netflix ribbon: fill slide cell width (avoid `w-[90vw]` overflow/clipping).
 * @param {string} [props.className] Extra classes on the outer article.
 */
export default function MealCardClientViewNano({
    title,
    imageUrl,
    imageAlt = '',
    macros: _macros,
    selected = false,
    disabled = false,
    onToggleSelected,
    onViewDetails,
    deck = false,
    deckStackRole,
    ribbon = false,
    imageLoading = 'lazy',
    className = '',
}) {
    const [mediaFailed, setMediaFailed] = useState(false);
    const showImage = Boolean(imageUrl) && !mediaFailed;

    /** Crafted-for-YOU (deck): 2px linear gradient rim only — no outer drop shadow (integrated radiant border). */
    const radiantBorderClass = selected
        ? deck
            ? 'bg-gradient-to-br from-[#B8D49F] to-[#5A6B44] p-[2px]'
            : 'bg-gradient-to-br from-[#B8D49F] to-[#6E8C47] p-[2px]'
        : 'bg-transparent p-0';
    const outerGlow = selected && !deck ? { filter: 'drop-shadow(0 0 4px rgba(184, 212, 159, 0.85))' } : undefined;
    const deckSelectedInnerGlow = deck && selected ? { boxShadow: 'inset 0 0 8px rgba(184, 212, 159, 0.4)' } : undefined;
    const cardShadowClass =
        deck ? (selected ? 'shadow-none ring-0' : 'shadow-none') : selected ? 'shadow-none' : 'shadow-md';
    /** Left-weighted rim matches fanned deck reference; backs stay shadowless (depth from blur/stack). */
    const articleDeckStackShadow =
        deck && !selected && deckStackRole === 'front'
            ? 'shadow-[-14px_0_28px_-10px_rgba(38,42,34,0.16)]'
            : '';
    /** Separates blurred stack layers — heavy CSS blur merges identical assets into one mush without this cue. */
    const deckBackRimClass =
        deck && !selected && deckStackRole === 'back'
            ? 'ring-1 ring-white/90 shadow-[inset_-1px_0_0_0_rgba(38,42,34,0.06)]'
            : '';
    /** Inner panel uses overflow-hidden for photo corners — deck shadow lives on `<article>` so it is not clipped. */
    const articleShadowDeck =
        deck && !selected && deckStackRole == null ? 'shadow-md' : '';
    const selectedDeckRaise =
        deck && selected ? 'relative z-[1]' : '';

    const shell = deck
        ? ribbon
            ? 'h-full w-full min-h-0 min-w-0 max-w-full rounded-[12px] flex flex-col'
            : 'w-[90vw] max-w-[440px] rounded-[12px] flex flex-col'
        : 'w-[240px] h-[320px] rounded-[12px]';
    /** Inner face: 12px card − 2px gradient ring ⇒ 10px inner radius when selected. */
    const innerR = deck ? (selected ? 'rounded-[10px]' : 'rounded-[12px]') : 'rounded-[10px]';
    const photoR = deck ? '' : 'rounded-t-[10px]';
    const titleClass = deck
        ? 'min-h-0 text-[12px] leading-snug'
        : 'min-h-[32px] text-[14px]';
    const bodyPad = deck ? '' : 'gap-1 px-2 pb-10 pt-2';
    const btnRow = deck ? '!h-[34px] !min-h-[34px] !px-2 !text-[12px]' : '!h-[34px] !min-h-[34px] !px-3 !text-[12px]';

    const craftPrimaryAria =
        selected === true
            ? `${title} is selected for your craft. Tap to remove from this slot.`
            : `Craft the ${title} meal`;

    const photo172 = (
        <>
            {showImage ? (
                <img
                    src={imageUrl}
                    alt={imageAlt || title}
                    className="h-full w-full object-cover"
                    loading={imageLoading}
                    decoding="async"
                    onError={() => setMediaFailed(true)}
                />
            ) : (
                <div className="flex h-full w-full items-center justify-center bg-[#F8F9F6]">
                    <MealCraftLogo
                        variant="seal-sm"
                        width={deck ? 56 : 68}
                        className="opacity-70"
                        alt="MealCraft"
                        title="MealCraft"
                    />
                </div>
            )}
        </>
    );

    return (
        <article
            className={`relative font-montserrat ${shell} ${radiantBorderClass} ${articleShadowDeck} ${articleDeckStackShadow} ${deckBackRimClass} ${selectedDeckRaise} ${deck ? 'bg-[#FFFFFF]' : ''} ${className}`.trim()}
            style={outerGlow ?? undefined}
        >
            {deck ? (
                <div
                    className={`relative flex w-full min-h-0 flex-1 flex-col overflow-hidden bg-white ${innerR} ${cardShadowClass}`.trim()}
                    style={deckSelectedInnerGlow}
                >
                    {selected ? (
                        <div className="pointer-events-none absolute right-2.5 top-2.5 z-30 rounded-full bg-gradient-to-br from-[#B8D49F] to-[#6E8C47] p-[2px]">
                            <div className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#5A6B44] text-[10px] font-bold text-white shadow-sm">
                                ✓
                            </div>
                        </div>
                    ) : null}

                    {/* Full-bleed 1:1 image: full inner width — corners clipped by parent radius. */}
                    <div className="relative w-full shrink-0 bg-[#F8F9F6]">
                        <div className="aspect-square w-full max-w-none shrink-0">{photo172}</div>
                    </div>

                    <div className="relative flex flex-col gap-0 px-3 pb-3 pt-1.5">
                        <div className="min-h-[2.75rem] shrink-0">
                            <h3
                                className={`w-full text-center ${titleClass} mt-0 font-bold tracking-tight text-[#262A22]`}
                                style={{
                                    display: '-webkit-box',
                                    WebkitBoxOrient: 'vertical',
                                    overflow: 'hidden',
                                    WebkitLineClamp: 2,
                                }}
                            >
                                {title}
                            </h3>
                        </div>

                        <button
                            type="button"
                            aria-label={`View details for ${title}`}
                            className="mx-auto mt-2 mb-2 inline-flex items-center justify-center rounded-[12px] px-2 py-1 text-[9px] font-bold uppercase tracking-[0.16em] text-[#5A6B44] hover:bg-[#5A6B44]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                            onClick={(e) => {
                                e.stopPropagation();
                                onViewDetails?.();
                            }}
                        >
                            VIEW DETAILS
                        </button>

                        <div className="mt-1 w-full shrink-0 pb-0.5">
                            <Button
                                type="button"
                                variant={selected ? 'primary' : 'secondary'}
                                disabled={disabled}
                                label={selected ? 'SELECTED' : 'CRAFT THIS MEAL'}
                                aria-label={craftPrimaryAria}
                                className={`w-full justify-center rounded-[12px] ${btnRow}`}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onToggleSelected?.();
                                }}
                            />
                        </div>
                    </div>
                </div>
            ) : (
                <div
                    className={`relative flex h-full flex-col overflow-hidden bg-white ${innerR} ${cardShadowClass}`.trim()}
                >
                    {selected ? (
                        <div
                            className="pointer-events-none absolute right-2 top-2 z-30 rounded-full bg-gradient-to-br from-[#B8D49F] to-[#6E8C47] p-[2px]"
                            style={{ filter: 'drop-shadow(0 0 4px rgba(184, 212, 159, 0.9))' }}
                        >
                            <div className="inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#5A6B44] text-xs font-bold text-white shadow-sm">
                                ✓
                            </div>
                        </div>
                    ) : null}

                    <div className={`relative w-full shrink-0 overflow-hidden bg-[#F8F9F6] ${photoR}`}>
                        <div className="aspect-square w-full shrink-0">
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
                                    <MealCraftLogo
                                        variant="seal-sm"
                                        width={68}
                                        className="opacity-70"
                                        alt="MealCraft"
                                        title="MealCraft"
                                    />
                                </div>
                            )}
                        </div>
                    </div>

                    <div className={`relative flex flex-1 flex-col ${bodyPad}`}>
                        <h3
                            className={`w-full text-center ${titleClass} mt-0 font-bold tracking-tight text-[#262A22]`}
                            style={{
                                display: '-webkit-box',
                                WebkitBoxOrient: 'vertical',
                                overflow: 'hidden',
                                WebkitLineClamp: 2,
                            }}
                        >
                            {title}
                        </h3>

                        <button
                            type="button"
                            className="mx-auto mt-2 inline-flex items-center justify-center rounded-[12px] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-[#5A6B44] hover:bg-[#5A6B44]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                            onClick={(e) => {
                                e.stopPropagation();
                                onViewDetails?.();
                            }}
                        >
                            VIEW DETAILS
                        </button>

                        <div className="absolute bottom-2 left-2 right-2">
                            <Button
                                type="button"
                                variant={selected ? 'primary' : 'secondary'}
                                disabled={disabled}
                                label={selected ? 'SELECTED' : 'CRAFT THIS MEAL'}
                                className={`w-full justify-center rounded-[12px] ${btnRow}`}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    onToggleSelected?.();
                                }}
                            />
                        </div>
                    </div>
                </div>
            )}
        </article>
    );
}

