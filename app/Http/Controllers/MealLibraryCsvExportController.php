<?php

namespace App\Http\Controllers;

use App\Services\MealCraftMasterCsvExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MealLibraryCsvExportController extends Controller
{
    public function __invoke(MealCraftMasterCsvExport $mealCraftMasterCsvExport): StreamedResponse
    {
        $filename = 'meal-craft-master-'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($mealCraftMasterCsvExport): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            $mealCraftMasterCsvExport->writeFullLibraryToStream($handle);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
