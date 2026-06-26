import { useMemo } from 'react';
import MealIngredientRowsEditor from '../../Components/MealPlan/MealIngredientRowsEditor.jsx';
import TextInput from '../../Components/Atoms/TextInput/TextInput.jsx';
import NutrientBadge from '../../Components/Atoms/MealSystem/NutrientBadge.jsx';
import { MealNutritionSummaryTable } from '../../Components/Molecules/MealDetailView/MealDetailView.tsx';
import { aggregateNutritionFromIngredientRows } from '../../meal-library/aggregateIngredientNutrition.ts';
import {
    buildNutritionalDataPer100gSidebar,
    scaleNutritionToPer100g,
} from '../../meal-library/buildNutritionalDataPer100g.ts';
import { sickleCellHighlightBadgeLabels } from '../../meal-library/sickleCellNutrientRdi.ts';
import {
    CHICKEN_BREAST_RAW_NAME,
    CHICKEN_RAW_TO_COOKED_RATIO,
    cookedGramsFromRawChicken,
} from '../../meal-library/chickenBreastYield.ts';

/**
 * Base recipe editor — Meal Library ingredient rows + yield-based per-100 g nutrition sidebar.
 *
 * @param {object} props
 * @param {{ nameQuery: string; selectedName: string; ingredientId: number | null; amount: string; unit: string }[]} props.rows
 * @param {(updater: (prev: typeof props.rows) => typeof props.rows) => void} props.onRowsChange
 * @param {object[]} props.ingredientDatabase
 * @param {import('../../meal-library/calculateMealNutrition').IngredientProfile[]} props.ingredientProfiles
 * @param {string} props.finishedWeightGrams
 * @param {(value: string) => void} props.onFinishedWeightChange
 * @param {string} props.description
 * @param {(value: string) => void} props.onDescriptionChange
 * @param {string} props.instructions
 * @param {(value: string) => void} props.onInstructionsChange
 */
export default function BaseRecipeEditorView({
    rows,
    onRowsChange,
    ingredientDatabase,
    ingredientProfiles,
    finishedWeightGrams,
    onFinishedWeightChange,
    description,
    onDescriptionChange,
    instructions,
    onInstructionsChange,
}) {
    const batchNutrition = useMemo(() => {
        const { nutrition } = aggregateNutritionFromIngredientRows(rows, ingredientProfiles);
        return nutrition;
    }, [rows, ingredientProfiles]);

    const per100Nutrition = useMemo(() => {
        const finished = Number(String(finishedWeightGrams ?? '').trim());
        return scaleNutritionToPer100g(batchNutrition, finished);
    }, [batchNutrition, finishedWeightGrams]);

    const nutritionalData = useMemo(() => {
        if (!per100Nutrition) {
            return null;
        }
        return buildNutritionalDataPer100gSidebar(per100Nutrition);
    }, [per100Nutrition]);

    const sickleCellHighlights = useMemo(() => {
        if (!per100Nutrition) {
            return [];
        }
        return sickleCellHighlightBadgeLabels(per100Nutrition);
    }, [per100Nutrition]);

    const finishedWeightInvalid =
        finishedWeightGrams.trim() !== '' &&
        (!Number.isFinite(Number(finishedWeightGrams.trim())) || Number(finishedWeightGrams.trim()) <= 0);

    const rawChickenBatchGrams = useMemo(() => {
        return rows.reduce((sum, row) => {
            if (row.selectedName !== CHICKEN_BREAST_RAW_NAME || row.unit !== 'g') {
                return sum;
            }

            const grams = Number(String(row.amount ?? '').trim());
            return Number.isFinite(grams) && grams > 0 ? sum + grams : sum;
        }, 0);
    }, [rows]);

    const chickenBatchYieldHint = useMemo(() => {
        if (rawChickenBatchGrams <= 0) {
            return null;
        }

        const cookedChickenOnly = cookedGramsFromRawChicken(rawChickenBatchGrams);
        const finished = Number(String(finishedWeightGrams ?? '').trim());

        if (Number.isFinite(finished) && finished > 0) {
            return `${rawChickenBatchGrams} g raw chicken → ~${cookedChickenOnly} g cooked breast (${Math.round(CHICKEN_RAW_TO_COOKED_RATIO * 100)}% yield). Finished batch weight ${finished} g includes marinade solids; per-100 g nutrition uses that total cooked yield.`;
        }

        return `${rawChickenBatchGrams} g raw chicken → ~${cookedChickenOnly} g cooked breast (${Math.round(CHICKEN_RAW_TO_COOKED_RATIO * 100)}% yield). Add retained marinade weight for finished cooked batch weight.`;
    }, [finishedWeightGrams, rawChickenBatchGrams]);

    const nutritionPanel = nutritionalData ? (
        <MealNutritionSummaryTable data={nutritionalData} />
    ) : (
        <p className="font-body text-sm text-[#555555]">
            Add ingredients and enter finished cooked weight to preview per-100 g nutrition.
        </p>
    );

    return (
        <div className="grid grid-cols-1 gap-0 overflow-hidden rounded-[12px] border border-gray-200 bg-white lg:grid-cols-[13fr_7fr]">
            <div className="space-y-6 p-6 md:p-8 lg:border-r lg:border-gray-200">
                <MealIngredientRowsEditor
                    rows={rows}
                    onRowsChange={onRowsChange}
                    ingredientDatabase={ingredientDatabase}
                    comboboxIdPrefix="base-recipe-ingredient-combobox"
                    suggestPortalAttribute="data-base-recipe-ingredient-suggest"
                />

                <TextInput
                    label="Finished cooked weight (g)"
                    type="number"
                    placeholder="e.g. 450"
                    value={finishedWeightGrams}
                    onChange={(e) => onFinishedWeightChange(e.target.value)}
                    className="!max-w-none"
                    inputMode="decimal"
                    required
                />
                {finishedWeightInvalid ? (
                    <p className="font-body text-xs text-[#C44F5D]" role="alert">
                        Enter a positive finished weight in grams.
                    </p>
                ) : (
                    <p className="font-body text-xs text-[#555555]">
                        Total weight after cooking. Nutrition per 100 g is calculated from raw ingredient totals divided
                        by this yield.
                    </p>
                )}
                {chickenBatchYieldHint ? (
                    <p className="font-body text-xs text-[#555555]">{chickenBatchYieldHint}</p>
                ) : null}

                <label className="block space-y-1">
                    <span className="font-montserrat text-[13px] font-bold tracking-wide text-[#374151]">
                        Short description
                    </span>
                    <textarea
                        value={description}
                        onChange={(e) => onDescriptionChange(e.target.value)}
                        placeholder="One-line summary shown under the recipe title"
                        rows={2}
                        className="w-full resize-y rounded-[12px] border border-[#E5E7EB] bg-white px-4 py-3 font-body text-sm text-[#1F2937] outline-none ring-[#5A6B44]/35 placeholder:text-[#9CA3AF] focus:border-[#5A6B44]/35 focus:ring-2"
                    />
                </label>

                <label className="block space-y-1">
                    <span className="font-montserrat text-[13px] font-bold tracking-wide text-[#374151]">Instructions</span>
                    <textarea
                        value={instructions}
                        onChange={(e) => onInstructionsChange(e.target.value)}
                        placeholder="One step per line"
                        rows={5}
                        className="w-full resize-y rounded-[12px] border border-[#E5E7EB] bg-white px-4 py-3 font-body text-sm text-[#1F2937] outline-none ring-[#5A6B44]/35 placeholder:text-[#9CA3AF] focus:border-[#5A6B44]/35 focus:ring-2"
                    />
                </label>

                <section className="space-y-4 lg:hidden" aria-labelledby="base-recipe-nutrition-heading-mobile">
                    <div>
                        <h2
                            id="base-recipe-nutrition-heading-mobile"
                            className="font-montserrat text-lg font-bold tracking-tight text-[#262A22]"
                        >
                            Nutritional summary
                        </h2>
                        <p className="mt-1 font-montserrat text-sm font-medium text-[#555555]">Per 100 g totals</p>
                    </div>
                    {nutritionPanel}
                </section>
            </div>

            <aside className="space-y-6 bg-[#F8F9F6] p-6">
                <div className="space-y-3">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#374151]">
                        Sickle Cell Highlights
                    </p>
                    <p className="font-body text-xs text-[#6B7280]">High Source: ≥20% of daily RDI per 100 g</p>
                    <div className="flex flex-wrap gap-2">
                        {sickleCellHighlights.length > 0 ? (
                            sickleCellHighlights.map((badge) => <NutrientBadge key={badge} type={badge} />)
                        ) : (
                            <p className="font-body text-sm text-[#555555]">—</p>
                        )}
                    </div>
                </div>

                <section className="hidden space-y-4 lg:block" aria-labelledby="base-recipe-nutrition-heading">
                    <div>
                        <h2
                            id="base-recipe-nutrition-heading"
                            className="font-montserrat text-lg font-bold tracking-tight text-[#262A22]"
                        >
                            Nutritional summary
                        </h2>
                        <p className="mt-1 font-montserrat text-sm font-medium text-[#555555]">Per 100 g totals</p>
                    </div>
                    {nutritionPanel}
                </section>
            </aside>
        </div>
    );
}
