export {
    calculateMealNutrition,
    calorieWarningsForCategory,
    computeMealHealthScore,
    MEAL_LIBRARY_CSV_CATEGORY_VALUES,
    resolveMealLibraryCategory,
} from './meal-library/calculateMealNutrition';
export { downloadMissingIngredientsCSV } from './meal-library/downloadMissingIngredientsCSV';
export {
    buildMealCraftExportFilename,
    downloadMealCraftExportCsv,
    exportMealDataToCSV,
    formatMealCraftVarianceNotes,
    MEAL_CRAFT_MASTER_CSV_HEADERS,
    MEAL_CRAFT_MASTER_MISSING_PHOTO_PLACEHOLDER,
    mealCraftExportDatePart,
    normalizeCyclePhaseForMealCraftCsv,
} from './meal-library/exportMealDataToCSV';
export { generateLibraryExportCSV, MEAL_LIBRARY_SYNCHRONIZED_CSV_HEADERS } from './meal-library/generateLibraryExportCSV';

import { downloadMissingIngredientsCSV } from './meal-library/downloadMissingIngredientsCSV';
import { downloadMealCraftExportCsv } from './meal-library/exportMealDataToCSV';
import { generateLibraryExportCSV } from './meal-library/generateLibraryExportCSV';

if (typeof window !== 'undefined') {
    window.downloadMissingIngredientsCSV = downloadMissingIngredientsCSV;
    window.downloadMealCraftExportCsv = downloadMealCraftExportCsv;
    window.generateLibraryExportCSV = generateLibraryExportCSV;
}
