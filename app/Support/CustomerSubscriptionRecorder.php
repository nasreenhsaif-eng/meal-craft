<?php

namespace App\Support;

use App\Enums\CustomerPlanType;
use App\Enums\CustomerSubscriptionStatus;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerProfile;
use App\Models\CustomerSubscription;
use Illuminate\Support\Carbon;

/**
 * Creates a paid subscription snapshot from a submitted craft plan (checkout simulator).
 */
final class CustomerSubscriptionRecorder
{
    /**
     * @return array{
     *     mealPlanName: string,
     *     craftKey: string,
     *     days: int,
     *     startDate: string,
     *     expiryDate: string,
     *     deliveries: int,
     *     fulfillmentChoice: string|null
     * }|null
     */
    public static function detailsFromCraftPlan(CustomerProfile $profile, CustomerCraftPlan $plan): ?array
    {
        $weekDuration = max(5, min(7, (int) ($plan->week_duration ?: 7)));
        $planWeeks = (int) ($plan->plan_weeks ?: 4) === 1 ? 1 : 4;
        $days = PartnerKitchenPrice::billedDays($weekDuration, $planWeeks);
        $fulfillment = is_string($plan->fulfillment_choice) ? $plan->fulfillment_choice : null;
        $craftKey = (string) ($plan->craft_key ?? '');

        $start = $profile->planned_start_date !== null
            ? Carbon::parse($profile->planned_start_date)->startOfDay()
            : Carbon::parse(
                MealPlanDateRange::forWeek(
                    selectedWeekdays: array_values($plan->selected_weekdays ?? []),
                )['startsOn'],
            )->startOfDay();

        $expiry = $start->copy()->addWeeks($planWeeks)->subDay();
        $deliveries = $fulfillment === 'diy' ? 0 : $days;

        $planType = CustomerPlanType::tryFrom($craftKey);

        return [
            'mealPlanName' => $planType?->label() ?? (ucfirst($craftKey) ?: 'Meal Craft'),
            'craftKey' => $craftKey,
            'days' => $days,
            'startDate' => $start->toDateString(),
            'expiryDate' => $expiry->toDateString(),
            'deliveries' => $deliveries,
            'fulfillmentChoice' => $fulfillment,
        ];
    }

    public static function recordPaidFromLatestPlan(CustomerProfile $profile, ?float $amountBhd = null): ?CustomerSubscription
    {
        $plan = $profile->craftPlans()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        if (! $plan instanceof CustomerCraftPlan) {
            return null;
        }

        $details = self::detailsFromCraftPlan($profile, $plan);

        if ($details === null) {
            return null;
        }

        return CustomerSubscription::query()->create([
            'customer_profile_id' => $profile->id,
            'customer_craft_plan_id' => $plan->id,
            'status' => CustomerSubscriptionStatus::Paid,
            'paid_at' => now(),
            'starts_on' => $details['startDate'],
            'expires_on' => $details['expiryDate'],
            'delivery_days_count' => $details['deliveries'],
            'fulfillment_choice' => $details['fulfillmentChoice'],
            'craft_key' => $details['craftKey'] !== '' ? $details['craftKey'] : null,
            'meal_plan_name' => $details['mealPlanName'],
            'amount_bhd' => $amountBhd,
        ]);
    }

    /**
     * @return array{
     *     status: string,
     *     statusLabel: string,
     *     paidAt: string|null,
     *     mealPlanName: string,
     *     days: int,
     *     startDate: string,
     *     expiryDate: string,
     *     deliveries: int,
     *     fulfillmentChoice: string|null,
     *     frozenDays: list<string>
     * }|null
     */
    public static function toAdminArray(?CustomerSubscription $subscription): ?array
    {
        if ($subscription === null) {
            return null;
        }

        $frozenDays = $subscription->relationLoaded('freezes')
            ? $subscription->freezes->pluck('frozen_on')->map(fn ($d) => Carbon::parse($d)->toDateString())->values()->all()
            : $subscription->freezes()->orderBy('frozen_on')->pluck('frozen_on')->map(fn ($d) => Carbon::parse($d)->toDateString())->values()->all();

        return [
            'status' => $subscription->status->value,
            'statusLabel' => $subscription->status->label(),
            'paidAt' => $subscription->paid_at?->toIso8601String(),
            'mealPlanName' => (string) ($subscription->meal_plan_name ?? 'Meal Craft'),
            'days' => (int) $subscription->delivery_days_count,
            'startDate' => $subscription->starts_on?->toDateString() ?? '',
            'expiryDate' => $subscription->expires_on?->toDateString() ?? '',
            'deliveries' => (int) $subscription->delivery_days_count,
            'fulfillmentChoice' => $subscription->fulfillment_choice,
            'frozenDays' => $frozenDays,
        ];
    }
}
