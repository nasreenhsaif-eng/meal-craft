<?php

namespace App\Support;

use App\Enums\DietProtocol;
use App\Http\Controllers\Api\ConsultationResumeDraftController;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerProfile;
use App\Services\CustomerCraftPlanPresentationService;
use App\Services\Nutrition\DayMacroReconciliation;
use App\Services\Nutrition\UserPlanCalculator;
use Illuminate\Support\Carbon;

/**
 * Builds the customer payment-checkout basket payload.
 */
final class CheckoutBasket
{
    /**
     * @return array{
     *     planLabel: string,
     *     averageDayCalories: int,
     *     currency: string,
     *     subtotal: float,
     *     deliveryCost: float,
     *     limitedTimeDiscount: float,
     *     limitedTimeDiscountLabel: string,
     *     promoDiscount: float,
     *     promoCode: string|null,
     *     total: float,
     *     benefitPayNumber: string,
     *     refundPolicyUrl: string,
     *     freezePolicyUrl: string,
     *     deliveryAddress: array{
     *         area: string,
     *         block: string,
     *         road: string,
     *         houseNumber: string,
     *         gateFlatNumber: string,
     *         country: string,
     *         deliveryTime: string|null,
     *         plannedStartDate: string|null,
     *         formatted: string
     *     },
     *     craftPlan: array{craftKey: string, craftTitle: string, weekDuration: int, planWeeks: int}|null
     * }
     */
    public static function forProfile(
        CustomerProfile $profile,
        CustomerCraftPlanPresentationService $presentation,
        ?string $promoCode = null,
        ?int $planWeeksOverride = null,
    ): array {
        $plan = $profile->craftPlans()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        $planTierCalories = $profile->daily_calorie_target !== null
            ? (int) UserPlanCalculator::snapToPlanTier((float) $profile->daily_calorie_target)
            : 1200;

        $averageDayCalories = $planTierCalories;
        $craftMeta = null;
        $subtotal = 0.0;
        $pricing = self::resolvePricingContext($plan, $presentation, $planTierCalories, $planWeeksOverride);

        if ($pricing !== null) {
            $averageDayCalories = $pricing['averageDayCalories'];
            $subtotal = PartnerKitchenPrice::packageAmount(
                $pricing['craftKey'],
                $pricing['priceCalories'],
                $pricing['weekDuration'],
                $pricing['planWeeks'],
            );
            $craftMeta = [
                'craftKey' => $pricing['craftKey'],
                'craftTitle' => $pricing['craftTitle'],
                'weekDuration' => $pricing['weekDuration'],
                'planWeeks' => $pricing['planWeeks'],
            ];
        }

        $diet = DietProtocol::tryFromStored($profile->diet_protocol);
        $dietShort = match ($diet) {
            DietProtocol::Balanced => 'Balanced Anti-inflammatory',
            DietProtocol::NutrientDense => 'Balanced Anti-inflammatory',
            DietProtocol::Ketobiotic => 'Ketobiotic',
            DietProtocol::CycleSync => 'Cycle Sync',
            DietProtocol::Thyroid => 'Thyroid',
            DietProtocol::SickleCellWarrior => 'Sickle Cell Warrior',
        };

        $currency = (string) config('checkout.currency', 'BHD');
        $deliveryCost = (float) config('checkout.delivery_cost', 5);
        $limitedTime = PartnerKitchenPrice::activeLimitedTimeDiscount();
        $limitedTimeDiscount = $limitedTime['amount'];
        $limitedTimeDiscountLabel = $limitedTime['label'];
        $adminExtraDiscount = PartnerKitchenPrice::additionalDiscountTotal();

        $normalizedPromo = $promoCode !== null ? strtoupper(trim($promoCode)) : null;
        $promoCodes = config('checkout.promo_codes', []);
        $promoDiscount = 0.0;

        if ($normalizedPromo !== null && $normalizedPromo !== '' && is_array($promoCodes)) {
            $promoDiscount = (float) ($promoCodes[$normalizedPromo] ?? 0);
            if ($promoDiscount <= 0) {
                $normalizedPromo = null;
            }
        } else {
            $normalizedPromo = null;
        }

        $total = max(0, round(
            $subtotal + $deliveryCost - $limitedTimeDiscount - $adminExtraDiscount - $promoDiscount,
            3,
        ));

        return [
            'planLabel' => sprintf('%s Meal plan %d calories', $dietShort, $averageDayCalories),
            'averageDayCalories' => $averageDayCalories,
            'currency' => $currency,
            'subtotal' => round($subtotal, 3),
            'deliveryCost' => round($deliveryCost, 3),
            'limitedTimeDiscount' => round($limitedTimeDiscount + $adminExtraDiscount, 3),
            'limitedTimeDiscountLabel' => $adminExtraDiscount > 0
                ? $limitedTimeDiscountLabel.' + admin discount'
                : $limitedTimeDiscountLabel,
            'promoDiscount' => round($promoDiscount, 3),
            'promoCode' => $normalizedPromo,
            'total' => $total,
            'benefitPayNumber' => (string) config('checkout.benefit_pay_number', '33177718'),
            'refundPolicyUrl' => (string) config('checkout.refund_policy_url', '/refund-policy#refund'),
            'freezePolicyUrl' => (string) config('checkout.freeze_policy_url', '/refund-policy#freeze'),
            'deliveryAddress' => self::deliveryAddress($profile),
            'craftPlan' => $craftMeta,
        ];
    }

    /**
     * @param  array{days?: list<array{categories?: array<string, list<array<string, mixed>>>}>}  $summary
     */
    public static function averageDayCaloriesFromSummary(array $summary): int
    {
        $days = $summary['days'] ?? [];

        if ($days === []) {
            return (int) ($summary['planTierCalories'] ?? 0);
        }

        $total = 0.0;
        $count = 0;

        foreach ($days as $day) {
            if (! is_array($day)) {
                continue;
            }

            $categories = is_array($day['categories'] ?? null) ? $day['categories'] : [];
            $macros = DayMacroReconciliation::sumDayMacros([
                'breakfasts' => $categories['breakfasts'] ?? [],
                'meals' => $categories['meals'] ?? [],
                'sideSalads' => $categories['sideSalads'] ?? [],
                'desserts' => $categories['desserts'] ?? [],
                'soup' => $categories['soup'] ?? [],
            ]);

            $dayCalories = (float) ($macros['calories'] ?? 0);

            if ($dayCalories <= 0) {
                continue;
            }

            $total += $dayCalories;
            $count++;
        }

        if ($count === 0) {
            return (int) ($summary['planTierCalories'] ?? 0);
        }

        return (int) round($total / $count);
    }

    /**
     * @return array{
     *     craftKey: string,
     *     craftTitle: string,
     *     weekDuration: int,
     *     planWeeks: int,
     *     priceCalories: int,
     *     averageDayCalories: int
     * }|null
     */
    private static function resolvePricingContext(
        ?CustomerCraftPlan $plan,
        CustomerCraftPlanPresentationService $presentation,
        int $planTierCalories,
        ?int $planWeeksOverride,
    ): ?array {
        if ($plan instanceof CustomerCraftPlan) {
            $summary = $presentation->presentSummary($plan, $planTierCalories);
            $planWeeks = $planWeeksOverride ?? (int) ($plan->plan_weeks ?: 4);
            $planWeeks = $planWeeks === 1 ? 1 : 4;
            $weekDuration = (int) ($summary['weekDuration'] ?? $plan->week_duration ?? 7);
            $craftKey = (string) ($summary['craftKey'] ?? $plan->craft_key);
            $priceCalories = (int) ($summary['planTierCalories'] ?? $planTierCalories);

            if ($craftKey !== 'business') {
                $priceCalories = CraftLibraryTierMap::snapToCraftTotal($priceCalories, $craftKey);
            }

            return [
                'craftKey' => $craftKey,
                'craftTitle' => (string) ($summary['craftTitle'] ?? $craftKey),
                'weekDuration' => $weekDuration,
                'planWeeks' => $planWeeks,
                'priceCalories' => $priceCalories,
                'averageDayCalories' => self::averageDayCaloriesFromSummary($summary),
            ];
        }

        /** @var array<string, mixed>|null $draft */
        $draft = session(ConsultationResumeDraftController::SESSION_DRAFT_KEY);

        if (! is_array($draft) || ! isset($draft['craftKey'], $draft['weekDuration'])) {
            return null;
        }

        $craftKey = (string) $draft['craftKey'];
        $weekDuration = (int) $draft['weekDuration'];
        $planWeeks = $planWeeksOverride ?? (int) ($draft['planWeeks'] ?? 4);
        $planWeeks = $planWeeks === 1 ? 1 : 4;
        $priceCalories = $planTierCalories;

        if ($craftKey !== 'business') {
            $priceCalories = CraftLibraryTierMap::snapToCraftTotal($priceCalories, $craftKey);
        }

        $titles = [
            'full' => 'Full Craft',
            'day' => 'Day Craft',
            'afternoon' => 'Afternoon Craft',
            'intermittent' => 'Intermittent Craft',
            'business' => 'Business Craft',
        ];

        return [
            'craftKey' => $craftKey,
            'craftTitle' => $titles[$craftKey] ?? $craftKey,
            'weekDuration' => $weekDuration,
            'planWeeks' => $planWeeks,
            'priceCalories' => $priceCalories,
            'averageDayCalories' => $planTierCalories,
        ];
    }

    /**
     * @return array{
     *     area: string,
     *     block: string,
     *     road: string,
     *     houseNumber: string,
     *     gateFlatNumber: string,
     *     country: string,
     *     deliveryTime: string|null,
     *     plannedStartDate: string|null,
     *     formatted: string
     * }
     */
    private static function deliveryAddress(CustomerProfile $profile): array
    {
        $area = trim((string) ($profile->area ?? ''));
        $block = trim((string) ($profile->block ?? ''));
        $road = trim((string) ($profile->road ?? ''));
        $houseNumber = trim((string) ($profile->house_number ?? ''));
        $gateFlat = trim((string) ($profile->gate_flat_number ?? ''));
        $country = trim((string) ($profile->country ?: 'Bahrain'));

        $parts = array_values(array_filter([
            $houseNumber !== '' ? 'House '.$houseNumber : null,
            $gateFlat !== '' ? 'Gate/Flat '.$gateFlat : null,
            $road !== '' ? 'Road '.$road : null,
            $block !== '' ? 'Block '.$block : null,
            $area !== '' ? $area : null,
            $country !== '' ? $country : null,
        ]));

        $plannedStart = $profile->planned_start_date instanceof Carbon
            ? $profile->planned_start_date->toDateString()
            : null;

        return [
            'area' => $area,
            'block' => $block,
            'road' => $road,
            'houseNumber' => $houseNumber,
            'gateFlatNumber' => $gateFlat,
            'country' => $country,
            'deliveryTime' => $profile->delivery_time?->value,
            'plannedStartDate' => $plannedStart,
            'formatted' => $parts !== [] ? implode(', ', $parts) : '—',
        ];
    }
}
