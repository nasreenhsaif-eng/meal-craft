/**
 * @param {Array<Record<string, unknown>>} apiRows
 */
export function mapKitchenSheetRows(apiRows) {
    return apiRows.map((row) => ({
        id: String(row.id ?? ''),
        name: String(row.customer_name ?? ''),
        breakfast: String(row.breakfast ?? ''),
        m1: String(row.m1 ?? ''),
        m2: String(row.m2 ?? ''),
        soup: String(row.soup ?? ''),
        sideSalad: String(row.sideSalad ?? ''),
        dessert: String(row.dessert ?? ''),
        cutlery: String(row.cutlery ?? ''),
        kitchenInstructions: String(row.kitchen_instructions ?? ''),
        specialRequests: String(row.specialRequests ?? ''),
        allergies: String(row.allergies ?? ''),
    }));
}

/**
 * @param {Array<Record<string, unknown>>} apiLines
 */
export function mapIngredientLinesToChoiceLines(apiLines) {
    return apiLines.map((line) => ({
        ingredient: String(line.ingredient ?? ''),
        unit: 'g',
        gramsPerPortion: Number(line.adapted_amount_grams ?? 0),
        portions: 1,
        recipeLabel: `${String(line.customer_name ?? 'Guest')} — ${String(line.meal_name ?? 'Meal')}`,
    }));
}

/**
 * Flatten `rows[].adapted_meals` for kitchen recipe batching / print packs.
 *
 * @param {Array<Record<string, unknown>>} apiRows
 * @returns {import('../Components/Logistics/kitchenRecipeBatches.js').AdaptedKitchenMeal[]}
 */
export function mapAdaptedMealsFromKitchenRows(apiRows) {
    /** @type {import('../Components/Logistics/kitchenRecipeBatches.js').AdaptedKitchenMeal[]} */
    const out = [];

    for (const row of apiRows) {
        const customerName = String(row.customer_name ?? 'Guest');
        const meals = Array.isArray(row.adapted_meals) ? row.adapted_meals : [];

        for (const meal of meals) {
            if (!meal || typeof meal !== 'object') {
                continue;
            }

            const rawIngredients = Array.isArray(meal.ingredients) ? meal.ingredients : [];
            const ingredients = rawIngredients
                .map((ingredient) => {
                    if (!ingredient || typeof ingredient !== 'object') {
                        return null;
                    }

                    const adaptedAmountGrams = Number(ingredient.adapted_amount_grams ?? 0);
                    if (!Number.isFinite(adaptedAmountGrams) || adaptedAmountGrams <= 0) {
                        return null;
                    }

                    const adaptedAmountRaw = ingredient.adapted_amount;
                    const adaptedAmount =
                        adaptedAmountRaw !== null && adaptedAmountRaw !== undefined && adaptedAmountRaw !== ''
                            ? Number(adaptedAmountRaw)
                            : null;

                    return {
                        id: Number(ingredient.id ?? 0),
                        name: String(ingredient.name ?? ''),
                        adaptedAmountGrams,
                        adaptedAmount:
                            adaptedAmount !== null && Number.isFinite(adaptedAmount) && adaptedAmount > 0
                                ? adaptedAmount
                                : null,
                        unit: String(ingredient.unit ?? 'g') || 'g',
                        isPreparedBase: Boolean(ingredient.is_prepared_base),
                    };
                })
                .filter(Boolean);

            out.push({
                mealId: Number(meal.id ?? 0),
                mealName: String(meal.name ?? 'Meal'),
                slot: String(meal.slot ?? ''),
                imageUrl: meal.image_url ? String(meal.image_url) : null,
                instructions: meal.instructions ?? '',
                customerName,
                ingredients,
            });
        }
    }

    return out;
}

/**
 * @param {string} productionDate ISO date YYYY-MM-DD
 * @param {string} [url]
 */
export async function fetchKitchenDailySheet(productionDate, url = '/api/admin/kitchen/daily-sheet') {
    const query = productionDate ? `?date=${encodeURIComponent(productionDate)}` : '';
    const response = await fetch(`${url}${query}`, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message = typeof body.message === 'string' ? body.message : 'Could not load kitchen sheet.';
        throw new Error(message);
    }

    const apiRows = Array.isArray(body.rows) ? body.rows : [];

    return {
        productionDate: String(body.production_date ?? productionDate),
        rows: mapKitchenSheetRows(apiRows),
        ingredientLines: mapIngredientLinesToChoiceLines(
            Array.isArray(body.ingredient_lines) ? body.ingredient_lines : [],
        ),
        adaptedMeals: mapAdaptedMealsFromKitchenRows(apiRows),
        baseRecipes: Array.isArray(body.base_recipes) ? body.base_recipes : [],
    };
}
