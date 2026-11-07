import { useMemo, useState } from 'react';
import Button from '../../Atoms/Button.jsx';
import { MealNutritionSummaryTable } from '../MealDetailView/MealDetailView.tsx';
import NutrientBadge from '../../MealSystem/NutrientBadge.jsx';
import SafetyAlerts from '../../MealSystem/SafetyAlerts.jsx';
import {
    shoppingIngredientsForMeal,
    shoppingRowsFromDetailView,
} from '../../../customer/customerShoppingList.js';
import { mealInstructionStepsForDisplay } from '../../../meal-library/mealInstructionsDisplay.ts';
import { G6PD_HIGHLIGHT_BADGE } from '../../../meal-library/mealSafetyAndSickle.ts';
import ShoppingIngredientChecklist from './ShoppingIngredientChecklist.jsx';

/**
 * @param {{ className?: string; filled?: boolean }} props
 */
function HeartIcon({ className = '', filled = false }) {
    return (
        <svg className={className} width={22} height={22} viewBox="0 0 24 24" fill="none" aria-hidden>
            <path
                d="M19.5 12.572 12 20l-7.5-7.428A5 5 0 1 1 12 6.013a5 5 0 1 1 7.5 6.559Z"
                stroke="currentColor"
                strokeWidth="1.75"
                strokeLinejoin="round"
                fill={filled ? 'currentColor' : 'none'}
            />
        </svg>
    );
}

/**
 * Full DIY recipe detail (description, Show recipe, favourites, nutrition).
 *
 * @param {object} props
 * @param {{ id: string; title: string; category?: string; hasG6pdTrigger?: boolean; safetyAlerts?: object[]; sickleCellHighlights?: string[]; detailView?: object }} props.meal
 * @param {import('../../../Pages/MealPlanLibrary/dayShoppingList.js').DayShoppingIngredient[]} props.shoppingList
 * @param {(rows: import('../../../Pages/MealPlanLibrary/dayShoppingList.js').DayShoppingIngredient[], shouldAdd: boolean) => void} props.onToggleIngredient
 * @param {boolean} props.favourited
 * @param {() => void} props.onToggleFavourite
 */
export default function CookMealCard({ meal, shoppingList, onToggleIngredient, favourited, onToggleFavourite }) {
    const [recipeOpen, setRecipeOpen] = useState(false);
    const detailView = meal.detailView ?? {};
    const mealBlurb = String(detailView.shortDescription || detailView.description || '').trim();
    const scBadges = (meal.sickleCellHighlights ?? []).filter((badge) => badge !== G6PD_HIGHLIGHT_BADGE);
    const shoppingRows = useMemo(() => {
        const fromKitchen = shoppingIngredientsForMeal(meal);

        if (fromKitchen.length > 0) {
            return fromKitchen;
        }

        return shoppingRowsFromDetailView(detailView);
    }, [meal, detailView]);
    const checkedKeys = useMemo(() => new Set(shoppingList.map((row) => row.key)), [shoppingList]);

    const instructionSections = Array.isArray(detailView.instructionSections)
        ? detailView.instructionSections
        : [];
    const hasInstructionSections = instructionSections.length > 0;
    const instructionSteps = mealInstructionStepsForDisplay(detailView.instructions ?? []);
    const nutritionalData = detailView.nutritionalData ?? null;
    const nutritionSubheading = String(detailView.nutritionSubheading ?? 'Per serving totals');

    return (
        <article className="rounded-[12px] border border-gray-200 bg-white px-3 py-4 sm:px-4">
            <p className="font-montserrat text-[10px] font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                {meal.category}
            </p>
            <h2 className="mt-1 font-montserrat text-lg font-bold tracking-tight text-[#262A22]">{meal.title}</h2>

            {mealBlurb ? (
                <section className="mt-3 space-y-1">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-[#374151]">
                        Description
                    </p>
                    <p className="font-body text-sm leading-relaxed text-[#555555]">{mealBlurb}</p>
                </section>
            ) : null}

            {meal.hasG6pdTrigger ? (
                <div className="mt-3 rounded-[12px] border-2 border-[#B91C1C] bg-[#FEF2F2] p-3">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#991B1B]">
                        G6PD safety
                    </p>
                    <div className="mt-2">
                        <NutrientBadge type="G6PD Safety Alert" />
                    </div>
                </div>
            ) : null}

            {meal.safetyAlerts?.length > 0 ? (
                <div className="mt-3 space-y-2">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                        Safety alerts
                    </p>
                    <SafetyAlerts alerts={meal.safetyAlerts} />
                </div>
            ) : null}

            {scBadges.length > 0 ? (
                <div className="mt-3 space-y-2">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                        Nutrient highlights
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {scBadges.map((badge) => (
                            <NutrientBadge key={`${meal.id}-${badge}`} type={badge} />
                        ))}
                    </div>
                    <button
                        type="button"
                        className="font-montserrat text-sm font-semibold text-[#5A6B44] underline underline-offset-2 hover:text-[#4A5A38]"
                        onClick={() => {
                            document
                                .getElementById(`cook-nutrition-${meal.id}`)
                                ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }}
                    >
                        See full nutritional summary
                    </button>
                </div>
            ) : null}

            <div className="mt-4 flex items-center gap-2">
                <Button
                    type="button"
                    label={recipeOpen ? 'Hide recipe' : 'Show recipe'}
                    variant={recipeOpen ? 'ghost' : 'primary'}
                    size="sm"
                    onClick={() => setRecipeOpen((open) => !open)}
                    className="min-w-[8.5rem]"
                />
                <button
                    type="button"
                    onClick={onToggleFavourite}
                    aria-pressed={favourited}
                    aria-label={favourited ? 'Remove from favourites' : 'Add to favourites'}
                    className={[
                        'inline-flex h-10 w-10 items-center justify-center rounded-full border transition-colors',
                        favourited
                            ? 'border-[#C44F5D]/40 bg-[#FEF2F2] text-[#C44F5D]'
                            : 'border-gray-200 bg-white text-[#6B7280] hover:text-[#C44F5D]',
                    ].join(' ')}
                >
                    <HeartIcon filled={favourited} />
                </button>
            </div>

            {recipeOpen ? (
                <div className="mt-4 space-y-5 border-t border-gray-100 pt-4">
                    <section aria-labelledby={`cook-ingredients-${meal.id}`}>
                        <h3
                            id={`cook-ingredients-${meal.id}`}
                            className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]"
                        >
                            Ingredients
                        </h3>
                        {shoppingRows.length === 0 ? (
                            <p className="mt-2 font-body text-sm text-[#555555]">
                                No ingredient amounts available for this meal.
                            </p>
                        ) : (
                            <ShoppingIngredientChecklist
                                className="mt-2"
                                rows={shoppingRows}
                                checkedKeys={checkedKeys}
                                onToggle={(row, shouldAdd) => onToggleIngredient([row], shouldAdd)}
                                idPrefix={`cook-shop-${meal.id}`}
                            />
                        )}
                        <p className="mt-2 font-body text-xs text-[#6B7280]">
                            Check an ingredient to add it to your shopping list in My Tools.
                        </p>
                    </section>

                    <section aria-labelledby={`cook-instructions-${meal.id}`}>
                        <h3
                            id={`cook-instructions-${meal.id}`}
                            className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]"
                        >
                            Instructions
                        </h3>
                        {hasInstructionSections ? (
                            <div className="mt-3 space-y-5">
                                {instructionSections.map((section) => (
                                    <div key={section.title} className="space-y-2">
                                        <h4 className="font-montserrat text-xs font-bold uppercase tracking-[0.12em] text-[#5A6B44]">
                                            {section.title}
                                        </h4>
                                        <ol className="list-decimal space-y-3 pl-5 font-body text-sm leading-relaxed text-[#374151] marker:font-bold">
                                            {(section.steps ?? []).map((step, idx) => (
                                                <li key={`${section.title}-${idx}`} className="whitespace-pre-line pl-1">
                                                    {step}
                                                </li>
                                            ))}
                                        </ol>
                                    </div>
                                ))}
                            </div>
                        ) : instructionSteps.length > 0 ? (
                            <ol className="mt-3 list-decimal space-y-3 pl-5 font-body text-sm leading-relaxed text-[#374151] marker:font-bold">
                                {instructionSteps.map((step, idx) => (
                                    <li key={idx} className="whitespace-pre-line pl-1">
                                        {step}
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <p className="mt-2 font-body text-sm text-[#555555]">
                                No cooking instructions for this meal yet.
                            </p>
                        )}
                    </section>
                </div>
            ) : null}

            <section
                className="mt-4 space-y-2 border-t border-gray-100 pt-4"
                aria-labelledby={`cook-nutrition-${meal.id}`}
            >
                <div>
                    <h3
                        id={`cook-nutrition-${meal.id}`}
                        className="font-montserrat text-sm font-bold tracking-tight text-[#262A22]"
                    >
                        Nutritional summary
                    </h3>
                    <p className="mt-1 font-body text-xs text-[#555555]">{nutritionSubheading}</p>
                </div>
                {nutritionalData?.sections?.length ? (
                    <MealNutritionSummaryTable data={nutritionalData} />
                ) : (
                    <p className="font-body text-sm text-[#555555]">No nutrition data for this meal.</p>
                )}
            </section>
        </article>
    );
}
