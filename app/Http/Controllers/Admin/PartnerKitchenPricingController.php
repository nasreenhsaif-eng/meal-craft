<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplyPlanCalorieDiscountRequest;
use App\Http\Requests\Admin\ResetPlanCalorieDiscountRequest;
use App\Http\Requests\Admin\StorePartnerKitchenDiscountRequest;
use App\Http\Requests\Admin\UpdatePartnerKitchenPricingRequest;
use App\Models\PartnerKitchenDiscount;
use App\Models\PlanCaloriePrice;
use App\Models\SubscriptionPlan;
use App\Support\PartnerKitchenPrice;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PartnerKitchenPricingController extends Controller
{
    public function index(): Response
    {
        PartnerKitchenPrice::ensureSeeded();

        $scopeOrder = array_flip(PartnerKitchenPrice::CRAFT_DISPLAY_ORDER);

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (SubscriptionPlan $plan): int => $scopeOrder[$plan->key] ?? 99)
            ->values()
            ->map(fn (SubscriptionPlan $plan): array => [
                'key' => $plan->key,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'minCalories' => (int) $plan->min_calories,
                'calorieTiers' => SubscriptionPlanCatalog::tiersFor($plan->key),
                'mealStructure' => $plan->meal_structure,
            ])
            ->all();

        $prices = PlanCaloriePrice::query()
            ->with('plan')
            ->get()
            ->sortBy(fn (PlanCaloriePrice $price): string => sprintf(
                '%02d-%05d-%03d',
                $scopeOrder[$price->plan?->key] ?? 99,
                (int) $price->calorie_tier,
                (int) $price->days_count,
            ))
            ->values()
            ->map(fn (PlanCaloriePrice $price): array => [
                'id' => $price->id,
                'planKey' => (string) $price->plan?->key,
                'planName' => (string) $price->plan?->name,
                'calorieTier' => (int) $price->calorie_tier,
                'daysCount' => (int) $price->days_count,
                'baseTotalPrice' => (int) $price->base_total_price,
                'discountedTotalPrice' => $price->discounted_total_price !== null
                    ? (int) $price->discounted_total_price
                    : null,
                'dailyRate' => (int) $price->daily_rate,
                'isFeatured' => (bool) $price->is_featured,
            ])
            ->all();

        return Inertia::render('Admin/PartnerKitchenPricing', [
            'plans' => $plans,
            'prices' => $prices,
            'durations' => SubscriptionPlanCatalog::DAYS,
            'currency' => (string) config('checkout.currency', 'BHD'),
            'csvExportUrl' => route('admin.partner-kitchen-pricing.export-csv'),
            'csvImportUrl' => route('admin.partner-kitchen-pricing.import-csv'),
        ]);
    }

    public function update(UpdatePartnerKitchenPricingRequest $request): RedirectResponse
    {
        foreach ($request->validated('prices') as $row) {
            $price = PlanCaloriePrice::query()->find($row['id']);

            if (! $price instanceof PlanCaloriePrice) {
                continue;
            }

            $base = (int) $row['base_total_price'];
            $discounted = array_key_exists('discounted_total_price', $row)
                ? $row['discounted_total_price']
                : $price->discounted_total_price;

            if ($discounted !== null && (int) $discounted >= $base) {
                $discounted = null;
            }

            $price->base_total_price = $base;
            $price->discounted_total_price = $discounted !== null ? (int) $discounted : null;
            $price->is_featured = (int) $price->days_count === 24;
            $price->syncDailyRate();
            $price->save();
        }

        return redirect()
            ->route('admin.partner-kitchen-pricing')
            ->with('success', __('Partner Kitchen prices were updated.'));
    }

    public function applyDiscount(ApplyPlanCalorieDiscountRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $type = (string) $validated['discount_type'];
        $value = (int) $validated['discount_value'];

        if ($type === 'percent') {
            $value = min(100, $value);
        }

        $updated = 0;

        $this->matchingPrices($validated)->each(function (PlanCaloriePrice $price) use ($type, $value, &$updated): void {
            $price->applyDiscount($type, $value);
            $updated++;
        });

        return redirect()
            ->route('admin.partner-kitchen-pricing')
            ->with('success', __('Discount applied to :count packages.', ['count' => $updated]));
    }

    public function resetDiscounts(ResetPlanCalorieDiscountRequest $request): RedirectResponse
    {
        $updated = 0;

        $this->matchingPrices($request->validated())->each(function (PlanCaloriePrice $price) use (&$updated): void {
            $price->clearDiscount();
            $updated++;
        });

        return redirect()
            ->route('admin.partner-kitchen-pricing')
            ->with('success', __('Discounts cleared on :count packages.', ['count' => $updated]));
    }

    public function storeDiscount(StorePartnerKitchenDiscountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        PartnerKitchenDiscount::query()->create([
            'label' => $validated['label'],
            'kind' => 'fixed',
            'amount_bhd' => round((float) $validated['amount_bhd'], 3),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('admin.partner-kitchen-pricing')
            ->with('success', __('Discount added.'));
    }

    public function destroyDiscount(PartnerKitchenDiscount $discount): RedirectResponse
    {
        if ($discount->kind === 'limited_time') {
            $discount->update(['is_active' => false]);
        } else {
            $discount->delete();
        }

        return redirect()
            ->route('admin.partner-kitchen-pricing')
            ->with('success', __('Discount removed.'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PlanCaloriePrice>
     */
    private function matchingPrices(array $filters): Builder
    {
        $query = PlanCaloriePrice::query();

        if (($filters['apply_all'] ?? false) === true) {
            return $query;
        }

        $planKeys = $filters['plan_keys'] ?? [];
        $tiers = $filters['calorie_tiers'] ?? [];
        $days = $filters['days_counts'] ?? [];

        if ($planKeys === [] && $tiers === [] && $days === []) {
            return $query->whereRaw('0 = 1');
        }

        if ($planKeys !== []) {
            $query->whereHas('plan', fn (Builder $plan) => $plan->whereIn('key', $planKeys));
        }

        if ($tiers !== []) {
            $query->whereIn('calorie_tier', $tiers);
        }

        if ($days !== []) {
            $query->whereIn('days_count', $days);
        }

        return $query;
    }
}
