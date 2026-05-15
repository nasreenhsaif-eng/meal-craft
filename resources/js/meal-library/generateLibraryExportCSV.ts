import { buildMealCraftExportFilename } from './exportMealDataToCSV';

/**
 * Admin Meal Library “Download CSV template” columns.
 * Must match {@see App\Services\MealCraftMasterCsvExport::MEAL_CRAFT_CSV_TEMPLATE_HEADERS} and {@see App\Services\MealCraftMasterCsvExport::mealCraftCsvTemplateCsv}.
 */
export const MEAL_CRAFT_CSV_TEMPLATE_HEADERS = [
    'Meal Name',
    'Meal Type',
    'Ingredients String',
    'Target Calories',
    'Target Protein',
    'Target Carbs',
    'Target Fat',
    'Batch Calories',
    'Batch Protein',
    'Batch Carbs',
    'Batch Fat',
    'Is Bulk',
    'Servings Count',
    'Meal Plan Tag',
    'Cycle phase',
    'Safety Alerts',
    'Image_URL',
    'Description',
] as const;

const MEAL_CRAFT_CSV_TEMPLATE_FILENAME = 'meal-craft-csv-template.csv';

/**
 * Fetches the authenticated meal-library CSV template (same bytes as {@see App\Services\MealCraftMasterCsvExport::mealCraftCsvTemplateCsv}).
 *
 * @param templateUrl - Absolute URL to `GET` (e.g. `route('admin.meal-library.csv-template')` from Inertia props).
 */
export async function downloadMealCraftCsvTemplate(templateUrl: string): Promise<void> {
    const url = typeof templateUrl === 'string' ? templateUrl : '';
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
        throw new Error(`CSV template download failed (${response.status})`);
    }

    const blob = await response.blob();
    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = MEAL_CRAFT_CSV_TEMPLATE_FILENAME;
    anchor.rel = 'noopener';
    anchor.click();
    URL.revokeObjectURL(objectUrl);
}

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
    'Image_URL',
] as const;

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
