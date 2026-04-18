/**
 * Synchronized meal-library CSV columns (must match {@see App\Services\MealLibrarySynchronizedCsvExport::HEADERS}
 * and {@see App\Services\MealCsvLibraryImportService::LIBRARY_CSV_HEADERS}).
 */
export const MEAL_LIBRARY_SYNCHRONIZED_CSV_HEADERS = [
    'Meal_Name',
    'Category',
    'Ingredient_Quantities',
    'Instructions',
    'Description_Highlight',
] as const;

/**
 * Downloads the full meal library CSV (same columns as bulk import) via the authenticated export route.
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
    const disposition = response.headers.get('Content-Disposition');
    let filename = `meal-library-${new Date().toISOString().slice(0, 10)}.csv`;
    const match = disposition?.match(/filename\*=UTF-8''([^;]+)|filename="([^"]+)"|filename=([^;\s]+)/i);
    if (match) {
        const raw = match[1] ?? match[2] ?? match[3];
        if (raw) {
            filename = decodeURIComponent(raw.trim());
        }
    }

    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    anchor.rel = 'noopener';
    anchor.click();
    URL.revokeObjectURL(objectUrl);
}
