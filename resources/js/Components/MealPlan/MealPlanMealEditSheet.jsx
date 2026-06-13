import { useEffect, useMemo, useState } from 'react';
import Button from '../Atoms/Button.jsx';
import PillButton from '../Atoms/Button/Button.jsx';
import TextInput from '../Atoms/TextInput/TextInput.jsx';
import SquareCheckbox from '../Atoms/Icons/SquareCheckbox.jsx';
import MacroGrid from '../MacroGrid.jsx';
import MealIngredientRowsEditor from './MealIngredientRowsEditor.jsx';
import {
    applyEditFormToMealCard,
    buildIngredientDatabase,
    nutritionFromEditForm,
    persistMealPlanMealEdit,
} from '../../Pages/MealPlanLibrary/mealPlanMealEdit.js';

/** @typedef {{ ingredientId: number | null; selectedName: string; nameQuery: string; amount: string; unit: string }} IngredientRow */

/**
 * Interactive edit sheet for a meal assigned in a meal plan deck.
 * Ingredient quantity + bulk fields mirror the Meal Library create/edit layout.
 *
 * @param {object} props
 * @param {object} props.meal Consultation card row (`editForm`, `macros`, `detailView`)
 * @param {object[]} [props.ingredientProfiles]
 * @param {() => void} props.onClose
 * @param {(updatedMeal: object) => void} props.onApply
 */
export default function MealPlanMealEditSheet({ meal, ingredientProfiles = [], onClose, onApply }) {
    const editFormSeed = meal?.editForm ?? {};

    const [isBulk, setIsBulk] = useState(() => Boolean(editFormSeed.isBulk));
    const [servingsCount, setServingsCount] = useState(() => String(editFormSeed.servingsCount ?? ''));
    const [ingredientRows, setIngredientRows] = useState(
        /** @type {IngredientRow[]} */ (
            Array.isArray(editFormSeed.ingredientRows) && editFormSeed.ingredientRows.length > 0
                ? editFormSeed.ingredientRows.map((r) => ({
                      ingredientId: r.ingredientId ?? null,
                      selectedName: r.selectedName ?? '',
                      nameQuery: r.nameQuery ?? r.selectedName ?? '',
                      amount: String(r.amount ?? '100'),
                      unit: r.unit ?? 'g',
                  }))
                : [{ ingredientId: null, selectedName: '', nameQuery: '', amount: '100', unit: 'g' }]
        ),
    );

    useEffect(() => {
        const ef = meal?.editForm ?? {};
        setIsBulk(Boolean(ef.isBulk));
        setServingsCount(String(ef.servingsCount ?? ''));
        setIngredientRows(
            Array.isArray(ef.ingredientRows) && ef.ingredientRows.length > 0
                ? ef.ingredientRows.map((r) => ({
                      ingredientId: r.ingredientId ?? null,
                      selectedName: r.selectedName ?? '',
                      nameQuery: r.nameQuery ?? r.selectedName ?? '',
                      amount: String(r.amount ?? '100'),
                      unit: r.unit ?? 'g',
                  }))
                : [{ ingredientId: null, selectedName: '', nameQuery: '', amount: '100', unit: 'g' }],
        );
    }, [meal?.id, meal?.editForm]);

    const ingredientDatabase = useMemo(() => buildIngredientDatabase(ingredientProfiles), [ingredientProfiles]);

    const draftEditForm = useMemo(
        () => ({
            ...editFormSeed,
            isBulk,
            servingsCount,
            ingredientRows,
        }),
        [editFormSeed, isBulk, servingsCount, ingredientRows],
    );

    const liveNutrition = useMemo(
        () => nutritionFromEditForm(draftEditForm, ingredientDatabase),
        [draftEditForm, ingredientDatabase],
    );

    const handleApply = () => {
        const updatedMeal = applyEditFormToMealCard(meal, draftEditForm, ingredientDatabase);

        persistMealPlanMealEdit({
            mealId: String(meal.id),
            editForm: draftEditForm,
            macros: updatedMeal.macros,
        });

        onApply(updatedMeal);
        onClose();
    };

    return (
        <>
            <div className="flex items-start justify-between gap-4 border-b border-gray-100 px-5 py-4">
                <div className="min-w-0">
                    <h2 id="meal-plan-edit-modal-title" className="font-montserrat text-lg font-bold text-[#262A22]">
                        {meal.title ?? editFormSeed.name ?? 'Edit meal'}
                    </h2>
                    <p className="mt-1 font-body text-sm text-[#555555]">
                        Adjust ingredient amounts and bulk settings. Changes update this plan view immediately.
                    </p>
                </div>
                <Button label="Close" variant="ghost" type="button" onClick={onClose} />
            </div>

            <div className="flex-1 overflow-y-auto px-5 py-4">
                <div className="rounded-[12px] border border-gray-200 bg-[#F8F9F6] px-4 py-3">
                    <p className="font-montserrat text-xs font-bold uppercase tracking-[0.14em] text-[#5A6B44]">
                        Live macros (per serving)
                    </p>
                    <div className="mt-2">
                        <MacroGrid
                            calories={liveNutrition.macros.calories}
                            protein={liveNutrition.macros.protein}
                            carbs={liveNutrition.macros.carbs}
                            fat={liveNutrition.macros.fat}
                            compact
                            fluid
                            className="!w-full !max-w-full min-w-0"
                        />
                    </div>
                </div>

                <div className="mt-4 rounded-[12px] border border-gray-200 bg-white p-4">
                    <button
                        type="button"
                        className="inline-flex items-center gap-3"
                        onClick={() => setIsBulk((v) => !v)}
                        aria-pressed={isBulk}
                    >
                        <SquareCheckbox checked={isBulk} />
                        <span className="font-montserrat text-sm font-bold text-[#262A22]">Bulk recipe</span>
                    </button>
                    <p className="mt-2 font-body text-sm text-[#555555]">
                        Batch totals roll up from ingredients; card macros show per-serving values when servings are set.
                    </p>
                    {isBulk ? (
                        <div className="mt-4">
                            <TextInput
                                label="Number of servings"
                                placeholder="e.g. 8"
                                value={servingsCount}
                                onChange={(e) => setServingsCount(e.target.value)}
                                className="!max-w-none"
                                inputMode="decimal"
                            />
                        </div>
                    ) : null}
                </div>

                <div className="mt-4">
                    <MealIngredientRowsEditor
                        rows={ingredientRows}
                        onRowsChange={setIngredientRows}
                        ingredientDatabase={ingredientDatabase}
                        comboboxIdPrefix="meal-plan-edit-ingredient"
                    />
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 px-5 py-4">
                <PillButton label="Cancel" variant="outline" onClick={onClose} />
                <PillButton label="Apply changes" variant="primary" onClick={handleApply} />
            </div>
        </>
    );
}
