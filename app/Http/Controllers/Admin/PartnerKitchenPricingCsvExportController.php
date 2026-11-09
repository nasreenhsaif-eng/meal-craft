<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PartnerKitchenPriceCard;
use App\PartnerKitchenPriceCardsImport;
use App\Support\PartnerKitchenPrice;
use Database\Seeders\PartnerKitchenPriceSeeder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PartnerKitchenPricingCsvExportController extends Controller
{
    /**
     * Stream Partner Kitchen price cards as CSV (same columns as import).
     */
    public function __invoke(): StreamedResponse
    {
        if (PartnerKitchenPriceCard::query()->count() === 0) {
            (new PartnerKitchenPriceSeeder)->run();
        }

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

            PartnerKitchenPriceCard::query()
                ->get()
                ->sortBy(fn (PartnerKitchenPriceCard $card): string => sprintf(
                    '%02d-%05d-%03d',
                    $scopeOrder[$card->scope] ?? 99,
                    (int) $card->calorie_row,
                    (int) $card->billed_days,
                ))
                ->each(function (PartnerKitchenPriceCard $card) use ($handle): void {
                    fputcsv($handle, [
                        $card->scope,
                        (int) $card->calorie_row,
                        (int) $card->billed_days,
                        round((float) $card->amount_bhd, 3),
                        $card->discount_type === PartnerKitchenPriceCard::DISCOUNT_TYPE_PERCENT
                            ? PartnerKitchenPriceCard::DISCOUNT_TYPE_PERCENT
                            : PartnerKitchenPriceCard::DISCOUNT_TYPE_AMOUNT,
                        round((float) $card->discount_bhd, 3),
                    ], ',', '"', '\\', "\r\n");
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
