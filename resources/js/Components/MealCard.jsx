import { useState } from 'react';
import MacroGrid from './MacroGrid.jsx';
import Button from './Atoms/Button.jsx';
import RoundIconButton from './Atoms/Icons/RoundIconButton.jsx';
import SquareCheckbox from './Atoms/Icons/SquareCheckbox.jsx';
import { IconEdit } from './Atoms/SvgIcons.jsx';
import MealCraftLogo from './Atoms/Logo/MealCraftLogo.jsx';

/**
 * Same shell as {@link MealCardClientViewNano} `deck` (consultation card, non-ribbon).
 */
const DECK_SHELL =
    'mx-auto w-[270px] max-w-[min(270px,100%)] shrink-0 rounded-[12px] flex flex-col bg-[#FFFFFF] shadow-md font-montserrat';

/**
 * @param {{ macros?: { calories: unknown; protein: unknown; carbs: unknown; fat: unknown } | null }} props
 */
function MacroGridDeckSection({ macros }) {
    if (!macros) {
        return null;
    }

    return (
        <div className="mt-0 w-full min-w-0 shrink px-0">
            <MacroGrid
                calories={macros.calories}
                protein={macros.protein}
                carbs={macros.carbs}
                fat={macros.fat}
                compact
                fluid
                abbreviated={false}
                className="!w-full !max-w-full min-w-0"
            />
        </div>
    );
}

/**
 * Unified meal card — layout matches {@link MealCardClientViewNano} **deck (consultation)** card:
 * `270px` shell, `aspect-[4/3]` hero, deck `MacroGrid` (fluid), title + VIEW DETAILS + CRAFT THIS MEAL.
 *
 * @param {object} props
 * @param {object} [props.meal]
 * @param {boolean} [props.isAdmin]
 * @param {string} [props.variant]
 * @param {string} [props.title]
 * @param {string} [props.imageUrl]
 * @param {string} [props.photoUrl]
 * @param {string} [props.imageAlt]
 * @param {{ calories: unknown; protein: unknown; carbs: unknown; fat: unknown }} [props.macros]
 * @param {boolean} [props.adminControls]
 * @param {boolean} [props.showActions]
 * @param {boolean} [props.showAdminSelectionCheckbox]
 * @param {boolean} [props.selected]
 * @param {(next?: boolean) => void} [props.onToggleSelected]
 * @param {() => void} [props.onEdit]
 * @param {(meal: object) => void} [props.onViewDetails] Invoked with the resolved meal payload (spread `meal` prop plus card fields when `meal` is set).
 * @param {() => void} [props.onPrimaryAction]
 * @param {() => void} [props.onCraftThisMeal]
 * @param {boolean} [props.disabled]
 * @param {string} [props.className]
 */
export default function MealCard({
    meal,
    isAdmin: isAdminProp,
    variant = 'client',
    title,
    imageUrl,
    photoUrl,
    imageAlt = '',
    macros,
    adminControls = false,
    showActions = false,
    showAdminSelectionCheckbox = true,
    selected = false,
    onToggleSelected,
    onEdit,
    onViewDetails,
    onPrimaryAction,
    onCraftThisMeal,
    disabled = false,
    className = '',
}) {
    const [mediaFailed, setMediaFailed] = useState(false);

    const mealRecord = meal && typeof meal === 'object' ? meal : null;

    const resolvedTitle = String(title ?? mealRecord?.title ?? '').trim();
    if (!resolvedTitle) {
        return null;
    }

    const resolvedImageUrl = String(
        photoUrl ?? imageUrl ?? mealRecord?.photoUrl ?? mealRecord?.imageUrl ?? '',
    ).trim();
    const resolvedImageAlt = String(imageAlt ?? mealRecord?.imageAlt ?? '').trim();
    const resolvedMacros = macros ?? mealRecord?.nutritionalSummary ?? mealRecord?.macros ?? null;

    const isAdmin = Boolean(isAdminProp) || variant === 'admin';
    const showAdminChrome = isAdmin && (Boolean(showActions) || Boolean(adminControls));
    const showImage = Boolean(resolvedImageUrl) && !mediaFailed;

    const viewDetailsHandler = onViewDetails ?? onPrimaryAction;

    const emitViewDetails = () => {
        const payload =
            mealRecord !== null
                ? { ...mealRecord }
                : {
                      title: resolvedTitle,
                      imageUrl: resolvedImageUrl,
                      imageAlt: resolvedImageAlt,
                      macros: resolvedMacros,
                  };
        if (typeof viewDetailsHandler === 'function') {
            viewDetailsHandler(payload);
        }
    };

    const craftHandler = onCraftThisMeal ?? onToggleSelected;

    const craftPrimaryAria =
        selected === true
            ? `${resolvedTitle} is selected for your craft. Tap to remove from this slot.`
            : `Craft the ${resolvedTitle} meal`;

    /** Client-only: consultation deck selected rim (matches {@link MealCardClientViewNano}). */
    const radiantBorderClass =
        !isAdmin && selected ? 'bg-gradient-to-br from-[#B8D49F] to-[#5A6B44] p-[2px]' : 'bg-transparent p-0';
    const innerR = !isAdmin && selected ? 'rounded-[10px]' : 'rounded-[12px]';
    const deckSelectedInnerGlow =
        !isAdmin && selected ? { boxShadow: 'inset 0 0 8px rgba(184, 212, 159, 0.4)' } : undefined;

    const titleClass = 'min-h-0 text-[17px] leading-snug';
    const btnRow = '!h-[36px] !min-h-[36px] !px-3 !text-[12px]';

    return (
        <article className={`relative ${DECK_SHELL} ${radiantBorderClass} ${className}`.trim()}>
            <div
                className={`relative flex w-full min-h-0 flex-1 flex-col overflow-hidden bg-white ${innerR} shadow-none`.trim()}
                style={deckSelectedInnerGlow}
            >
                {!isAdmin && selected ? (
                    <div className="pointer-events-none absolute right-2.5 top-2.5 z-30 rounded-full bg-gradient-to-br from-[#B8D49F] to-[#6E8C47] p-[2px]">
                        <div className="inline-flex h-6 w-6 items-center justify-center rounded-full bg-[#5A6B44] text-[10px] font-bold text-white shadow-sm">
                            ✓
                        </div>
                    </div>
                ) : null}

                {showAdminChrome && showAdminSelectionCheckbox ? (
                    <div className="absolute left-3 top-3 z-30">
                        <button
                            type="button"
                            className="inline-flex items-center rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                            onClick={(e) => {
                                e.stopPropagation();
                                onToggleSelected?.(!selected);
                            }}
                            aria-pressed={selected}
                            aria-label={selected ? `Deselect ${resolvedTitle}` : `Select ${resolvedTitle}`}
                        >
                            <SquareCheckbox checked={selected} presentational />
                        </button>
                    </div>
                ) : null}

                {showAdminChrome ? (
                    <div className="absolute right-3 top-3 z-30">
                        <RoundIconButton
                            icon={<IconEdit />}
                            ariaLabel="Edit meal"
                            intent="default"
                            onClick={onEdit}
                            className="!h-9 !w-9 !min-h-0 !rounded-lg !border-0 !bg-transparent !shadow-none hover:!border-0 hover:!bg-black/[0.06] active:!scale-[95%]"
                        />
                    </div>
                ) : null}

                <div className="relative aspect-[4/3] w-full shrink-0 overflow-hidden bg-[#F8F9F6]">
                    {showImage ? (
                        <img
                            src={resolvedImageUrl}
                            alt={resolvedImageAlt || resolvedTitle}
                            className="absolute inset-0 h-full w-full object-cover"
                            loading="lazy"
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

                <div className="relative flex flex-col gap-0 px-3 pb-2 pt-0">
                    <div className="min-h-[2.5rem] shrink-0">
                        <h3
                            className={`mt-2 w-full text-center font-bold tracking-tight text-[#262A22] ${titleClass}`}
                            style={{
                                display: '-webkit-box',
                                WebkitBoxOrient: 'vertical',
                                overflow: 'hidden',
                                WebkitLineClamp: 2,
                            }}
                        >
                            {resolvedTitle}
                        </h3>
                    </div>

                    <MacroGridDeckSection macros={resolvedMacros} />

                    {!isAdmin ? (
                        <>
                            <button
                                type="button"
                                aria-label={`View details for ${resolvedTitle}`}
                                className={`mx-auto mb-1.5 inline-flex w-full max-w-full items-center justify-center rounded-[12px] px-2 py-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-[#5A6B44] hover:bg-[#5A6B44]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2 ${resolvedMacros ? 'mt-1' : 'mt-2'}`}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    emitViewDetails();
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
                                        craftHandler?.();
                                    }}
                                />
                            </div>
                        </>
                    ) : (
                        <div
                            className={`w-full shrink-0 pb-0.5 ${resolvedMacros ? 'mt-1' : 'mt-2'}`}
                        >
                            <Button
                                type="button"
                                variant="primary"
                                disabled={disabled}
                                label="View details"
                                className={`w-full justify-center rounded-[12px] ${btnRow}`}
                                onClick={(e) => {
                                    e.stopPropagation();
                                    emitViewDetails();
                                }}
                            />
                        </div>
                    )}
                </div>
            </div>
        </article>
    );
}
