<?php

namespace App\Http\Controllers;

use App\Services\MealLibrarySynchronizedCsvExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MealLibraryCsvExportController extends Controller
{
    public function __invoke(MealLibrarySynchronizedCsvExport $mealLibrarySynchronizedCsvExport): StreamedResponse
    {
        $filename = 'meal-library-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($mealLibrarySynchronizedCsvExport): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            $mealLibrarySynchronizedCsvExport->writeFullLibraryToStream($handle);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
