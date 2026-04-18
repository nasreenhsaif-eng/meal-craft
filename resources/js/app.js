export {
    calculateMealNutrition,
    calorieWarningsForCategory,
    computeMealHealthScore,
    MEAL_LIBRARY_CSV_CATEGORY_VALUES,
    resolveMealLibraryCategory,
} from './meal-library/calculateMealNutrition';
export { downloadMissingIngredientsCSV } from './meal-library/downloadMissingIngredientsCSV';
export { generateLibraryExportCSV, MEAL_LIBRARY_SYNCHRONIZED_CSV_HEADERS } from './meal-library/generateLibraryExportCSV';

import { downloadMissingIngredientsCSV } from './meal-library/downloadMissingIngredientsCSV';
import { generateLibraryExportCSV } from './meal-library/generateLibraryExportCSV';

if (typeof window !== 'undefined') {
    window.downloadMissingIngredientsCSV = downloadMissingIngredientsCSV;
    window.generateLibraryExportCSV = generateLibraryExportCSV;
}
