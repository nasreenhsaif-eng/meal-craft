import { useMemo, useState } from 'react';
import Button from './Atoms/Button.jsx';
import MacroGrid from './MacroGrid.jsx';
import RoundIconButton from './Atoms/RoundIconButton.jsx';
import CategoryBadge from './MealSystem/CategoryBadges.jsx';
import TimeBadge from './MealSystem/TimeBadge.jsx';
import SafetyAlerts from './MealSystem/SafetyAlerts.jsx';
import { DietaryTag } from './MealSystem/DietaryTags.jsx';
import PreferenceTags from './MealSystem/PreferenceTags.jsx';
import ProtocolTags from './MealSystem/ProtocolTags.jsx';
import SquareCheckbox from './Atoms/SquareCheckbox.jsx';
import NutrientBadge from './Atoms/MealSystem/NutrientBadge.jsx';
import { IconDelete, IconEdit } from './Atoms/Icons.jsx';
import MealCraftLogo from './Atoms/Logo/MealCraftLogo.jsx';

/**
 * @param {object} props
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
 */
export default function MealCard({
    variant = 'client',
    isAdmin: isAdminProp,
    showActions,
    title,
    imageUrl,
    imageAlt = '',
    tags = [],
    allergyTags = [],
    dislikeTags = [],
    category,
    prepMinutes,
    macros,
    adminControls,
    selected = false,
    onToggleSelected,
    onEdit,
    onDelete,
    primaryActionLabel,
    onPrimaryAction,
    onViewDetails,
    protocolTags = [],
    safetySlot,
    actionSlot,
    nutrientHighlights = [],
    className = '',
}) {
    const structuralLabels = useMemo(
        () => new Set(['meal', 'dessert', 'breakfast', 'soup', 'side salad', 'sideSalad']),
        [],
    );
    const redWarningLabels = useMemo(() => new Set(['contains nuts', 'contains dairy', 'shellfish', 'contains gluten']), []);
    const greenDietaryLabels = useMemo(
        () =>
            new Set([
                'gluten-free',
                'gluten free',
                'vegan',
                'vegetarian',
                'nut-free',
                'nut free',
                'high protein',
                'low carbs',
                'low carb',
                'keto',
                'ketogenic',
            ]),
        [],
    );

    const normalized = (s) => String(s ?? '').trim();
    const normLower = (s) => normalized(s).toLowerCase();

    const inferredCategory =
        category ??
        (() => {
            const hit = tags.find((t) => structuralLabels.has(normLower(t.label)));
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

    const derivedSafetyAllergyTags = tags
        .map((t) => normalized(t.label))
        .filter((l) => redWarningLabels.has(l.toLowerCase()));

    const dietaryLabels = tags
        .map((t) => normalized(t.label))
        .filter((l) => l.length > 0)
        .filter((l) => !structuralLabels.has(l.toLowerCase()))
        .filter((l) => greenDietaryLabels.has(l.toLowerCase()));

    const protocolLabels = protocolTags.map((t) => normalized(t)).filter((t) => t.length > 0);
    const preferenceLabels = dislikeTags.map((t) => normalized(t)).filter((t) => t.length > 0);

    const hasSafety =
        Boolean(safetySlot) ||
        allergyTags.length > 0 ||
        dislikeTags.length > 0 ||
        derivedSafetyAllergyTags.length > 0;

    const isAdmin = Boolean(isAdminProp) || variant === 'admin';
    const showAdminControls = Boolean(showActions) || (isAdmin && adminControls);
    const isCraftSelection = variant === 'craft-selection';

    const computedPrimaryLabel = primaryActionLabel ?? 'View details';
    const [mediaFailed, setMediaFailed] = useState(false);
    const showImage = Boolean(imageUrl) && !mediaFailed;

    const cardBorderClass = isCraftSelection ? 'border-0' : 'border border-gray-100';
    const cardShadowClass = isCraftSelection ? (selected ? 'shadow-none' : 'shadow-md') : 'shadow-sm';
    const radiantBorderClass =
        isCraftSelection && selected ? 'bg-gradient-to-br from-[#B8D49F] to-[#6E8C47] p-[2px]' : 'bg-transparent p-0';

    return (
        <article
            className={`relative ${isCraftSelection ? 'w-[270px] h-[380px] sm:w-[310px] sm:h-[410px]' : 'w-[310px]'} ${radiantBorderClass} ${isCraftSelection ? 'rounded-[12px]' : 'rounded-[20px]'} font-montserrat ${className}`.trim()}
            style={
                isCraftSelection && selected
                    ? {
                          filter: 'drop-shadow(0 0 4px rgba(184, 212, 159, 0.85))',
                      }
                    : undefined
            }
        >
            <div
                className={`relative flex h-full flex-col overflow-hidden ${
                    isCraftSelection ? 'rounded-[10px]' : 'rounded-[20px]'
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

                <div className={`relative w-full overflow-hidden ${isCraftSelection ? 'rounded-t-[10px]' : 'rounded-t-[20px]'} bg-[#F8F9F6]`}>
                <div className={`${isCraftSelection ? 'aspect-[3/4]' : 'aspect-[4/3]'} w-full`}>
                    {showImage ? (
                        <img
                            src={imageUrl}
                            alt={imageAlt || title}
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
                                <button
                                    type="button"
                                    className="inline-flex items-center rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44] focus-visible:ring-offset-2"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        onToggleSelected?.(!selected);
                                    }}
                                    aria-pressed={selected}
                                    aria-label={selected ? `Deselect ${title}` : `Select ${title}`}
                                >
                                    <SquareCheckbox checked={selected} presentational />
                                </button>
                                <RoundIconButton
                                    icon={<IconEdit />}
                                    label="Edit"
                                    intent="default"
                                    onClick={onEdit}
                                    className="!bg-white/80 backdrop-blur"
                                />
                                <RoundIconButton
                                    icon={<IconDelete />}
                                    label="Delete"
                                    intent="danger"
                                    onClick={onDelete}
                                    className="!bg-white/80 backdrop-blur"
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
                    className={`flex flex-1 flex-col ${
                        isCraftSelection ? 'relative px-3 pb-12 pt-2' : 'px-5 pb-6 pt-4'
                    } ${actionSlot && isCraftSelection ? 'gap-1 sm:gap-1.5' : 'gap-3'}`}
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
                        {title}
                    </h3>

                    {!isCraftSelection ? (
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            {typeof prepMinutes === 'number' ? <TimeBadge minutes={prepMinutes} className="shrink-0" /> : null}
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
                                        ...allergyTags.map((label) => ({
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

                {macros ? (
                    <footer className={isCraftSelection ? 'pt-0.5' : 'mt-auto pt-2'}>
                        <div className="flex justify-center">
                            <div className={`w-[246px] border-t border-gray-100 ${isCraftSelection ? 'pb-1.5' : 'pb-3'}`} />
                        </div>
                        <div className="flex justify-center">
                            <MacroGrid
                                calories={macros.calories}
                                protein={macros.protein}
                                carbs={macros.carbs}
                                fat={macros.fat}
                                compact={isCraftSelection}
                            />
                        </div>
                        {isCraftSelection ? (
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
                        ) : null}
                    </footer>
                ) : (
                    <div className="mt-auto" />
                )}

                    {actionSlot ? (
                        isCraftSelection ? (
                            <div className="absolute bottom-2 left-3 right-3">{actionSlot}</div>
                        ) : (
                            <div className="pt-4">{actionSlot}</div>
                        )
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
                </div>
            </div>
        </article>
    );
}

