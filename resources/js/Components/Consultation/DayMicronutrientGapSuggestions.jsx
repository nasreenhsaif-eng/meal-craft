import { useMemo, useState } from 'react';
import Button from '../Atoms/Button.jsx';
import { aggregateDayMicronutrientRows } from '../../meal-library/aggregateDayNutritionalData.ts';
import {
    BIOAVAILABLE_SUPPLEMENT_GUIDE,
    buildDayMicronutrientGuidance,
    dayHasLiverMain,
    LIVER_K2_MEAL_OPTIONS,
} from '../../meal-library/micronutrientGapGuidance.ts';
import { isMicronutrientTierEnforced } from '../../meal-library/nutrientDailyRdi.ts';

/**
 * @param {object} props
 * @param {Partial<Record<string, Array<{ title?: string }>>>} props.categories
 * @param {number} [props.planTierCalories]
 * @param {() => void} [props.onEditMeals]
 */
export default function DayMicronutrientGapSuggestions({
    categories,
    planTierCalories = 0,
    onEditMeals,
}) {
    const micronutrientRows = useMemo(() => aggregateDayMicronutrientRows(categories), [categories]);

    const guidance = useMemo(
        () => buildDayMicronutrientGuidance(micronutrientRows, planTierCalories, categories),
        [categories, micronutrientRows, planTierCalories],
    );

    const tierEnforced = isMicronutrientTierEnforced(planTierCalories);
    const showSupplementGuide = tierEnforced || guidance.length > 0;

    const [supplementGuideOpen, setSupplementGuideOpen] = useState(true);
    const [selectedLiverMeal, setSelectedLiverMeal] = useState(
        () => 'Seared Beef Liver w Caramelized Onion, Spinach & Chimichurri',
    );

    const hasLiverOnDay = useMemo(() => dayHasLiverMain(categories), [categories]);

    if (planTierCalories <= 0) {
        return null;
    }

    return (
        <div className="mt-6 space-y-4">
            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#5A6B44]">
                Nutrient guidance
            </p>

            {guidance.length === 0 && tierEnforced ? (
                <div className="rounded-[12px] border border-gray-200 bg-[#F8F9F6] px-4 py-4 sm:px-5">
                    <h3 className="font-montserrat text-sm font-bold text-[#262A22]">
                        Keep K2, calcium &amp; vitamin D on your radar
                    </h3>
                    <p className="mt-2 font-body text-sm leading-relaxed text-[#374151]">
                        Dairy-free plans often run low on vitamin K2 and calcium. If any nutrient in the table
                        above drops below 98% RDI, swap in a liver main for K2, add plain Greek yogurt on the
                        side for calcium, and consider sensible sun plus a D3 + MK-7 supplement.
                    </p>
                    <ul className="mt-3 list-disc space-y-1.5 pl-5 font-body text-sm text-[#374151]">
                        {LIVER_K2_MEAL_OPTIONS.slice(0, 2).map((meal) => (
                            <li key={meal.title}>
                                <span className="font-medium">
                                    {meal.title.includes(' w ') ? meal.title.split(' w ')[0] : meal.title}
                                </span>
                                {' — '}
                                {meal.k2Note}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}

            {guidance.map((item) => (
                <div
                    key={item.id}
                    className="rounded-[12px] border border-amber-200 bg-amber-50/80 px-4 py-4 sm:px-5"
                >
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-amber-900">
                        {item.nutrient} · below {98}% RDI
                    </p>
                    <h3 className="mt-2 font-montserrat text-sm font-bold text-[#262A22]">{item.title}</h3>
                    <p className="mt-2 font-body text-sm leading-relaxed text-[#374151]">{item.body}</p>

                    {item.bullets?.length ? (
                        <ul className="mt-3 list-disc space-y-1.5 pl-5 font-body text-sm text-[#374151]">
                            {item.bullets.map((bullet) => (
                                <li key={bullet}>{bullet}</li>
                            ))}
                        </ul>
                    ) : null}

                    {item.liverMeals?.length ? (
                        <div className="mt-4 space-y-2">
                            <p className="font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                                {hasLiverOnDay ? 'Swap to a stronger liver main' : 'Choose a liver main'}
                            </p>
                            <fieldset className="space-y-2">
                                <legend className="sr-only">Liver meal options for vitamin K2</legend>
                                {item.liverMeals.map((meal) => {
                                    const selected = selectedLiverMeal === meal.title;
                                    const shortTitle = meal.title.includes(' w ')
                                        ? meal.title.split(' w ')[0]
                                        : meal.title;

                                    return (
                                        <label
                                            key={meal.title}
                                            className={[
                                                'flex cursor-pointer gap-3 rounded-[10px] border px-3 py-3 transition',
                                                selected
                                                    ? 'border-[#5A6B44] bg-white shadow-sm'
                                                    : 'border-amber-100 bg-white/60 hover:border-[#5A6B44]/40',
                                            ].join(' ')}
                                        >
                                            <input
                                                type="radio"
                                                name="liver-k2-meal"
                                                value={meal.title}
                                                checked={selected}
                                                onChange={() => setSelectedLiverMeal(meal.title)}
                                                className="mt-1 shrink-0 accent-[#5A6B44]"
                                            />
                                            <span className="min-w-0">
                                                <span className="block font-montserrat text-sm font-bold text-[#262A22]">
                                                    {shortTitle}
                                                </span>
                                                <span className="mt-0.5 block font-body text-xs text-[#555555]">
                                                    {meal.description}
                                                </span>
                                                <span className="mt-1 block font-body text-xs font-medium text-[#5A6B44]">
                                                    {meal.k2Note}
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </fieldset>
                            {selectedLiverMeal ? (
                                <p className="font-body text-xs text-[#555555]">
                                    Selected:{' '}
                                    <span className="font-medium text-[#262A22]">
                                        {selectedLiverMeal.includes(' w ')
                                            ? selectedLiverMeal.split(' w ')[0]
                                            : selectedLiverMeal}
                                    </span>
                                    . Use Edit main meals to swap one of your two mains for this dish.
                                </p>
                            ) : null}
                        </div>
                    ) : null}

                    {item.actions?.length ? (
                        <div className="mt-4 flex flex-wrap gap-2">
                            {item.actions.map((action) =>
                                action.type === 'edit_meals' && onEditMeals ? (
                                    <Button
                                        key={action.label}
                                        label={action.label}
                                        variant="outline"
                                        onClick={onEditMeals}
                                        className="px-4 py-2 text-sm"
                                    />
                                ) : null,
                            )}
                        </div>
                    ) : null}
                </div>
            ))}

            {showSupplementGuide ? (
                <div className="rounded-[12px] border border-gray-200 bg-white px-4 py-4 sm:px-5">
                    <button
                        type="button"
                        onClick={() => setSupplementGuideOpen((open) => !open)}
                        className="flex w-full items-center justify-between gap-3 text-left"
                        aria-expanded={supplementGuideOpen}
                    >
                        <span>
                            <span className="block font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#5A6B44]">
                                Supplement guide
                            </span>
                            <span className="mt-1 block font-montserrat text-sm font-bold text-[#262A22]">
                                {BIOAVAILABLE_SUPPLEMENT_GUIDE.title}
                            </span>
                        </span>
                        <span className="shrink-0 font-montserrat text-lg font-bold text-[#5A6B44]" aria-hidden>
                            {supplementGuideOpen ? '−' : '+'}
                        </span>
                    </button>

                    {supplementGuideOpen ? (
                        <div className="mt-4 space-y-4 border-t border-gray-100 pt-4">
                            <p className="font-body text-sm text-[#555555]">{BIOAVAILABLE_SUPPLEMENT_GUIDE.intro}</p>
                            {BIOAVAILABLE_SUPPLEMENT_GUIDE.sections.map((section) => (
                                <section key={section.heading}>
                                    <h4 className="font-montserrat text-sm font-bold text-[#262A22]">
                                        {section.heading}
                                    </h4>
                                    <ul className="mt-2 list-disc space-y-1 pl-5 font-body text-sm text-[#374151]">
                                        {section.points.map((point) => (
                                            <li key={point}>{point}</li>
                                        ))}
                                    </ul>
                                </section>
                            ))}
                            <p className="font-body text-xs text-[#6B7280]">
                                This is general wellness information, not medical advice. Confirm doses and
                                interactions with your clinician, especially if you take blood thinners (vitamin K
                                matters for warfarin).
                            </p>
                        </div>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
