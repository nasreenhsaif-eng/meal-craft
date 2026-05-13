import { useState } from 'react';
import Button from './Atoms/Button.jsx';
import MacroGrid from './MacroGrid.jsx';
import RoundIconButton from './Atoms/Icons/RoundIconButton.jsx';
import CategoryBadge from './MealSystem/CategoryBadges.jsx';
import TimeBadge from './MealSystem/TimeBadge.jsx';
import SafetyAlerts from './MealSystem/SafetyAlerts.jsx';
import { DietaryTag } from './MealSystem/DietaryTags.jsx';
import PreferenceTags from './MealSystem/PreferenceTags.jsx';
import ProtocolTags from './MealSystem/ProtocolTags.jsx';
import SquareCheckbox from './Atoms/Icons/SquareCheckbox.jsx';
import NutrientBadge from './Atoms/MealSystem/NutrientBadge.jsx';
import { IconDelete, IconEdit } from './Atoms/SvgIcons.jsx';
import MealCraftLogo from './Atoms/Logo/MealCraftLogo.jsx';
import { CyclePhaseTag } from './Molecules/MealDetailView/CyclePhaseTag.tsx';

const VALID_CYCLE_PHASES = new Set(['Menstrual', 'Follicular', 'Ovulatory', 'Luteal']);

const STRUCTURAL_LABELS = new Set(['meal', 'dessert', 'breakfast', 'soup', 'side salad', 'sideSalad']);
const RED_WARNING_LABELS = new Set(['contains nuts', 'contains dairy', 'shellfish', 'contains gluten']);
const GREEN_DIETARY_LABELS = new Set([
    'gluten-free',
    'gluten free',
    'vegan',
    'vegetarian',
    'dairy-free',
    'dairy free',
    'nut-free',
    'nut free',
    'high protein',
    'low carbs',
    'low carb',
    'keto',
    'ketogenic',
    'balanced',
    'hormone feast',
    'sickle cell anemia',
]);

/**
 * @param {unknown} tagsProp
 * @param {object | null} mealRecord
 * @returns {{ label: string; type?: string }[]}
 */
function resolveTags(tagsProp, mealRecord) {
    const explicit = Array.isArray(tagsProp) ? tagsProp : [];
    if (explicit.length > 0) {
        return explicit;
    }
    const fromDiet = Array.isArray(mealRecord?.dietaryTags)
        ? mealRecord.dietaryTags
              .filter((t) => t != null && String(t).trim() !== '')
              .map((t) => (typeof t === 'string' ? { label: t, type: 'dietary' } : t))
        : [];
    const fromExtra = Array.isArray(mealRecord?.tags) ? mealRecord.tags : [];
    return [...fromDiet, ...fromExtra];
}

/**
 * @param {unknown} allergyTagsProp
 * @param {object | null} mealRecord
 * @returns {string[]}
 */
function resolveAllergyTags(allergyTagsProp, mealRecord) {
    const explicit = Array.isArray(allergyTagsProp) ? allergyTagsProp : [];
    if (explicit.length > 0) {
        return explicit;
    }
    const alerts = mealRecord?.safetyAlerts;
    if (!Array.isArray(alerts)) {
        return [];
    }
    return alerts
        .map((a) => (typeof a === 'string' ? a : a?.label))
        .filter((x) => x != null && String(x).trim() !== '');
}

function MealCardEmptyState({ className = '' }) {
    return (
        <article
            role="status"
            aria-live="polite"
            className={`relative flex w-full min-h-[280px] max-w-sm flex-col justify-center rounded-[20px] border border-dashed border-gray-200 bg-white px-6 py-10 text-center font-montserrat shadow-sm ${className}`.trim()}
        >
            <p className="text-sm font-semibold text-[#262A22]">No meal to display</p>
            <p className="mt-2 text-xs font-medium leading-relaxed text-[#555555]">
                Pass a <span className="font-semibold text-[#374151]">title</span> or a{' '}
                <span className="font-semibold text-[#374151]">meal</span> object to render this card.
            </p>
        </article>
    );
}

function MealCardLoadingState({ className = '' }) {
    return (
        <article
            className={`relative flex w-full min-h-[320px] max-w-sm flex-col overflow-hidden rounded-[20px] border border-gray-100 bg-white font-montserrat shadow-sm ${className}`.trim()}
            aria-busy="true"
            aria-label="Loading meal"
        >
            <div className="aspect-[4/3] w-full animate-pulse bg-[#F0F1ED]" />
            <div className="flex flex-1 flex-col gap-3 px-5 pb-6 pt-4">
                <div className="h-5 w-full max-w-[220px] animate-pulse rounded-md bg-[#F0F1ED]" />
                <div className="h-4 w-24 animate-pulse rounded-md bg-[#F0F1ED]" />
                <div className="mt-2 flex flex-wrap gap-2">
                    <div className="h-[26px] w-20 animate-pulse rounded-full bg-[#F0F1ED]" />
                    <div className="h-[26px] w-24 animate-pulse rounded-full bg-[#F0F1ED]" />
                </div>
                <p className="mt-4 text-xs font-semibold uppercase tracking-[0.14em] text-[#9CA3AF]">Loading meal…</p>
            </div>
        </article>
    );
}

/**
 * @param {object} props
 * @param {object} [props.meal] — When set, fills title, tags, macros, safety, etc. unless overridden by top-level props.
 * @param {boolean} [props.isLoading] — Shows a minimal loading shell (Montserrat).
 * @param {string} [props.cyclePhase] — One of Menstrual | Follicular | Ovulatory | Luteal; shown when not craft-selection.
 * @param {string} props.title
 * @param {string} [props.imageUrl]
 * @param {string} [props.imageAlt]
 * @param {{ label: string; type?: 'dietary' | 'nutrient' | 'category' }[]} [props.tags]
 * @param {string[]} [props.allergyTags]
 * @param {string[]} [props.dislikeTags]
 * @param {import('react').ReactNode} [props.safetySlot]
 * @param {import('react').ReactNode} [props.actionSlot]
 * @param {string[]} [props.nutrientHighlights] — Smart Kitchen nutrient keys: B12, Iron, Magnesium, Zinc, Folate
 * @param {string} [props.className]
 * @param {boolean} [props.adminControls]
 * @param {boolean} [props.showAdminSelectionCheckbox] — With admin controls, show bulk-selection checkbox (default true). Use false for grid-only admin layouts.
 */
export default function MealCard({
    meal,
    isLoading = false,
    cyclePhase: cyclePhaseProp,
    variant = 'client',
    isAdmin: isAdminProp,
    showActions,
    title,
    imageUrl,
    imageAlt = '',
    tags,
    allergyTags,
    dislikeTags,
    category,
    prepMinutes,
    macros,
    adminControls,
    showAdminSelectionCheckbox = true,
    selected = false,
    onToggleSelected,
    onEdit,
    onDelete,
    primaryActionLabel,
    onPrimaryAction,
    onViewDetails,
    protocolTags,
    safetySlot,
    actionSlot,
    nutrientHighlights,
    className = '',
}) {
    const [mediaFailed, setMediaFailed] = useState(false);

    if (isLoading) {
        return <MealCardLoadingState className={className} />;
    }

    const mealRecord = meal && typeof meal === 'object' ? meal : null;

    const resolvedTitle = String(title ?? mealRecord?.title ?? '').trim();
    if (!resolvedTitle) {
        return <MealCardEmptyState className={className} />;
    }

    const resolvedImageUrl = imageUrl ?? mealRecord?.imageUrl ?? '';
    const resolvedImageAlt = imageAlt ?? mealRecord?.imageAlt ?? '';
    const resolvedCategory = category ?? mealRecord?.category;
    const resolvedPrep = prepMinutes ?? mealRecord?.prepMinutes;
    const resolvedMacros = macros ?? mealRecord?.nutritionalSummary ?? mealRecord?.macros ?? null;
    const resolvedTags = resolveTags(tags, mealRecord);
    const resolvedAllergyTags = resolveAllergyTags(allergyTags, mealRecord);
    const resolvedDislikeTags = (() => {
        const a = Array.isArray(dislikeTags) ? dislikeTags : [];
        if (a.length > 0) {
            return a;
        }
        return Array.isArray(mealRecord?.dislikeTags) ? mealRecord.dislikeTags : [];
    })();
    const resolvedProtocolTags = Array.isArray(protocolTags) ? protocolTags : [];
    const resolvedNutrientHighlights = Array.isArray(nutrientHighlights) ? nutrientHighlights : [];

    const rawCycle = cyclePhaseProp ?? mealRecord?.cyclePhase;
    const resolvedCyclePhase = typeof rawCycle === 'string' && VALID_CYCLE_PHASES.has(rawCycle) ? rawCycle : null;

    const normalized = (s) => String(s ?? '').trim();
    const normLower = (s) => normalized(s).toLowerCase();

    const inferredCategory =
        resolvedCategory ??
        (() => {
            const hit = resolvedTags.find((t) => STRUCTURAL_LABELS.has(normLower(t.label)));
            return hit ? hit.label : undefined;
        })();

    const toCategoryVariant = (label) => {
        const l = normLower(label);
        if (l === 'side salad' || l === 'sideSalad') {
            return 'sideSalad';
        }
        if (l === 'breakfast') {
            return 'breakfast';
        }
        if (l === 'soup') {
            return 'soup';
        }
        if (l === 'dessert') {
            return 'dessert';
        }
        return 'meal';
    };

    const derivedSafetyAllergyTags = resolvedTags
        .map((t) => normalized(t.label))
        .filter((l) => RED_WARNING_LABELS.has(l.toLowerCase()));

    const dietaryLabels = resolvedTags
        .map((t) => normalized(t.label))
        .filter((l) => l.length > 0)
        .filter((l) => !STRUCTURAL_LABELS.has(l.toLowerCase()))
        .filter((l) => GREEN_DIETARY_LABELS.has(l.toLowerCase()));

    const protocolLabels = resolvedProtocolTags.map((t) => normalized(t)).filter((t) => t.length > 0);
    const preferenceLabels = resolvedDislikeTags.map((t) => normalized(t)).filter((t) => t.length > 0);

    const hasSafety =
        Boolean(safetySlot) ||
        resolvedAllergyTags.length > 0 ||
        resolvedDislikeTags.length > 0 ||
        derivedSafetyAllergyTags.length > 0;

    const isAdmin = Boolean(isAdminProp) || variant === 'admin';
    const showAdminControls = Boolean(showActions) || (isAdmin && adminControls);
    const isCraftSelection = variant === 'craft-selection';

    const computedPrimaryLabel = primaryActionLabel ?? 'View details';
    const showImage = Boolean(resolvedImageUrl) && !mediaFailed;

    const cardBorderClass = isCraftSelection ? 'border-0' : 'border border-gray-100';
    const cardShadowClass = isCraftSelection ? (selected ? 'shadow-none' : 'shadow-md') : 'shadow-sm';
    const radiantBorderClass =
        isCraftSelection && selected ? 'bg-gradient-to-br from-[#B8D49F] to-[#6E8C47] p-[2px]' : 'bg-transparent p-0';

    return (
        <article
            className={`relative ${
                isCraftSelection
                    ? 'h-[380px] w-[270px] sm:h-[410px] sm:w-[310px]'
                    : 'flex h-full min-h-0 w-full flex-col'
            } ${radiantBorderClass} ${isCraftSelection ? 'rounded-[12px]' : 'rounded-[20px]'} font-montserrat ${className}`.trim()}
            style={
                isCraftSelection && selected
                    ? {
                          filter: 'drop-shadow(0 0 4px rgba(184, 212, 159, 0.85))',
                      }
                    : undefined
            }
        >
            <div
                className={`relative flex flex-col overflow-hidden ${
                    isCraftSelection ? 'h-full rounded-[10px]' : 'min-h-0 flex-1 rounded-[20px]'
                } bg-white ${cardBorderClass} ${cardShadowClass}`.trim()}
            >
                {isCraftSelection && selected ? (
                    <div
                        className="pointer-events-none absolute right-3 top-3 z-30 rounded-full bg-gradient-to-br from-[#B8D49F] to-[#6E8C47] p-[2px]"
                        style={{ filter: 'drop-shadow(0 0 4px rgba(184, 212, 159, 0.9))' }}
                    >
                        <div className="inline-flex h-9 w-9 items-center justify-center rounded-full bg-[#5A6B44] text-white shadow-sm">
                            ✓
                        </div>
                    </div>
                ) : null}

                <div className={`relative w-full shrink-0 overflow-hidden ${isCraftSelection ? 'rounded-t-[10px]' : 'rounded-t-[20px]'} bg-[#F8F9F6]`}>
                <div className={`${isCraftSelection ? 'aspect-[3/4]' : 'aspect-[4/3]'} w-full`}>
                    {showImage ? (
                        <img
                            src={resolvedImageUrl}
                            alt={resolvedImageAlt || resolvedTitle}
                            className="absolute inset-0 h-full w-full object-cover"
                            loading="lazy"
                            onError={() => setMediaFailed(true)}
                        />
                    ) : (
                        <div className="absolute inset-0 flex items-center justify-center bg-[#F8F9F6]">
                            <MealCraftLogo
                                variant="seal-sm"
                                width={84}
                                className="opacity-70"
                                alt="MealCraft"
                                title="MealCraft"
                            />
                        </div>
                    )}
                </div>

                {showAdminControls ? (
                    <>
                        <div className="pointer-events-none absolute inset-x-0 top-0 z-20 h-16 bg-gradient-to-b from-white/85 via-white/35 to-transparent backdrop-blur-sm" />

                        <div className="absolute inset-x-0 top-0 z-30 flex items-start justify-between gap-3 p-4">
                            <div className="pointer-events-auto flex items-center gap-2">
                                {showAdminSelectionCheckbox ? (
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
                                ) : null}
                                <RoundIconButton
                                    icon={<IconEdit />}
                                    ariaLabel="Edit meal"
                                    intent="default"
                                    onClick={onEdit}
                                    className="!border-white/45 !bg-white/80 backdrop-blur"
                                />
                                <RoundIconButton
                                    icon={<IconDelete />}
                                    ariaLabel="Delete meal"
                                    intent="danger"
                                    onClick={onDelete}
                                    className="!border-white/45 !bg-white/80 backdrop-blur"
                                />
                            </div>

                            <div className="pointer-events-auto">
                                {inferredCategory ? (
                                    <CategoryBadge variant={toCategoryVariant(inferredCategory)} label={inferredCategory} />
                                ) : null}
                            </div>
                        </div>
                    </>
                ) : (
                    <div className="pointer-events-auto absolute top-0 right-0 z-10 p-4">
                        {!isCraftSelection && inferredCategory ? (
                            <CategoryBadge
                                variant={toCategoryVariant(inferredCategory)}
                                label={inferredCategory}
                                className="bg-white/80 backdrop-blur border border-white/40"
                            />
                        ) : null}
                    </div>
                )}
                </div>

                <div
                    className={`flex flex-1 flex-col min-h-0 ${
                        isCraftSelection ? 'relative px-3 pb-12 pt-2' : 'px-5 pb-6 pt-4'
                    } ${actionSlot && isCraftSelection ? 'gap-1 sm:gap-1.5' : isCraftSelection ? 'gap-3' : ''}`}
                >
                    <header className={isCraftSelection ? 'space-y-0' : 'space-y-3'}>
                    <h3
                        className={`m-0 mb-1 font-bold leading-tight tracking-tight text-[#262A22] ${
                            isCraftSelection ? 'mt-1 min-h-[32px] text-[15px] leading-tight sm:text-[16px] sm:min-h-[36px]' : 'text-base'
                        }`.trim()}
                        style={
                            isCraftSelection
                                ? {
                                      display: '-webkit-box',
                                      WebkitLineClamp: 2,
                                      WebkitBoxOrient: 'vertical',
                                      overflow: 'hidden',
                                  }
                                : undefined
                        }
                    >
                        {resolvedTitle}
                    </h3>

                    {!isCraftSelection ? (
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            {typeof resolvedPrep === 'number' ? <TimeBadge minutes={resolvedPrep} className="shrink-0" /> : null}
                        </div>
                    ) : (
                        <div className="h-0.5" />
                    )}

                    {!isCraftSelection && dietaryLabels.length > 0 ? (
                        <div className="flex flex-wrap gap-2" role="group" aria-label="Dietary and nutrient badges">
                            {dietaryLabels.map((t) => (
                                <DietaryTag key={`diet-${t}`} label={t} />
                            ))}
                        </div>
                    ) : null}

                    {!isCraftSelection && resolvedCyclePhase ? (
                        <div className="flex flex-wrap gap-2" role="group" aria-label="Cycle phase">
                            <CyclePhaseTag phase={resolvedCyclePhase} />
                        </div>
                    ) : null}

                    {!isCraftSelection && resolvedNutrientHighlights.length > 0 ? (
                        <div className="flex flex-wrap gap-2" role="group" aria-label="Nutrient highlights">
                            {resolvedNutrientHighlights.map((t) => (
                                <NutrientBadge key={`nh-${t}`} type={t} />
                            ))}
                        </div>
                    ) : null}

                    {!isCraftSelection && hasSafety ? (
                        <div className="space-y-2">
                            {safetySlot ? (
                                <div>{safetySlot}</div>
                            ) : (
                                <SafetyAlerts
                                    alerts={[
                                        ...derivedSafetyAllergyTags.map((label) => ({
                                            label: label.toUpperCase().includes('G6PD') ? 'G6PD' : label,
                                            variant: label.toLowerCase().includes('g6pd') ? 'g6pd' : 'allergy',
                                        })),
                                        ...resolvedAllergyTags.map((label) => ({
                                            label,
                                            variant: label.toLowerCase().includes('g6pd') ? 'g6pd' : 'allergy',
                                        })),
                                    ]}
                                />
                            )}
                        </div>
                    ) : null}

                    {!isCraftSelection ? (
                        <>
                            <ProtocolTags tags={protocolLabels} />
                            <PreferenceTags tags={preferenceLabels} />
                        </>
                    ) : null}
                </header>

                {isCraftSelection ? (
                    <>
                        {resolvedMacros ? (
                            <footer className="pt-0.5">
                                <div className="flex justify-center">
                                    <div className="w-[246px] border-t border-gray-100 pb-1.5" />
                                </div>
                                <div className="flex justify-center">
                                    <MacroGrid
                                        calories={resolvedMacros.calories}
                                        protein={resolvedMacros.protein}
                                        carbs={resolvedMacros.carbs}
                                        fat={resolvedMacros.fat}
                                        compact={isCraftSelection}
                                    />
                                </div>
                                <div className="pt-0.5">
                                    <button
                                        type="button"
                                        className="mx-auto inline-flex items-center justify-center rounded-[12px] px-2 py-1.5 text-center text-[10px] font-bold uppercase tracking-[0.18em] text-[#5A6B44] transition-colors hover:bg-[#5A6B44]/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                                        onClick={(e) => {
                                            e.stopPropagation();
                                            onViewDetails?.();
                                        }}
                                    >
                                        VIEW DETAILS
                                    </button>
                                </div>
                            </footer>
                        ) : (
                            <div className="mt-auto" />
                        )}
                        {actionSlot ? (
                            <div className="absolute bottom-2 left-3 right-3">{actionSlot}</div>
                        ) : (
                            <div className="pt-4">
                                <Button
                                    type="button"
                                    variant="primary"
                                    label={computedPrimaryLabel}
                                    className="w-full justify-center"
                                    onClick={onPrimaryAction}
                                />
                            </div>
                        )}
                    </>
                ) : (
                    <div className="mt-auto flex w-full flex-col gap-3 pt-2">
                        {resolvedMacros ? (
                            <>
                                <div className="flex justify-center">
                                    <div className="w-[246px] border-t border-gray-100 pb-3" />
                                </div>
                                <div className="flex justify-center">
                                    <MacroGrid
                                        calories={resolvedMacros.calories}
                                        protein={resolvedMacros.protein}
                                        carbs={resolvedMacros.carbs}
                                        fat={resolvedMacros.fat}
                                        compact={false}
                                    />
                                </div>
                            </>
                        ) : null}
                        {actionSlot ? (
                            <div className="w-full">{actionSlot}</div>
                        ) : (
                            <div className="w-full">
                                <Button
                                    type="button"
                                    variant="primary"
                                    label={computedPrimaryLabel}
                                    className="w-full justify-center"
                                    onClick={onPrimaryAction}
                                />
                            </div>
                        )}
                    </div>
                )}
                </div>
            </div>
        </article>
    );
}

