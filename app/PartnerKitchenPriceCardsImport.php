<?php

namespace App;

use App\Models\PartnerKitchenPriceCard;
use App\Support\PartnerKitchenPrice;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final class PartnerKitchenPriceCardsImport
{
    /** @var list<string> */
    public const HEADERS = [
        'scope',
        'calorie_row',
        'billed_days',
        'amount_bhd',
        'discount_type',
        'discount_bhd',
    ];

    /**
     * Upsert price cards from CSV by scope + calorie_row + billed_days.
     *
     * @return array{updated: int, created: int}
     */
    public function import(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if (! is_string($path) || $path === '' || ! is_readable($path)) {
            throw new InvalidArgumentException(__('The CSV file could not be read.'));
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException(__('The CSV file could not be opened.'));
        }

        try {
            $headerRow = fgetcsv($handle, 0, ',', '"', '\\');

            if (! is_array($headerRow) || $headerRow === []) {
                throw new InvalidArgumentException(__('The CSV file is empty.'));
            }

            $headerRow[0] = isset($headerRow[0])
                ? preg_replace('/^\xEF\xBB\xBF/', '', (string) $headerRow[0])
                : '';

            $headers = array_map(
                fn ($header): string => strtolower(str_replace(' ', '_', trim((string) $header))),
                $headerRow,
            );

            foreach (self::HEADERS as $required) {
                if (! in_array($required, $headers, true)) {
                    throw new InvalidArgumentException(__(
                        'CSV is missing required column: :column',
                        ['column' => $required],
                    ));
                }
            }

            $index = array_flip($headers);
            $allowedScopes = PartnerKitchenPrice::CRAFT_DISPLAY_ORDER;
            $updated = 0;
            $created = 0;
            $rowNumber = 1;

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rowNumber++;

                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $scope = strtolower(trim((string) ($row[$index['scope']] ?? '')));
                $calorieRow = (int) ($row[$index['calorie_row']] ?? 0);
                $billedDays = (int) ($row[$index['billed_days']] ?? 0);
                $amount = round((float) ($row[$index['amount_bhd']] ?? 0), 3);
                $discountType = strtolower(trim((string) ($row[$index['discount_type']] ?? PartnerKitchenPriceCard::DISCOUNT_TYPE_AMOUNT)));
                $discount = round((float) ($row[$index['discount_bhd']] ?? 0), 3);

                if (! in_array($scope, $allowedScopes, true)) {
                    throw new InvalidArgumentException(__(
                        'Row :row has an invalid scope ":scope".',
                        ['row' => $rowNumber, 'scope' => $scope],
                    ));
                }

                if ($billedDays <= 0) {
                    throw new InvalidArgumentException(__(
                        'Row :row has an invalid billed_days value.',
                        ['row' => $rowNumber],
                    ));
                }

                if ($amount < 0 || $discount < 0) {
                    throw new InvalidArgumentException(__(
                        'Row :row has a negative amount or discount.',
                        ['row' => $rowNumber],
                    ));
                }

                if ($discountType === 'percent' || $discountType === '%') {
                    $discountType = PartnerKitchenPriceCard::DISCOUNT_TYPE_PERCENT;
                    $discount = min(100, max(0, $discount));
                } else {
                    $discountType = PartnerKitchenPriceCard::DISCOUNT_TYPE_AMOUNT;
                    $discount = max(0, $discount);
                }

                if ($scope === 'business') {
                    $calorieRow = 0;
                }

                $card = PartnerKitchenPriceCard::query()->updateOrCreate(
                    [
                        'scope' => $scope,
                        'calorie_row' => $calorieRow,
                        'billed_days' => $billedDays,
                    ],
                    [
                        'amount_bhd' => $amount,
                        'discount_type' => $discountType,
                        'discount_bhd' => $discount,
                    ],
                );

                if ($card->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        } finally {
            fclose($handle);
        }

        return [
            'updated' => $updated,
            'created' => $created,
        ];
    }

    /**
     * @param  list<string|null>|false  $row
     */
    private function rowIsEmpty(array|false $row): bool
    {
        if ($row === false || $row === []) {
            return true;
        }

        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
