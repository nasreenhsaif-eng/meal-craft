<?php

namespace App\Http\Controllers\Customer;

use App\Enums\OnboardingStep;
use App\Http\Controllers\Api\ConsultationResumeDraftController;
use App\Http\Controllers\Controller;
use App\Models\Meal;
use App\Services\Nutrition\CraftCaloriePlanner;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\AdminConsultationPreviewProfile;
use App\Support\PartnerKitchenPrice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ConsultationCraftedForYouController extends Controller
{
    public function __invoke(Request $request): View|Response
    {
        // Soft Inertia visits must full-reload into this Blade page.
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        $user = $request->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;
        $isCustomer = $user?->isCustomer() === true;
        $isAdminPreview = $user?->isAdmin() === true && ! $isCustomer;

        $editDraft = null;
        $resumeDraft = null;

        if ($request->query('edit') === '1' && $request->session()->has('consultation_edit_draft')) {
            /** @var array<string, mixed> $editDraft */
            $editDraft = $request->session()->pull('consultation_edit_draft');
        }

        // Home → Choose your meals → Receive: start a fresh Partner/DIY path (ignore payment resume).
        $forceReceive = $request->boolean('receive');
        $fulfillmentQuery = $request->query('fulfillment');
        $initialFulfillmentChoice = in_array($fulfillmentQuery, ['diy', 'partner_kitchen'], true)
            ? $fulfillmentQuery
            : null;

        if ($forceReceive) {
            $request->session()->forget(ConsultationResumeDraftController::SESSION_RESUME_KEY);
        }

        $resumeChooseMeals = ! $forceReceive && (
            $request->boolean('choose_meals')
            || $request->session()->get(ConsultationResumeDraftController::SESSION_RESUME_KEY) === true
        );

        if ($resumeChooseMeals && $request->session()->has(ConsultationResumeDraftController::SESSION_DRAFT_KEY)) {
            /** @var array<string, mixed> $resumeDraft */
            $resumeDraft = $request->session()->get(ConsultationResumeDraftController::SESSION_DRAFT_KEY);
            $request->session()->put(ConsultationResumeDraftController::SESSION_RESUME_KEY, true);
        }

        // Same customer-experience exits for staff and customers (profile state, not role).
        $customerAppHome = route('app.home', absolute: false);
        $customerOnboardingBack = route('onboarding.show', [
            'step' => OnboardingStep::FoodFilters->value,
        ], absolute: false);
        $customerLanding = $user?->shouldLandOnWelcomeBack()
            ? $customerAppHome
            : $customerOnboardingBack;

        $consultationConfig = [
            'backHref' => $customerLanding,
            'closeHref' => $customerLanding,
            'homeHref' => $customerLanding,
            'summaryHref' => route('app.meal-plan', absolute: false),
            'deliveryUrl' => route('checkout.delivery', absolute: false),
            'loginUrl' => route('login'),
            'signOutUrl' => route('sign-out'),
            'csrfToken' => csrf_token(),
            'isCustomerAccount' => $isCustomer,
            'isAdminPreview' => $isAdminPreview,
            'pageEyebrow' => 'Your plan',
            'adaptedMenuUrl' => route('api.menu.adapted', absolute: false),
            'mealDetailViewUrlTemplate' => '/api/meals/{id}/detail-view',
            'mealLibraryRevision' => Meal::libraryRevisionTimestamp(),
            'planTiers' => UserPlanCalculator::planTiers(),
            'planTier' => $profile?->daily_calorie_target !== null
                ? (int) UserPlanCalculator::snapToPlanTier((float) $profile->daily_calorie_target)
                : null,
            'editDraft' => $editDraft,
            'resumeDraft' => $resumeDraft,
            'dietProtocol' => $profile?->diet_protocol ?? 'balanced',
            'sex' => $profile?->sex?->value,
            'activityLevel' => $profile?->activity_level?->value,
            'dailyCalorieTarget' => $profile?->daily_calorie_target !== null
                ? (int) $profile->daily_calorie_target
                : null,
            'resumeChooseMeals' => $resumeChooseMeals,
            'initialFulfillmentChoice' => $initialFulfillmentChoice,
            'forceReceive' => $forceReceive,
            'focusCutleryOnly' => $request->query('focus') === 'cutlery' && $editDraft !== null,
            'initialCraftKey' => $this->resolveInitialCraftKey($request),
            'cycleSyncMealPlanPayload' => $this->resolveCycleSyncMealPlanPayload($request),
        ];

        $baseCalories = (int) (
            $consultationConfig['dailyCalorieTarget']
            ?? $consultationConfig['planTier']
            ?? 1200
        );
        $consultationConfig['partnerKitchenPricing'] = $this->partnerKitchenPricingPayload($baseCalories);

        return view('pages.consultation.crafted-for-you', [
            'consultationConfig' => $consultationConfig,
        ]);
    }

    private function resolveInitialCraftKey(Request $request): ?string
    {
        $craftKey = $request->query('craft_key');

        if (! is_string($craftKey) || $craftKey === '') {
            return null;
        }

        return in_array($craftKey, CraftCaloriePlanner::keys(), true) ? $craftKey : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCycleSyncMealPlanPayload(Request $request): ?array
    {
        $protocolMode = $request->query('protocol_mode');
        $phaseKey = $request->query('phase_key');

        if (! is_string($protocolMode) || ! is_string($phaseKey) || $protocolMode === '' || $phaseKey === '') {
            return null;
        }

        $schedulesFull = $request->boolean('schedules_full_daily_meals', $protocolMode === 'non_fasting');

        return [
            'protocol_mode' => $protocolMode,
            'phase_key' => $phaseKey,
            'biological_phase' => $request->query('biological_phase'),
            'food_approach' => $request->query('food_approach'),
            'fasting_window' => $request->query('fasting_window'),
            'fasting_type' => $request->query('fasting_type'),
            'schedules_full_daily_meals' => $schedulesFull,
            'craft_key' => $this->resolveInitialCraftKey($request)
                ?? ($schedulesFull ? CraftCaloriePlanner::CRAFT_FULL : CraftCaloriePlanner::CRAFT_INTERMITTENT),
        ];
    }

    /**
     * @return array{
     *     dailyRates: array<string, float>,
     *     dailyRateLabels: array<string, string>,
     *     planLengthOptions: array<string, list<array{billedDays: int, planWeeks: int, weekDuration: int, amount: float, label: string}>>
     * }
     */
    private function partnerKitchenPricingPayload(int $baseCalories): array
    {
        $dailyRates = [];
        $dailyRateLabels = [];
        $planLengthOptions = [];

        foreach (['full', 'afternoon', 'day', 'intermittent', 'business'] as $craftKey) {
            $craftCalories = PartnerKitchenPrice::priceCaloriesForCraft($baseCalories, $craftKey);

            $dailyRate = PartnerKitchenPrice::dailyRateForCraft($craftKey, $craftCalories);
            $dailyRates[$craftKey] = $dailyRate;
            $dailyRateLabels[$craftKey] = PartnerKitchenPrice::formatMoney($dailyRate, 1).' / day';
            $planLengthOptions[$craftKey] = PartnerKitchenPrice::planLengthOptions($craftKey, $craftCalories);
        }

        return [
            'dailyRates' => $dailyRates,
            'dailyRateLabels' => $dailyRateLabels,
            'planLengthOptions' => $planLengthOptions,
        ];
    }
}
