import { aggregateNutritionFromIngredientRows } from '../../meal-library/aggregateIngredientNutrition.ts';
import { resolvePerServingActualForTargets } from '../../meal-library/bulkPlanningVariance.ts';

/** @returns {number|null} */
export function parseBulkServingsCount(raw) {
    const n = Number(String(raw ?? '').trim());
    if (!Number.isFinite(n) || n <= 0) {
        return null;
    }
    return n;
}

/**
 * @param {object[]} ingredientProfiles
 */
export function buildIngredientDatabase(ingredientProfiles) {
    return (ingredientProfiles ?? []).map((p) => ({
        ...p,
        id: typeof p.id === 'number' ? p.id : Number(p.id),
    }));
}

/**
 * Roll up batch nutrition from ingredient rows, then derive per-serving card macros.
 *
 * @param {object} editForm
 * @param {object[]} ingredientDatabase
 */
export function nutritionFromEditForm(editForm, ingredientDatabase) {
    const rows = Array.isArray(editForm?.ingredientRows) ? editForm.ingredientRows : [];
    const { nutrition: batchNutrition } = aggregateNutritionFromIngredientRows(
        rows.map((r) => ({
            selectedName: r.selectedName ?? r.nameQuery ?? '',
            nameQuery: r.nameQuery ?? r.selectedName ?? '',
            ingredientId: r.ingredientId ?? null,
            amount: r.amount ?? '0',
            unit: r.unit ?? 'g',
        })),
        ingredientDatabase,
    );

    const isBulk = Boolean(editForm?.isBulk);
    const perServing = resolvePerServingActualForTargets({
        isBulkRecipe: isBulk,
        bulkServingsCountRaw: String(editForm?.servingsCount ?? ''),
        batchNutrition,
        parseServings: parseBulkServingsCount,
    });

    const source = perServing ?? batchNutrition ?? {};

    return {
        batchNutrition,
        perServing,
        macros: {
            calories: Math.round(Number(source.calories ?? 0)),
            protein: Math.round(Number(source.protein ?? 0) * 10) / 10,
            carbs: Math.round(Number(source.carbs ?? 0) * 10) / 10,
            fat: Math.round(Number(source.fat ?? 0) * 10) / 10,
        },
    };
}

/**
 * Merge edit-form edits into a consultation meal card and refresh on-screen macros.
 *
 * @param {object} meal
 * @param {object} editForm
 * @param {object[]} ingredientDatabase
 */
export function applyEditFormToMealCard(meal, editForm, ingredientDatabase) {
    const { macros, batchNutrition, perServing } = nutritionFromEditForm(editForm, ingredientDatabase);

    const ingredientLines = (editForm.ingredientRows ?? [])
        .filter((r) => String(r.selectedName ?? r.nameQuery ?? '').trim() !== '')
        .map((r) => {
            const grams = String(r.amount ?? '').trim();
            const name = r.selectedName ?? r.nameQuery ?? '';
            return grams !== '' ? `${grams}g ${name}` : name;
        });

    const nextDetailView = meal.detailView
        ? {
              ...meal.detailView,
              nutritionalData: meal.detailView.nutritionalData
                  ? {
                        ...meal.detailView.nutritionalData,
                        calories: macros.calories,
                        protein: macros.protein,
                        carbs: macros.carbs,
                        fat: macros.fat,
                    }
                  : meal.detailView.nutritionalData,
              ingredients: ingredientLines.length > 0 ? ingredientLines : meal.detailView.ingredients,
          }
        : meal.detailView;

    return {
        ...meal,
        macros,
        editForm: { ...editForm },
        detailView: nextDetailView,
        _batchNutrition: batchNutrition,
        _perServingNutrition: perServing,
    };
}

/**
 * Update local meal-plan day state after the edit sheet applies changes.
 *
 * @param {Array<{ dayNumber: number; categories: Record<string, object[]> }>} planDays
 * @param {{ dayNumber: number; categoryKey: string; mealId: string }} target
 * @param {object} updatedMeal
 */
export function updateMealInPlanDays(planDays, target, updatedMeal) {
    return planDays.map((day) => {
        if (day.dayNumber !== target.dayNumber) {
            return day;
        }

        const categoryMeals = day.categories?.[target.categoryKey] ?? [];

        return {
            ...day,
            categories: {
                ...day.categories,
                [target.categoryKey]: categoryMeals.map((m) => (String(m.id) === String(target.mealId) ? updatedMeal : m)),
            },
        };
    });
}

/**
 * Apply meal edits to the in-memory plan view (instant UI refresh).
 *
 * TODO: Bridge persistence to the backend / `Meal_Craft_Master_Template.csv` pipeline:
 *   1. POST `route('admin.meal-library.update', mealId)` with the same payload shape as Meal Library
 *      (`ingredientRows`, `is_bulk`, `servings_count`, batch macro totals) so `MealLibraryController`
 *      persists to DB and auto-syncs `database/data/menu/meals.csv`.
 *   2. Or invoke `php artisan menu:export-csv` after a dedicated meal-plan slot update endpoint
 *      if plan-specific overrides should not mutate the canonical library row.
 *   CSV columns align with `Meal_Craft_Master_Template.csv`: `ingredients`, `calc_cal`, `calc_pro`,
 *   `calc_fat`, `calc_carbs`, `target_cal`, bulk servings via meal library bulk fields.
 *
 * @param {object} payload
 * @param {string} payload.mealId
 * @param {object} payload.editForm
 * @param {object} payload.macros
 */
export function persistMealPlanMealEdit(payload) {
    void payload;
    // Local-only until wired to MealLibraryController::update or menu:export-csv sync.
}
