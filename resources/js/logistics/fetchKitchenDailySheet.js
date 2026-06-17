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

    return {
        productionDate: String(body.production_date ?? productionDate),
        rows: mapKitchenSheetRows(Array.isArray(body.rows) ? body.rows : []),
        ingredientLines: mapIngredientLinesToChoiceLines(
            Array.isArray(body.ingredient_lines) ? body.ingredient_lines : [],
        ),
    };
}
