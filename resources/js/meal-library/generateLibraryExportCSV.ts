/**
 * Bulk meal-library import columns (must match {@see App\Services\MealCsvLibraryImportService::LIBRARY_CSV_HEADERS}
 * and {@see App\Services\MealLibrarySynchronizedCsvExport}).
 */
export const MEAL_LIBRARY_SYNCHRONIZED_CSV_HEADERS = [
    'Meal_Name',
    'Category',
    'Ingredient_Quantities',
    'Instructions',
    'Description_Highlight',
    'Meal_Plan_Tags',
    'Cycle_Phase',
    'Total_Calories',
] as const;

import { buildMealCraftExportFilename } from './exportMealDataToCSV';

/**
 * Downloads the full meal library as the Meal Craft **master** CSV (nutrition comparison schema) via the authenticated export route.
 *
 * @param exportUrl - Absolute URL to `GET` (e.g. `route('meals.library.export-csv')` from Blade).
 */
export async function generateLibraryExportCSV(exportUrl: string): Promise<void> {
    const url = typeof exportUrl === 'string' ? exportUrl : '';
    if (url === '') {
        return;
    }

    const response = await fetch(url, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            Accept: 'text/csv, */*',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });

    if (!response.ok) {
        throw new Error(`Meal library export failed (${response.status})`);
    }

    const blob = await response.blob();
    const filename = buildMealCraftExportFilename();

    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    anchor.rel = 'noopener';
    anchor.click();
    URL.revokeObjectURL(objectUrl);
}
