<?php

namespace App;

use App\Models\PlanCaloriePrice;
use App\Models\SubscriptionPlan;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

final class PartnerKitchenPriceCardsImport
{
    /** @var list<string> */
    public const HEADERS = [
        'plan_key',
        'calorie_tier',
        'days_count',
        'base_total_price',
        'discounted_total_price',
    ];

    /**
     * Upsert plan calorie prices from CSV by plan key + calorie tier + days.
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
            $allowedKeys = array_column(SubscriptionPlanCatalog::plans(), 'key');
            $allowedDays = SubscriptionPlanCatalog::DAYS;
            $updated = 0;
            $created = 0;
            $rowNumber = 1;

            while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
                $rowNumber++;

                if ($this->rowIsEmpty($row)) {
                    continue;
                }

                $planKey = strtolower(trim((string) ($row[$index['plan_key']] ?? '')));
                $calorieTier = (int) ($row[$index['calorie_tier']] ?? 0);
                $daysCount = (int) ($row[$index['days_count']] ?? 0);
                $base = (int) ($row[$index['base_total_price']] ?? 0);
                $discountRaw = trim((string) ($row[$index['discounted_total_price']] ?? ''));
                $discounted = $discountRaw === '' ? null : (int) $discountRaw;

                if (! in_array($planKey, $allowedKeys, true)) {
                    throw new InvalidArgumentException(__(
                        'Row :row has an invalid plan_key ":plan".',
                        ['row' => $rowNumber, 'plan' => $planKey],
                    ));
                }

                $plan = SubscriptionPlan::query()->where('key', $planKey)->first();

                if (! $plan instanceof SubscriptionPlan) {
                    throw new InvalidArgumentException(__(
                        'Row :row references a plan that is not seeded.',
                        ['row' => $rowNumber],
                    ));
                }

                if (! in_array($calorieTier, SubscriptionPlanCatalog::tiersFor($planKey), true)) {
                    throw new InvalidArgumentException(__(
                        'Row :row has a calorie tier that is not eligible for :plan.',
                        ['row' => $rowNumber, 'plan' => $planKey],
                    ));
                }

                if (! in_array($daysCount, $allowedDays, true)) {
                    throw new InvalidArgumentException(__(
                        'Row :row has an invalid days_count value.',
                        ['row' => $rowNumber],
                    ));
                }

                if ($base < 0 || ($discounted !== null && $discounted < 0)) {
                    throw new InvalidArgumentException(__(
                        'Row :row has a negative price.',
                        ['row' => $rowNumber],
                    ));
                }

                if ($discounted !== null && $discounted >= $base) {
                    $discounted = null;
                }

                $price = PlanCaloriePrice::query()->firstOrNew([
                    'plan_id' => $plan->id,
                    'calorie_tier' => $calorieTier,
                    'days_count' => $daysCount,
                ]);

                $wasNew = ! $price->exists;
                $price->base_total_price = $base;
                $price->discounted_total_price = $discounted;
                $price->is_featured = $daysCount === 24;
                $price->syncDailyRate();
                $price->save();

                if ($wasNew) {
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
