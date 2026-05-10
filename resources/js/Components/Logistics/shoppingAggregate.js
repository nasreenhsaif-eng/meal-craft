/**
 * @typedef {{ ingredient: string; unit: string; gramsPerPortion: number; portions: number; recipeLabel?: string; }} IngredientChoiceLine
 */

/**
 * @typedef {{ ingredient: string; unit: string; totalAmount: number; lineCount: number; }} BulkLine
 */

/**
 * @typedef {{ recipeLabel: string; totalPortions: number; ingredients: { ingredient: string; unit: string; amount: number }[] }} RecipeBundle
 */

const norm = (s) => s.trim().toLowerCase();

/**
 * Aggregate shopping data from per-choice ingredient lines (e.g. 10× salmon 150g + 3× salmon 150g → 1950 g).
 *
 * @param {IngredientChoiceLine[]} lines
 * @returns {{ bulk: BulkLine[]; recipes: { label: string; portions: number }[]; quantities: { ingredient: string; unit: string; recipeLabel: string; portions: number; perPortion: number; lineTotal: number }[] }}
 */
export function aggregateIngredientsFromChoices(lines) {
    /** @type {Map<string, { unit: string; total: number; count: number; label: string }>} */
    const bulkMap = new Map();
    /** @type {Map<string, { portions: number; ingredients: Map<string, { unit: string; perPortion: number }> }>} */
    const recipeMap = new Map();

    for (const line of lines) {
        const key = `${norm(line.ingredient)}|${line.unit}`;
        const lineTotal = line.gramsPerPortion * line.portions;
        const prev = bulkMap.get(key);
        if (prev) {
            prev.total += lineTotal;
            prev.count += 1;
        } else {
            bulkMap.set(key, { unit: line.unit, total: lineTotal, count: 1, label: line.ingredient });
        }

        const recipeKey = line.recipeLabel?.trim() || 'Unassigned';
        let rb = recipeMap.get(recipeKey);
        if (!rb) {
            rb = { portions: 0, ingredients: new Map() };
            recipeMap.set(recipeKey, rb);
        }
        rb.portions += line.portions;
        const ingKey = `${norm(line.ingredient)}|${line.unit}`;
        const ingPrev = rb.ingredients.get(ingKey);
        if (ingPrev) {
            ingPrev.perPortion = Math.max(ingPrev.perPortion, line.gramsPerPortion);
        } else {
            rb.ingredients.set(ingKey, { label: line.ingredient, unit: line.unit, perPortion: line.gramsPerPortion });
        }
    }

    const bulk = [...bulkMap.entries()].map(([k, v]) => {
        const ingredient = v.label ?? k.split('|')[0];
        return {
            ingredient,
            unit: v.unit,
            totalAmount: Math.round(v.total * 10) / 10,
            lineCount: v.count,
        };
    });
    bulk.sort((a, b) => a.ingredient.localeCompare(b.ingredient));

    const recipes = [...recipeMap.entries()]
        .map(([label, data]) => ({ label, portions: data.portions }))
        .sort((a, b) => a.label.localeCompare(b.label));

    /** @type {{ ingredient: string; unit: string; recipeLabel: string; portions: number; perPortion: number; lineTotal: number }[]} */
    const quantities = [];
    for (const line of lines) {
        quantities.push({
            ingredient: line.ingredient,
            unit: line.unit,
            recipeLabel: line.recipeLabel ?? '—',
            portions: line.portions,
            perPortion: line.gramsPerPortion,
            lineTotal: Math.round(line.gramsPerPortion * line.portions * 10) / 10,
        });
    }
    quantities.sort((a, b) => a.recipeLabel.localeCompare(b.recipeLabel) || a.ingredient.localeCompare(b.ingredient));

    return { bulk, recipes, quantities };
}
