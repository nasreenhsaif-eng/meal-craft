import { useState } from 'react';
import MacroGrid from './MacroGrid.jsx';
import Button from './Atoms/Button.jsx';
import RoundIconButton from './Atoms/Icons/RoundIconButton.jsx';
import { IconEdit } from './Atoms/SvgIcons.jsx';
import MealCraftLogo from './Atoms/Logo/MealCraftLogo.jsx';
import SelectionCheckBadge from './Atoms/Icons/SelectionCheckBadge.jsx';

/**
 * Single {@link MacroGrid} usage — deck vs fixed preview only change wrappers (no duplicate grid markup).
 *
 * @param {{ macros?: { calories: unknown; protein: unknown; carbs: unknown; fat: unknown }; variant: 'deck' | 'nano'; macroAbbreviated?: boolean }} props
 */
function MacroGridSection({ macros, variant, macroAbbreviated = false }) {
    if (!macros) {
        return null;
    }

    const grid = (
        <MacroGrid
            calories={macros.calories}
            protein={macros.protein}
            carbs={macros.carbs}
            fat={macros.fat}
            compact
            {...(variant === 'deck'
                ? {
                      fluid: true,
                      abbreviated: macroAbbreviated,
                      className: '!w-full !max-w-full min-w-0',
                  }
                : { narrow: true })}
        />
    );

    if (variant === 'deck') {
        return (
            <div className="mt-0 w-full min-w-0 shrink px-0">
                {grid}
            </div>
        );
    }

    return <div className="flex justify-center pt-0">{grid}</div>;
}

/**
 * **Consultation / `MealCard/StackedDeckCarousel.jsx` deck card** — use `deck` (+ optional `ribbon`, `deckStackRole`).
 * Standalone “nano” preview uses `deck={false}` (fixed 240×320).
 *
 * Nano mobile-first client card (square photo).
 *
 * @param {object} props
 * @param {string} props.title
 * @param {string} [props.imageUrl]
 * @param {string} [props.imageAlt]
 * @param {{ calories: string|number, protein: string|number, carbs: string|number, fat: string|number }} [props.macros] Between title and VIEW DETAILS (compact grid; deck uses fluid width).
 * @param {boolean} [props.selected]
 * @param {boolean} [props.assigned] Assigned-to-plan checkmark without selection chrome.
 * @param {boolean} [props.disabled]
 * @param {() => void} [props.onToggleSelected]
 * @param {() => void} [props.onViewDetails]
 * @param {() => void} [props.onEdit] Admin/plan editor — pencil overlay (matches Meal Library card).
 * @param {boolean} [props.deck] Smaller sequential layout for stacked deck carousels.
 * @param {'front'|'back'|undefined} [props.deckStackRole] Depth cue for carousel: front gets a subtle left-facing stack shadow.
 * @param {'eager'|'lazy'} [props.imageLoading] Hero/front slides should use eager; stack backs use lazy.
 * @param {boolean} [props.ribbon] Desktop Netflix ribbon: fill slide cell width.
 * @param {boolean} [props.alignActionsBottom] Static two-up row: align craft buttons on the same baseline.
 * @param {boolean} [props.macroAbbreviated] Narrow / two-up deck: CAL · P · C · F labels.
 * @param {string} [props.className] Extra classes on the outer article.
 * @param {boolean} [props.vibrantCraftWhenAtLimit] Deck only: when selection slots are full, keep cards/buttons full-opacity (no greyed deck).
 * @param {boolean} [props.hideCraftButton] Read-only decks: hide CRAFT THIS MEAL (detail view only).
 */
export default function MealCardClientViewNano({
    title,
    imageUrl,
    imageAlt = '',
    macros,
    selected = false,
    assigned = false,
    disabled = false,
    onToggleSelected,
    onViewDetails,
    onEdit,
    deck = false,
    deckStackRole,
    ribbon = false,
    alignActionsBottom = false,
    macroAbbreviated = false,
    imageLoading = 'lazy',
    className = '',
    vibrantCraftWhenAtLimit = false,
    hideCraftButton = false,
}) {
    const [mediaFailed, setMediaFailed] = useState(false);
    const showImage = Boolean(imageUrl) && !mediaFailed;

    /** Ribbon + static pair: stretch card body so craft buttons share one baseline. */
    const pinActionsBottom = deck && (ribbon || alignActionsBottom);

    const showCheckmark = assigned || selected;

    /** Selected: subtle dark ring only — no light-green gradient rim or glow (keeps check badge clean). */
    const selectedShellClass = selected ? 'ring-2 ring-[#5A6B44]/35 ring-offset-0' : '';
    const cardShadowClass =
        deck ? (selected ? 'shadow-none ring-0' : 'shadow-none') : selected ? 'shadow-none' : 'shadow-md';
    /** Mobile 3D stack only — fan depth cue (not used on flat desktop ribbon). */
    const articleDeckStackShadow =
        deck && !ribbon && !selected && deckStackRole === 'front'
            ? 'shadow-[-14px_0_28px_-10px_rgba(38,42,34,0.16)]'
            : '';
    /** Mobile stack back cards only. */
    const deckBackRimClass =
        deck && !ribbon && !selected && deckStackRole === 'back'
            ? 'ring-1 ring-white/90 shadow-[inset_-1px_0_0_0_rgba(38,42,34,0.06)]'
            : '';
    /** Front hero in mobile stack when role unset — ribbon excluded. */
    const articleShadowDeck =
        deck && !ribbon && !selected && deckStackRole == null ? 'shadow-md' : '';
    const selectedDeckRaise =
        deck && selected ? 'relative z-[1]' : '';

    const shell = deck
        ? ribbon
            ? 'h-full w-full min-h-0 min-w-0 max-w-full rounded-[12px] flex flex-col'
            : pinActionsBottom
              ? 'flex h-full w-full min-h-0 min-w-0 max-w-full flex-col rounded-[12px]'
              : 'mx-auto w-[280px] max-w-[min(280px,100%)] shrink-0 rounded-[12px] flex flex-col'
        : 'w-[240px] h-[320px] rounded-[12px]';
    /** Inner face radius — uniform; no gradient padding inset. */
    const innerR = deck ? 'rounded-[12px]' : 'rounded-[10px]';
    const photoR = deck ? '' : 'rounded-t-[10px]';
    const titleClass = deck ? 'min-h-0 text-[17px] leading-snug' : 'min-h-[32px] text-[14px]';
    const bodyPad = deck ? '' : 'gap-0.5 px-2 pb-10 pt-2';
    const btnRow = deck
        ? '!h-[36px] !min-h-[36px] !px-3 !text-[12px]'
        : '!h-[34px] !min-h-[34px] !px-3 !text-[12px]';

    const craftPrimaryAria =
        selected === true
            ? `${title} is selected for your craft. Tap to remove from this slot.`
            : `Craft the ${title} meal`;

    return (
        <article
            className={`relative font-montserrat ${shell} ${pinActionsBottom && deck ? 'h-full' : ''} ${selectedShellClass} ${articleShadowDeck} ${articleDeckStackShadow} ${deckBackRimClass} ${selectedDeckRaise} ${deck ? 'bg-[#FFFFFF]' : ''} ${className}`.trim()}
        >
            {deck ? (
                <div
                    className={`relative flex w-full min-h-0 flex-1 flex-col overflow-hidden bg-white ${innerR} ${cardShadowClass}`.trim()}
                >
                    {/* Landscape-forward hero — shorter than 2:3 portrait so the deck feels wider / less tall. */}
                    <div className="relative aspect-[4/3] w-full shrink-0 overflow-hidden bg-[#F8F9F6]">
                        {onEdit ? (
                            <div className="absolute left-3 top-3 z-30">
                                <RoundIconButton
                                    icon={<IconEdit />}
                                    ariaLabel={`Edit ${title}`}
                                    intent="default"
                                    onClick={() => {
                                        onEdit();
                                    }}
                                    className="!h-9 !w-9 !min-h-0 !rounded-lg !border-0 !bg-transparent !shadow-none hover:!border-0 hover:!bg-black/[0.06] active:!scale-[95%]"
                                />
                            </div>
                        ) : null}
                        {showCheckmark ? (
                            <div className="pointer-events-none absolute right-2.5 top-2.5 z-30">
                                <SelectionCheckBadge />
                            </div>
                        ) : null}
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
                                    width={56}
                                    className="opacity-70"
                                    alt="MealCraft"
                                    title="MealCraft"
                                />
                            </div>
                        )}
                    </div>

                    <div
                        className={`relative flex flex-col gap-0 px-3 pb-2 pt-0 ${pinActionsBottom ? 'min-h-0 flex-1' : ''}`.trim()}
                    >
                        <div
                            className={
                                pinActionsBottom
                                    ? 'flex h-[3rem] shrink-0 items-start justify-center'
                                    : 'min-h-[2.5rem] shrink-0'
                            }
                        >
                            <h3
                                className={`mt-2 w-full text-center font-bold tracking-tight text-[#262A22] ${titleClass}`}
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

                        <MacroGridSection macros={macros} variant="deck" macroAbbreviated={macroAbbreviated} />

                        <button
                            type="button"
                            aria-label={`View details for ${title}`}
                            className={`mx-auto mb-1.5 inline-flex w-full max-w-full items-center justify-center rounded-[12px] px-2 py-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-[#5A6B44] hover:bg-[#5A6B44]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2 ${macros ? 'mt-1' : 'mt-2'}`}
                            onClick={(e) => {
                                e.stopPropagation();
                                onViewDetails?.();
                            }}
                        >
                            VIEW DETAILS
                        </button>

                        {hideCraftButton ? null : (
                            <div
                                className={`w-full shrink-0 pb-0.5 ${pinActionsBottom ? 'mt-auto pt-1' : 'mt-1'}`.trim()}
                            >
                                <Button
                                    type="button"
                                    variant={selected ? 'primary' : 'secondary'}
                                    disabled={disabled}
                                    label={selected ? 'SELECTED' : 'CRAFT THIS MEAL'}
                                    aria-label={craftPrimaryAria}
                                    className={`w-full justify-center rounded-[12px] ${btnRow} ${
                                        disabled && vibrantCraftWhenAtLimit ? '!cursor-default !opacity-100' : ''
                                    }`.trim()}
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onToggleSelected?.();
                                    }}
                                />
                            </div>
                        )}
                    </div>
                </div>
            ) : (
                <div
                    className={`relative flex h-full flex-col overflow-hidden bg-white ${innerR} ${cardShadowClass}`.trim()}
                >
                    {showCheckmark ? (
                        <div className="pointer-events-none absolute right-2 top-2 z-30">
                            <SelectionCheckBadge size="md" />
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

                        <MacroGridSection macros={macros} variant="nano" />

                        <button
                            type="button"
                            className={`mx-auto inline-flex items-center justify-center rounded-[12px] px-2 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-[#5A6B44] hover:bg-[#5A6B44]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2 ${macros ? 'mt-1' : 'mt-2'}`}
                            onClick={(e) => {
                                e.stopPropagation();
                                onViewDetails?.();
                            }}
                        >
                            VIEW DETAILS
                        </button>

                        {hideCraftButton ? null : (
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
                        )}
                    </div>
                </div>
            )}
        </article>
    );
}

