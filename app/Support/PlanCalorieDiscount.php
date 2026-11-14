<?php

namespace App\Support;

use App\Models\PlanCaloriePrice;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Builder;

final class PlanCalorieDiscount
{
    /**
     * @param  list<string>|null  $planKeys
     * @param  list<int>|null  $calorieTiers
     * @param  list<int>|null  $daysCounts
     */
    public static function apply(
        string $type,
        int $value,
        ?array $planKeys = null,
        ?array $calorieTiers = null,
        ?array $daysCounts = null,
    ): int {
        $updated = 0;

        foreach (self::matchingPrices($planKeys, $calorieTiers, $daysCounts)->get() as $price) {
            $base = (int) $price->base_total_price;
            $sale = $type === 'percent'
                ? (int) round($base * (1 - (min(100, max(0, $value)) / 100)))
                : max(0, $base - max(0, $value));

            $price->discounted_total_price = $sale < $base ? $sale : null;
            $price->syncDailyRate();
            $price->save();
            $updated++;
        }

        return $updated;
    }

    public static function reset(): int
    {
        $updated = 0;

        foreach (PlanCaloriePrice::query()->whereNotNull('discounted_total_price')->get() as $price) {
            $price->discounted_total_price = null;
            $price->syncDailyRate();
            $price->save();
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  list<string>|null  $planKeys
     * @param  list<int>|null  $calorieTiers
     * @param  list<int>|null  $daysCounts
     * @return Builder<PlanCaloriePrice>
     */
    private static function matchingPrices(?array $planKeys, ?array $calorieTiers, ?array $daysCounts): Builder
    {
        $query = PlanCaloriePrice::query();

        if ($planKeys !== null && $planKeys !== []) {
            $planIds = SubscriptionPlan::query()->whereIn('key', $planKeys)->pluck('id');
            $query->whereIn('plan_id', $planIds);
        }

        if ($calorieTiers !== null && $calorieTiers !== []) {
            $query->whereIn('calorie_tier', $calorieTiers);
        }

        if ($daysCounts !== null && $daysCounts !== []) {
            $query->whereIn('days_count', $daysCounts);
        }

        return $query;
    }
}
