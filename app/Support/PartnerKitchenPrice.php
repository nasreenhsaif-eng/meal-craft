<?php

namespace App\Support;

use App\Models\PartnerKitchenDiscount;
use App\Models\PartnerKitchenPriceCard;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves Partner Kitchen meal package prices by craft and calorie row.
 */
final class PartnerKitchenPrice
{
    public const CALORIE_CRAFTS = ['full', 'afternoon', 'day', 'intermittent'];

    /** @deprecated Use CALORIE_CRAFTS */
    public const SHARED_CRAFTS = self::CALORIE_CRAFTS;

    /**
     * @return list<int>
     */
    public static function calorieRows(string $craftKey = 'full'): array
    {
        $matrix = self::matrixForCraft($craftKey);

        if ($matrix === []) {
            return [1200, 1500, 1800, 2000];
        }

        $rows = array_map('intval', array_keys($matrix));
        sort($rows);

        return array_values($rows);
    }

    /**
     * @return list<int>
     */
    public static function billedDayOptions(): array
    {
        return [5, 6, 7, 20, 24, 28];
    }

    public static function billedDays(int $weekDuration, int $planWeeks): int
    {
        $weekDuration = max(5, min(7, $weekDuration));
        $planWeeks = $planWeeks === 1 ? 1 : 4;

        return $planWeeks === 1 ? $weekDuration : $weekDuration * 4;
    }

    public static function weekDurationFromBilledDays(int $billedDays): int
    {
        return match ($billedDays) {
            5, 20 => 5,
            6, 24 => 6,
            7, 28 => 7,
            default => max(5, min(7, $billedDays)),
        };
    }

    public static function planWeeksFromBilledDays(int $billedDays): int
    {
        return in_array($billedDays, [5, 6, 7], true) ? 1 : 4;
    }

    public static function nearestCalorieRow(int $calories, string $craftKey = 'full'): int
    {
        $rows = self::calorieRows($craftKey);
        $nearest = $rows[0] ?? 1200;
        $smallest = abs($calories - $nearest);

        foreach ($rows as $row) {
            $distance = abs($calories - $row);

            if ($distance < $smallest) {
                $smallest = $distance;
                $nearest = $row;
            }
        }

        return $nearest;
    }

    /**
     * Derive Partner Kitchen price calories from the customer's Full daily need.
     *
     * Afternoon = Full total − breakfast. Day / Intermittent = Full total − one main.
     * Full keeps the Full library total (1250 prices on the 1200 package row).
     */
    public static function priceCaloriesForCraft(int $dailyNeed, string $craftKey): int
    {
        $craftKey = self::normalizeCraftKey($craftKey);

        if ($craftKey === 'business') {
            return $dailyNeed;
        }

        $fullTotal = CraftLibraryTierMap::snapToCraftTotal($dailyNeed, 'full');
        $fullRow = CraftLibraryTierMap::row('full', $fullTotal);

        $derived = match ($craftKey) {
            'afternoon' => max(0, $fullTotal - (int) $fullRow['breakfast']),
            'day', 'intermittent' => max(0, $fullTotal - (int) $fullRow['main_each']),
            default => $fullTotal,
        };

        return self::nearestCalorieRow($derived, $craftKey);
    }

    /**
     * Package subtotal for a craft, calorie target, week length, and plan length.
     */
    public static function packageAmount(
        string $craftKey,
        int $calories,
        int $weekDuration,
        int $planWeeks = 4,
    ): float {
        $billedDays = self::billedDays($weekDuration, $planWeeks);

        return self::amountForBilledDays($craftKey, $calories, $billedDays);
    }

    public static function amountForBilledDays(string $craftKey, int $calories, int $billedDays): float
    {
        $craftKey = self::normalizeCraftKey($craftKey);

        if ($craftKey === 'business') {
            return self::lookupAmount('business', null, $billedDays);
        }

        $row = self::nearestCalorieRow($calories, $craftKey);

        return self::lookupAmount($craftKey, $row, $billedDays);
    }

    /**
     * Four-week daily rate shown on craft buttons (same for 5/6/7-day weeks).
     */
    public static function dailyRateForCraft(string $craftKey, int $calories): float
    {
        $amount = self::packageAmount($craftKey, $calories, 5, 4);

        return round($amount / 20, 1);
    }

    /**
     * @return list<array{billedDays: int, planWeeks: int, weekDuration: int, amount: float, label: string}>
     */
    public static function planLengthOptions(string $craftKey, int $calories): array
    {
        $options = [];

        foreach ([5, 6, 7, 20, 24, 28] as $billedDays) {
            $options[] = [
                'billedDays' => $billedDays,
                'planWeeks' => self::planWeeksFromBilledDays($billedDays),
                'weekDuration' => self::weekDurationFromBilledDays($billedDays),
                'amount' => self::amountForBilledDays($craftKey, $calories, $billedDays),
                'label' => $billedDays.' days',
            ];
        }

        return $options;
    }

    /**
     * @return array{amount: float, label: string}
     */
    public static function activeLimitedTimeDiscount(): array
    {
        if (self::discountsTableReady()) {
            $discount = PartnerKitchenDiscount::query()
                ->where('is_active', true)
                ->where('kind', 'limited_time')
                ->orderByDesc('id')
                ->first();

            if ($discount instanceof PartnerKitchenDiscount) {
                return [
                    'amount' => round((float) $discount->amount_bhd, 3),
                    'label' => (string) $discount->label,
                ];
            }
        }

        return [
            'amount' => round((float) config('checkout.limited_time_discount', 20), 3),
            'label' => (string) config('checkout.limited_time_discount_label', 'Limited time offer'),
        ];
    }

    /**
     * Extra admin cart discounts (fixed BHD), excluding the limited-time offer.
     */
    public static function additionalDiscountTotal(): float
    {
        if (! self::discountsTableReady()) {
            return 0.0;
        }

        return round((float) PartnerKitchenDiscount::query()
            ->where('is_active', true)
            ->where('kind', 'fixed')
            ->sum('amount_bhd'), 3);
    }

    public static function formatMoney(float $amount, int $decimals = 3): string
    {
        $decimals = max(0, min(3, $decimals));

        return sprintf('BHD %.'.$decimals.'f', round($amount, $decimals));
    }

    /**
     * @return array<int, array<int, int|float>>
     */
    public static function matrixForCraft(string $craftKey): array
    {
        $craftKey = self::normalizeCraftKey($craftKey);

        if ($craftKey === 'business') {
            return [];
        }

        /** @var array<string, array<int|string, array<int|string, int|float>>> $matrices */
        $matrices = config('checkout.craft_calorie_matrices', []);

        $matrix = $matrices[$craftKey] ?? $matrices['full'] ?? [];

        if (! is_array($matrix)) {
            return [];
        }

        $normalized = [];

        foreach ($matrix as $calorieRow => $days) {
            if (! is_array($days)) {
                continue;
            }

            $dayAmounts = [];

            foreach ($days as $billedDays => $amount) {
                $dayAmounts[(int) $billedDays] = $amount;
            }

            $normalized[(int) $calorieRow] = $dayAmounts;
        }

        return $normalized;
    }

    private static function normalizeCraftKey(string $craftKey): string
    {
        $craftKey = strtolower(trim($craftKey));

        if ($craftKey === 'business' || in_array($craftKey, self::CALORIE_CRAFTS, true)) {
            return $craftKey;
        }

        return 'full';
    }

    private static function lookupAmount(string $scope, ?int $calorieRow, int $billedDays): float
    {
        if (self::priceCardsTableReady()) {
            $card = PartnerKitchenPriceCard::query()
                ->where('scope', $scope)
                ->where('billed_days', $billedDays)
                ->where('calorie_row', $scope === 'business' ? 0 : $calorieRow)
                ->first();

            if ($card instanceof PartnerKitchenPriceCard) {
                return round((float) $card->amount_bhd, 3);
            }
        }

        if ($scope === 'business') {
            /** @var array<int, int|float> $matrix */
            $matrix = config('checkout.business_matrix', []);

            return round((float) ($matrix[$billedDays] ?? 0), 3);
        }

        $matrix = self::matrixForCraft($scope);

        return round((float) ($matrix[$calorieRow][$billedDays] ?? 0), 3);
    }

    private static function priceCardsTableReady(): bool
    {
        try {
            return Schema::hasTable('partner_kitchen_price_cards');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function discountsTableReady(): bool
    {
        try {
            return Schema::hasTable('partner_kitchen_discounts');
        } catch (\Throwable) {
            return false;
        }
    }
}
