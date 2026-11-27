<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlanCaloriePrice;
use App\PartnerKitchenPriceCardsImport;
use App\Support\PartnerKitchenPrice;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerKitchenPricingCsvExportController extends Controller
{
    /**
     * Stream subscription plan calorie prices as CSV (same columns as import).
     */
    public function __invoke(): StreamedResponse
    {
        PartnerKitchenPrice::ensureSeeded();

        $filename = 'partner-kitchen-pricing-'.now()->format('Ymd_His').'.csv';
        $headers = PartnerKitchenPriceCardsImport::HEADERS;
        $scopeOrder = array_flip(PartnerKitchenPrice::CRAFT_DISPLAY_ORDER);

        return response()->streamDownload(function () use ($headers, $scopeOrder): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            echo "\xEF\xBB\xBF";

            fputcsv($handle, $headers, ',', '"', '\\', "\r\n");

            PlanCaloriePrice::query()
                ->with('plan')
                ->get()
                ->sortBy(fn (PlanCaloriePrice $price): string => sprintf(
                    '%02d-%05d-%03d',
                    $scopeOrder[$price->plan?->key] ?? 99,
                    (int) $price->calorie_tier,
                    (int) $price->days_count,
                ))
                ->each(function (PlanCaloriePrice $price) use ($handle): void {
                    fputcsv($handle, [
                        (string) $price->plan?->key,
                        (int) $price->calorie_tier,
                        (int) $price->days_count,
                        (int) $price->base_total_price,
                        $price->discounted_total_price === null ? '' : (int) $price->discounted_total_price,
                    ], ',', '"', '\\', "\r\n");
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
