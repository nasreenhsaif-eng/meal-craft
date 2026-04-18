<?php

namespace App\Http\Controllers;

use App\Services\MealCsvLibraryImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

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
        $validated = $request->validate([
            'file' => ['required', File::types(['csv', 'txt'])->max(10240)],
        ]);

        $result = $mealCsvLibraryImportService->processUploadedFile($validated['file']);

        return response()->json($result);
    }
}
