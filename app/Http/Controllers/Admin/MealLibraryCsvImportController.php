<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MealCsvLibraryImportService;
use App\Services\MenuDevelopmentCsvSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Inertia meal-library CSV import (redirect + session flash). JSON clients use {@see \App\Http\Controllers\MealLibraryCsvImportController}.
 */
class MealLibraryCsvImportController extends Controller
{
    public function __invoke(Request $request, MealCsvLibraryImportService $mealCsvLibraryImportService, MenuDevelopmentCsvSync $menuDevelopmentCsvSync): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'file' => ['required', File::types(['csv', 'txt'])->max(10240)],
            ]);
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.meal-library')
                ->with('mealCsvImportResult', [
                    'error' => true,
                    'message' => (string) __('The CSV file did not pass validation.'),
                    'validationErrors' => $e->errors(),
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
                ]);
        }

        try {
            $result = $mealCsvLibraryImportService->processUploadedFile($validated['file'], $request->user());
            $result['import_error_lines'] = $this->importErrorLines($result['rows'] ?? []);
        } catch (Throwable $e) {
            Log::error('Meal library CSV import failed with an exception.', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return redirect()
                ->route('admin.meal-library')
                ->with('mealCsvImportResult', [
                    'error' => true,
                    'message' => (string) __('The CSV could not be imported. Check that ingredients use a supported format such as Name (115g) or Name:115g, separated by commas or |.'),
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
                ]);
        }

        $menuDevelopmentCsvSync->syncMealsFromDatabase();

        return redirect()
            ->route('admin.meal-library')
            ->with('mealCsvImportResult', $result);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function importErrorLines(array $rows): array
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
