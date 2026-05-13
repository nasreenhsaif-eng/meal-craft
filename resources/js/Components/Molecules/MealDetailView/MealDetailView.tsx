import { Fragment, type ReactElement } from 'react';
import { MealPlanTag } from '../../MealSystem/DietaryTags.jsx';
import SafetyAlerts from '../../MealSystem/SafetyAlerts.jsx';
import { CyclePhaseTag, type CyclePhase } from './CyclePhaseTag';

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
    description: string;
    cyclePhases: CyclePhase[];
    dietaryTags: string[];
    safetyAlerts: MealSafetyAlert[];
    nutritionalData: MealNutritionalData;
    ingredients: string[];
    instructions: string[];
};

export type MealDetailViewProps = {
    meal: MealDetailModel;
    className?: string;
};

function MealNutritionSummaryTable({ data }: { data: MealNutritionalData }): ReactElement | null {
    if (!data.sections?.length) {
        return null;
    }

    const valueColumnLabel = data.valueColumnLabel ?? 'Total (meal)';

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
    const { description, cyclePhases, dietaryTags, safetyAlerts, nutritionalData, ingredients, instructions } = meal;

    return (
        <div
            role="region"
            aria-label="Meal details"
            tabIndex={0}
            className={[
                'mx-auto max-w-3xl max-h-[min(90vh,calc(100dvh-2rem))] overflow-y-auto overscroll-y-contain rounded-[12px] outline-none',
                'focus:outline-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#5A6B44]/45 focus-visible:ring-offset-2 focus-visible:ring-offset-[#F8F9F6]',
                className,
            ]
                .filter(Boolean)
                .join(' ')}
        >
            <article className="space-y-10 rounded-[12px] border border-gray-200 bg-white p-8 shadow-sm md:p-10">
            <header className="space-y-6">
                <div className="flex flex-col gap-5">
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

                    {safetyAlerts?.length ? (
                        <div className="space-y-2">
                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                                Safety alerts
                            </p>
                            <SafetyAlerts alerts={safetyAlerts} />
                        </div>
                    ) : null}
                </div>
            </header>

            <section className="space-y-3" aria-labelledby="meal-detail-description">
                <h2 id="meal-detail-description" className="sr-only">
                    Description
                </h2>
                <p className="font-montserrat text-base font-medium leading-relaxed text-[#262A22] md:text-[17px]">{description}</p>
            </section>

            <section className="space-y-4" aria-labelledby="meal-detail-nutrition-heading">
                <div>
                    <h2
                        id="meal-detail-nutrition-heading"
                        className="font-montserrat text-lg font-bold tracking-tight text-[#262A22] md:text-[18px]"
                    >
                        Nutritional summary
                    </h2>
                    <p className="mt-1 font-montserrat text-sm font-medium text-[#555555]">Per serving totals</p>
                </div>
                <MealNutritionSummaryTable data={nutritionalData} />
            </section>

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
                    {instructions.map((step, idx) => (
                        <li key={idx} className="pl-2">
                            {step}
                        </li>
                    ))}
                </ol>
            </section>
            </article>
        </div>
    );
}
