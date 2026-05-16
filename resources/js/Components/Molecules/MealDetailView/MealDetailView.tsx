import { Fragment, type ReactElement, useState } from 'react';
import { MealPlanTag } from '../../MealSystem/DietaryTags.jsx';
import SafetyAlerts from '../../MealSystem/SafetyAlerts.jsx';
import NutrientBadge from '../../Atoms/MealSystem/NutrientBadge.jsx';
import MealCraftLogo from '../../Atoms/Logo/MealCraftLogo.jsx';
import { CyclePhaseTag, type CyclePhase } from './CyclePhaseTag';
import { resolveMealImageUrl } from '../../../meal-library/resolveMealImageUrl.ts';
import { mealInstructionStepsForDisplay } from '../../../meal-library/mealInstructionsDisplay.ts';
import { G6PD_HIGHLIGHT_BADGE } from '../../../meal-library/mealSafetyAndSickle.ts';

export type { CyclePhase };
export { CyclePhaseTag };

export type MealNutritionRow = {
    label: string;
    value: string;
    valueClass?: string;
};

export type MealNutritionSection = {
    title: string;
    rows: MealNutritionRow[];
};

export type MealNutritionalData = {
    sections: MealNutritionSection[];
    valueColumnLabel?: string;
};

export type MealSafetyAlert = {
    label: string;
    variant?: 'allergy' | 'g6pd';
};

export type MealDetailModel = {
    shortDescription?: string;
    cyclePhases: CyclePhase[];
    dietaryTags: string[];
    hasG6pdTrigger?: boolean;
    safetyAlerts: MealSafetyAlert[];
    sickleCellHighlights?: string[];
    /** Overrides default "Per serving totals" under Nutritional summary headings. */
    nutritionSubheading?: string;
    /** Overrides default sickle-cell RDI helper line beside highlights. */
    sickleRdiFootnote?: string;
    nutritionalData: MealNutritionalData;
    ingredients: string[];
    instructions: string[] | string;
    imageUrl?: string | null;
    imageAlt?: string | null;
    /** @deprecated Use shortDescription */
    description?: string;
};

export type MealDetailViewProps = {
    meal: MealDetailModel;
    className?: string;
};

const G6PD_SAFETY_ALERT_BADGE = 'G6PD Safety Alert';

function MealNutritionSummaryTable({ data }: { data: MealNutritionalData }): ReactElement | null {
    if (!data.sections?.length) {
        return null;
    }

    const valueColumnLabel = data.valueColumnLabel ?? 'Per serving';

    return (
        <div
            role="region"
            aria-label="Nutritional breakdown"
            tabIndex={0}
            className="overflow-x-auto rounded-[12px] border border-gray-200 bg-white outline-none [-webkit-overflow-scrolling:touch] focus:outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#5A6B44]/35"
        >
            <table className="w-full min-w-[280px] border-collapse text-left text-sm">
                <thead>
                    <tr className="border-b border-gray-200 bg-[#F8F9F6]">
                        <th className="px-3 py-2.5 font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                            Nutrient
                        </th>
                        <th className="px-3 py-2.5 text-right font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                            {valueColumnLabel}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {data.sections.map((sec) => (
                        <Fragment key={sec.title}>
                            <tr className="bg-[#F8F9F6]">
                                <td
                                    colSpan={2}
                                    className="px-3 py-2 font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-[#5A6B44]"
                                >
                                    {sec.title}
                                </td>
                            </tr>
                            {sec.rows.map((r) => (
                                <tr key={`${sec.title}-${r.label}`} className="border-b border-gray-100 last:border-b-0">
                                    <td className="px-3 py-2.5 font-montserrat text-sm font-medium text-[#374151]">{r.label}</td>
                                    <td
                                        className={[
                                            'px-3 py-2.5 text-right font-montserrat text-sm font-bold tabular-nums text-[#1F2937]',
                                            r.valueClass ?? '',
                                        ].join(' ')}
                                    >
                                        {r.value}
                                    </td>
                                </tr>
                            ))}
                        </Fragment>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function MealDetailView({ meal, className = '' }: MealDetailViewProps): ReactElement {
    const [mediaFailed, setMediaFailed] = useState(false);
    const {
        shortDescription,
        cyclePhases,
        dietaryTags,
        hasG6pdTrigger = false,
        safetyAlerts,
        sickleCellHighlights = [],
        nutritionalData,
        ingredients,
        instructions,
        imageUrl,
        imageAlt,
        description,
        nutritionSubheading,
        sickleRdiFootnote,
    } = meal;

    const nutritionSubheadingText = nutritionSubheading ?? 'Per serving totals';
    const sickleRdiFootnoteText = sickleRdiFootnote ?? 'High Source: ≥20% of daily RDI per serving';
    const resolvedImageUrl = resolveMealImageUrl(imageUrl);
    const resolvedImageAlt = String(imageAlt ?? '').trim();
    const showImage = resolvedImageUrl !== '' && !mediaFailed;
    const instructionSteps = mealInstructionStepsForDisplay(instructions);
    const scBadges = sickleCellHighlights.filter((b) => b !== G6PD_HIGHLIGHT_BADGE);

    return (
        <div
            role="region"
            aria-label="Meal details"
            tabIndex={0}
            className={[
                'mx-auto max-w-5xl max-h-[min(90vh,calc(100dvh-2rem))] overflow-y-auto overscroll-y-contain rounded-[12px] outline-none',
                'focus:outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/45 focus-visible:ring-offset-2 focus-visible:ring-offset-[#F8F9F6]',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <div className="grid grid-cols-1 gap-0 lg:grid-cols-[13fr_7fr]">
                <article className="space-y-8 rounded-[12px] border border-gray-200 bg-white p-6 shadow-sm md:p-8 lg:rounded-r-none lg:border-r-0">
                    <div className="-mx-6 -mt-6 mb-6 aspect-[4/3] w-[calc(100%+3rem)] max-w-none overflow-hidden rounded-t-[12px] bg-[#F8F9F6] md:-mx-8 md:-mt-8 md:w-[calc(100%+4rem)]">
                        {showImage ? (
                            <img
                                src={resolvedImageUrl}
                                alt={resolvedImageAlt || 'Meal photo'}
                                className="h-full w-full object-cover"
                                loading="lazy"
                                decoding="async"
                                onError={() => setMediaFailed(true)}
                            />
                        ) : (
                            <div className="flex h-full min-h-[12rem] w-full items-center justify-center bg-[#F8F9F6]">
                                <MealCraftLogo
                                    variant="seal-sm"
                                    width={72}
                                    className="opacity-70"
                                    alt="No meal image"
                                    title="MealCraft"
                                />
                            </div>
                        )}
                    </div>

                    <div className="flex flex-col gap-4">
                        {dietaryTags?.length ? (
                            <div className="flex flex-wrap gap-2" role="list" aria-label="Dietary tags">
                                {dietaryTags.map((tag) => (
                                    <span key={tag} role="listitem" className="inline-flex">
                                        <MealPlanTag label={tag} />
                                    </span>
                                ))}
                            </div>
                        ) : null}

                        {cyclePhases?.length ? (
                            <div className="flex flex-wrap gap-2" role="list" aria-label="Cycle phases">
                                {cyclePhases.map((phase) => (
                                    <span key={phase} role="listitem" className="inline-flex">
                                        <CyclePhaseTag phase={phase} />
                                    </span>
                                ))}
                            </div>
                        ) : null}
                    </div>

                    <section className="space-y-4" aria-labelledby="meal-detail-ingredients-heading">
                        <h2
                            id="meal-detail-ingredients-heading"
                            className="font-montserrat text-lg font-bold tracking-tight text-[#262A22] md:text-[18px]"
                        >
                            Ingredients
                        </h2>
                        <ul className="space-y-3 font-montserrat text-sm font-medium leading-relaxed text-[#374151] md:text-[15px]">
                            {ingredients.map((line, idx) => (
                                <li key={`${idx}-${line}`} className="border-b border-gray-100 pb-3 last:border-b-0 last:pb-0">
                                    {line}
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section className="space-y-4" aria-labelledby="meal-detail-instructions-heading">
                        <h2
                            id="meal-detail-instructions-heading"
                            className="font-montserrat text-lg font-bold tracking-tight text-[#262A22] md:text-[18px]"
                        >
                            Instructions
                        </h2>
                        <ol className="list-decimal space-y-4 pl-5 font-montserrat text-sm font-medium leading-relaxed text-[#374151] marker:font-bold md:text-[15px] md:pl-6">
                            {instructionSteps.map((step, idx) => (
                                <li key={idx} className="whitespace-pre-line pl-2">
                                    {step}
                                </li>
                            ))}
                        </ol>
                    </section>

                    <section className="space-y-4 lg:hidden" aria-labelledby="meal-detail-nutrition-heading-mobile">
                        <div>
                            <h2
                                id="meal-detail-nutrition-heading-mobile"
                                className="font-montserrat text-lg font-bold tracking-tight text-[#262A22] md:text-[18px]"
                            >
                                Nutritional summary
                            </h2>
                            <p className="mt-1 font-montserrat text-sm font-medium text-[#555555]">{nutritionSubheadingText}</p>
                        </div>
                        <MealNutritionSummaryTable data={nutritionalData} />
                    </section>
                </article>

                <aside className="space-y-6 rounded-[12px] border border-gray-200 bg-[#F8F9F6] p-6 lg:rounded-l-none lg:border-l">
                    {hasG6pdTrigger ? (
                        <div className="rounded-[12px] border-2 border-[#B91C1C] bg-[#FEF2F2] p-4 shadow-sm">
                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#991B1B]">
                                G6PD safety
                            </p>
                            <div className="mt-3">
                                <NutrientBadge type={G6PD_SAFETY_ALERT_BADGE} />
                            </div>
                            <p className="mt-2 font-body text-sm text-[#7F1D1D]">
                                This meal contains ingredients flagged as unsafe for G6PD deficiency.
                            </p>
                        </div>
                    ) : null}

                    {safetyAlerts?.length ? (
                        <div className="space-y-2">
                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                Safety alerts
                            </p>
                            <SafetyAlerts alerts={safetyAlerts} />
                        </div>
                    ) : null}

                    <div className="space-y-3">
                        <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                            Sickle Cell Highlights
                        </p>
                        <p className="font-body text-xs text-[#6B7280]">{sickleRdiFootnoteText}</p>
                        <div className="flex flex-wrap gap-2">
                            {scBadges.length > 0 ? (
                                scBadges.map((badge) => <NutrientBadge key={badge} type={badge} />)
                            ) : (
                                <p className="font-body text-sm text-[#555555]">—</p>
                            )}
                        </div>
                    </div>

                    <section className="hidden space-y-4 lg:block" aria-labelledby="meal-detail-nutrition-heading">
                        <div>
                            <h2
                                id="meal-detail-nutrition-heading"
                                className="font-montserrat text-lg font-bold tracking-tight text-[#262A22] md:text-[18px]"
                            >
                                Nutritional summary
                            </h2>
                            <p className="mt-1 font-montserrat text-sm font-medium text-[#555555]">{nutritionSubheadingText}</p>
                        </div>
                        <MealNutritionSummaryTable data={nutritionalData} />
                    </section>
                </aside>
            </div>
        </div>
    );
}
