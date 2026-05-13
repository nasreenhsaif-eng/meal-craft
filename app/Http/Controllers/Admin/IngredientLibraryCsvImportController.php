<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\IngredientsImport;
use App\Services\MealCsvLibraryImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IngredientLibraryCsvImportController extends Controller
{
    public function __invoke(
        Request $request,
        IngredientsImport $ingredientsImport,
        MealCsvLibraryImportService $mealCsvLibraryImportService,
    ): RedirectResponse {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $count = $ingredientsImport->import($validated['file']);

        $user = $request->user();
        $mealFollowUp = $mealCsvLibraryImportService->processPendingMealImportsForUser($user);

        $message = __('Import complete: :n ingredient rows processed.', ['n' => $count]);

        if (($mealFollowUp['imported'] ?? 0) > 0 || ($mealFollowUp['updated'] ?? 0) > 0) {
            $message .= ' '.__(
                'Meal library: :imported new meal(s) created and :updated updated from your pending meal CSV import.',
                [
                    'imported' => $mealFollowUp['imported'],
                    'updated' => $mealFollowUp['updated'],
                ],
            );
        }

        if (($mealFollowUp['still_pending'] ?? 0) > 0) {
            $message .= ' '.__('Some meal CSV rows are still waiting on ingredients that are not in the library yet.');
        }

        return redirect()
            ->route('admin.ingredient-library')
            ->with('success', $message);
    }
}
