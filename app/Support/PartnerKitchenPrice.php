<?php

namespace App\Support;

use App\Models\PartnerKitchenDiscount;
use App\Models\PlanCaloriePrice;
use App\Models\SubscriptionPlan;
use Database\Seeders\PartnerKitchenPriceSeeder;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves Partner Kitchen meal package prices by craft and calorie row.
 */
final class PartnerKitchenPrice
{
    public const CALORIE_CRAFTS = ['full', 'afternoon', 'day', 'intermittent'];

    /** Admin / checkout display order: calorie crafts then business. */
    public const CRAFT_DISPLAY_ORDER = ['full', 'afternoon', 'day', 'intermittent', 'business'];

    /** @deprecated Use CALORIE_CRAFTS */
    public const SHARED_CRAFTS = self::CALORIE_CRAFTS;

    /**
     * @return list<int>
     */
    public static function ensureSeeded(): void
    {
        if (! self::pricesTableReady()) {
            return;
        }

        if (SubscriptionPlan::query()->doesntExist()) {
            (new PartnerKitchenPriceSeeder)->run();
        }
    }

    /**
     * @return list<int>
     */
    public static function calorieRows(string $craftKey = 'full'): array
    {
        $craftKey = self::normalizeCraftKey($craftKey);

        if (self::pricesTableReady()) {
            $plan = SubscriptionPlan::query()->where('key', $craftKey)->first();

            if ($plan instanceof SubscriptionPlan) {
                $rows = $plan->calorieTiers();

                if ($rows !== []) {
                    return $rows;
                }
            }
        }

        return SubscriptionPlanCatalog::tiersFor($craftKey);
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
            return 650;
        }

        $fullTotal = CraftLibraryTierMap::snapToCraftTotal($dailyNeed, 'full');
        $fullRow = CraftLibraryTierMap::row('full', $fullTotal);

        $derived = match ($craftKey) {
            'afternoon' => max(0, $fullTotal - (int) $fullRow['breakfast']),
            'day', 'intermittent' => max(0, $fullTotal - (int) $fullRow['main_each']),
            default => $fullTotal,
        };

        $snapped = self::nearestCalorieRow($derived, $craftKey);
        $minimum = SubscriptionPlanCatalog::plan($craftKey)['min_calories'] ?? $snapped;

        return max($minimum, $snapped);
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
            return self::lookupAmount('business', 650, $billedDays);
        }

        $row = self::nearestCalorieRow($calories, $craftKey);

        return self::lookupAmount($craftKey, $row, $billedDays);
    }

    /**
     * Four-week daily rate shown on craft buttons (same for 5/6/7-day weeks).
     */
    public static function dailyRateForCraft(string $craftKey, int $calories): float
    {
        $craftKey = self::normalizeCraftKey($craftKey);
        $tier = $craftKey === 'business'
            ? 650
            : self::nearestCalorieRow($calories, $craftKey);

        if (self::pricesTableReady()) {
            $plan = SubscriptionPlan::query()->where('key', $craftKey)->first();
            $price = $plan?->prices()
                ->where('calorie_tier', $tier)
                ->where('days_count', 20)
                ->first();

            if ($price instanceof PlanCaloriePrice) {
                return (float) $price->daily_rate;
            }
        }

        return (float) SubscriptionPlanCatalog::dailyRate(
            SubscriptionPlanCatalog::basePrice($tier, 20),
            20,
        );
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
        $tier = $scope === 'business' ? 650 : (int) $calorieRow;

        if (self::pricesTableReady()) {
            $plan = SubscriptionPlan::query()->where('key', $scope)->first();

            if ($plan instanceof SubscriptionPlan) {
                $price = $plan->prices()
                    ->where('calorie_tier', $tier)
                    ->where('days_count', $billedDays)
                    ->first();

                if ($price instanceof PlanCaloriePrice) {
                    return (float) $price->effectiveTotalPrice();
                }
            }
        }

        return (float) SubscriptionPlanCatalog::basePrice($tier, $billedDays);
    }

    private static function pricesTableReady(): bool
    {
        try {
            return Schema::hasTable('plan_calorie_prices') && Schema::hasTable('subscription_plans');
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
