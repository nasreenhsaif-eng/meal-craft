<?php

namespace App\Http\Controllers;

use App\Services\MealCsvLibraryImportService;
use App\Services\MenuDevelopmentCsvSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Authenticated JSON endpoint for bulk meal-library CSV import.
 *
 * Success JSON uses snake_case: {@code rows} (list of per-line outcomes), {@code summary.errors} (count of rows with
 * {@code status} {@code "error"}), and {@code import_error_lines} (pre-formatted strings for the UI). Each failed row
 * includes {@code line}, {@code status}, {@code message}, and optionally {@code meal_name}.
 *
 * Cycle-phase tag columns may exist on meals for legacy data; CSV import does not refresh them (see
 * {@see MealCsvLibraryImportService::processUploadedFile()}).
 */
class MealLibraryCsvImportController extends Controller
{
    public function __invoke(Request $request, MealCsvLibraryImportService $mealCsvLibraryImportService, MenuDevelopmentCsvSync $menuDevelopmentCsvSync): JsonResponse
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
                    'ingredient_library_imported' => 0,
                    'ingredient_library_updated' => 0,
                    'duplicates_created' => 0,
                    'pending_ingredient_input' => 0,
                    'errors' => 0,
                ],
                'unique_pending_ingredients' => [],
                'csv_unrecognized_headers' => [],
                'rows' => [],
                'import_error_lines' => [],
            ], 422);
        }

        try {
            $result = $mealCsvLibraryImportService->processUploadedFile($validated['file'], $request->user());
        } catch (Throwable $e) {
            Log::error('Meal library CSV import failed with an exception.', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return response()->json([
                'message' => __('The CSV could not be imported. Check that ingredients use a supported format such as Name (115g) or Name:115g, separated by commas or |.'),
                'summary' => [
                    'imported' => 0,
                    'updated' => 0,
                    'duplicates_created' => 0,
                    'pending_ingredient_input' => 0,
                    'errors' => 1,
                ],
                'unique_pending_ingredients' => [],
                'csv_unrecognized_headers' => [],
                'rows' => [],
                'import_error_lines' => [],
            ], 500);
        }

        $result['import_error_lines'] = $this->importErrorLinesForJsonResponse($result['rows'] ?? []);

        $menuDevelopmentCsvSync->syncMealsFromDatabase();

        return response()->json($result);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function importErrorLinesForJsonResponse(array $rows): array
    {
        $lines = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (($row['status'] ?? '') !== 'error') {
                continue;
            }
            $message = $row['message'] ?? null;
            $text = is_string($message) && $message !== '' ? $message : 'Unknown error.';
            $line = (int) ($row['line'] ?? 0);
            $mealName = isset($row['meal_name']) && is_string($row['meal_name']) ? trim($row['meal_name']) : '';
            if ($mealName !== '') {
                $lines[] = sprintf('Line %d (%s): %s', $line, $mealName, $text);
            } else {
                $lines[] = sprintf('Line %d: %s', $line, $text);
            }
        }

        return $lines;
    }
}
