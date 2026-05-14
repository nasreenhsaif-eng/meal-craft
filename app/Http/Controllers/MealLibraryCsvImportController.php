<?php

namespace App\Http\Controllers;

use App\Services\MealCsvLibraryImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

/**
 * Authenticated JSON endpoint for bulk meal-library CSV import.
 *
 * Cycle-phase tag columns may exist on meals for legacy data; CSV import does not refresh them (see
 * {@see MealCsvLibraryImportService::processUploadedFile()}).
 */
class MealLibraryCsvImportController extends Controller
{
    public function __invoke(Request $request, MealCsvLibraryImportService $mealCsvLibraryImportService): JsonResponse
    {
        try {
            $validated = $request->validate([
                'file' => ['required', File::types(['csv', 'txt'])->max(10240)],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => __('The CSV file did not pass validation.'),
                'errors' => $e->errors(),
                'summary' => [
                    'imported' => 0,
                    'updated' => 0,
                    'duplicates_created' => 0,
                    'pending_ingredient_input' => 0,
                    'errors' => 0,
                ],
                'unique_pending_ingredients' => [],
                'csv_unrecognized_headers' => [],
                'rows' => [],
            ], 422);
        }

        $result = $mealCsvLibraryImportService->processUploadedFile($validated['file'], $request->user());

        return response()->json($result);
    }
}
