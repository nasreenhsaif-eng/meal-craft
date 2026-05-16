import { expandMissingIngredientNames } from './ingredientQuantityString.ts';

/**
 * Bulk ingredient CSV template (must match App\IngredientsImport column order).
 */
export const MISSING_INGREDIENT_CSV_HEADERS = [
    'name',
    'category',
    'fdc_id',
    'calories',
    'protein',
    'carbs',
    'fat',
    'b6',
    'b9_folate',
    'b12',
    'iron',
    'magnesium',
    'fiber',
    'sugar',
    'calcium',
    'potassium',
    'sodium',
    'zinc',
    'vitamin_c',
    'vitamin_a',
    'vitamin_e',
    'vitamin_d',
    'vitamin_k',
    'density',
] as const;

function escapeCsvField(value: string): string {
    if (/[",\n\r]/.test(value)) {
        return `"${value.replace(/"/g, '""')}"`;
    }

    return value;
}

function cellForHeader(header: (typeof MISSING_INGREDIENT_CSV_HEADERS)[number], name: string): string {
    if (header === 'name') {
        return escapeCsvField(name);
    }

    if (header === 'category' || header === 'fdc_id') {
        return '';
    }

    if (header === 'density') {
        return '1';
    }

    return '0';
}

/**
 * Triggers a browser download of a CSV pre-filled with missing ingredient names (bulk-import compatible).
 *
 * @param missingArray - Unique ingredient name strings from the meal library parser
 */
export function downloadMissingIngredientsCSV(missingArray: string[]): void {
    const names = expandMissingIngredientNames(Array.isArray(missingArray) ? missingArray : []);
    const headerLine = MISSING_INGREDIENT_CSV_HEADERS.join(',');
    const bodyLines = names.map((raw) => {
        const name = typeof raw === 'string' ? raw : String(raw);
        return MISSING_INGREDIENT_CSV_HEADERS.map((h) => cellForHeader(h, name)).join(',');
    });
    const csv = [headerLine, ...bodyLines].join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = `missing-ingredients-${new Date().toISOString().slice(0, 10)}.csv`;
    anchor.rel = 'noopener';
    anchor.click();
    URL.revokeObjectURL(url);
}
