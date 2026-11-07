<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Api\ConsultationResumeDraftController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCheckoutDetailsRequest;
use App\Http\Requests\Customer\StoreCheckoutPaymentRequest;
use App\Http\Requests\Customer\StoreCheckoutPlanWeeksRequest;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerProfile;
use App\Services\CustomerCraftPlanPresentationService;
use App\Services\Nutrition\UserPlanCalculator;
use App\Support\AdminConsultationPreviewProfile;
use App\Support\CheckoutBasket;
use App\Support\CustomerIntake;
use App\Support\PartnerKitchenPrice;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function fulfillment(): Response
    {
        $user = request()->user();

        return Inertia::render('Checkout/FulfillmentSelection', [
            'customerName' => $user?->name ?? '',
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
            'deliveryUrl' => route('checkout.delivery'),
            'recipesUrl' => route('meal-plan.recipes'),
        ]);
    }

    public function delivery(CustomerCraftPlanPresentationService $presentation): Response
    {
        $user = request()->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;

        if (request()->query('resume') === 'choose_meals') {
            request()->session()->put('checkout_resume_choose_meals', true);
            request()->session()->put(ConsultationResumeDraftController::SESSION_RESUME_KEY, true);
        }

        $quotes = $this->deliveryQuotes($profile, $presentation);
        $selectedPlanWeeks = (int) request()->session()->get('checkout_plan_weeks', $quotes['selectedPlanWeeks']);
        $selectedPlanWeeks = $selectedPlanWeeks === 1 ? 1 : 4;
        $selectedQuote = $selectedPlanWeeks === 1 ? $quotes['oneWeek'] : $quotes['fourWeeks'];
        $billedDays = $selectedPlanWeeks === 1
            ? $quotes['billedDaysOneWeek']
            : $quotes['billedDaysFourWeeks'];

        return Inertia::render('Checkout/Delivery', [
            'customerName' => $user?->name ?? '',
            'detailsUrl' => route('checkout.details'),
            'planWeeksUrl' => route('checkout.delivery.plan-weeks'),
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
            'currency' => (string) config('checkout.currency', 'BHD'),
            'selectedPlanWeeks' => $selectedPlanWeeks,
            'selectedQuote' => $selectedQuote,
            'billedDays' => $billedDays,
            'craftTitle' => $quotes['craftTitle'],
        ]);
    }

    public function storePlanWeeks(StoreCheckoutPlanWeeksRequest $request): RedirectResponse
    {
        $planWeeks = (int) $request->validated('plan_weeks');
        $request->session()->put('checkout_plan_weeks', $planWeeks === 1 ? 1 : 4);

        $user = $request->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;
        $plan = $profile?->craftPlans()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        if ($plan instanceof CustomerCraftPlan) {
            $plan->update(['plan_weeks' => $planWeeks === 1 ? 1 : 4]);
        }

        return redirect()->route('checkout.details');
    }

    public function details(): View
    {
        $user = request()->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;

        abort_if($profile === null, 404);

        // Server-rendered HTML — no React/Inertia mount (Chrome blank-page workaround).
        return view('pages.checkout.details', [
            ...CustomerIntake::pageProps($profile),
            'customerName' => $user?->name ?? '',
            'submitUrl' => route('checkout.details.store'),
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
        ]);
    }

    public function storeDetails(StoreCheckoutDetailsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;

        abort_if($profile === null, 404);

        CustomerIntake::applyCheckout($profile, $request->validated());

        // Hard redirect so payment always boots in a fresh document (avoids blank SPA roots).
        return redirect()
            ->away(route('checkout.payment'))
            ->with('success', 'Your details were confirmed.');
    }

    public function payment(CustomerCraftPlanPresentationService $presentation): View|RedirectResponse
    {
        $user = request()->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;

        abort_if($profile === null, 404);

        if ($profile->intake_declaration_accepted_at === null) {
            return redirect()->route('checkout.details');
        }

        $promoCode = request()->query('promo');
        $promoCode = is_string($promoCode) ? $promoCode : null;
        $sessionWeeks = request()->session()->get('checkout_plan_weeks');
        $planWeeksOverride = is_numeric($sessionWeeks) ? ((int) $sessionWeeks === 1 ? 1 : 4) : null;

        // Server-rendered HTML — no React/Inertia mount (Chrome blank-page workaround).
        return view('pages.checkout.payment', [
            'customerName' => $user?->name ?? '',
            'basket' => CheckoutBasket::forProfile($profile, $presentation, $promoCode, $planWeeksOverride),
            'detailsUrl' => route('checkout.details'),
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
            'submitUrl' => route('checkout.payment.store'),
        ]);
    }

    /**
     * @return array{
     *     selectedPlanWeeks: int,
     *     oneWeek: float,
     *     fourWeeks: float,
     *     billedDaysOneWeek: int,
     *     billedDaysFourWeeks: int,
     *     craftTitle: string|null
     * }
     */
    private function deliveryQuotes(
        ?CustomerProfile $profile,
        CustomerCraftPlanPresentationService $presentation,
    ): array {
        $plan = $profile?->craftPlans()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        $planTierCalories = $profile?->daily_calorie_target !== null
            ? (int) UserPlanCalculator::snapToPlanTier((float) $profile->daily_calorie_target)
            : 1200;

        $craftKey = 'full';
        $weekDuration = 7;
        $selectedPlanWeeks = 4;
        $craftTitle = null;
        $priceCalories = $planTierCalories;

        if ($plan instanceof CustomerCraftPlan) {
            $summary = $presentation->presentSummary($plan, $planTierCalories);
            $craftKey = (string) ($summary['craftKey'] ?? $plan->craft_key);
            $weekDuration = (int) ($summary['weekDuration'] ?? $plan->week_duration ?? 7);
            $selectedPlanWeeks = (int) ($plan->plan_weeks ?: 4) === 1 ? 1 : 4;
            $craftTitle = (string) ($summary['craftTitle'] ?? $craftKey);
            $priceCalories = (int) ($summary['planTierCalories'] ?? $planTierCalories);
        } else {
            /** @var array<string, mixed>|null $draft */
            $draft = request()->session()->get(ConsultationResumeDraftController::SESSION_DRAFT_KEY);

            if (is_array($draft) && isset($draft['craftKey'], $draft['weekDuration'])) {
                $craftKey = (string) $draft['craftKey'];
                $weekDuration = (int) $draft['weekDuration'];
                $selectedPlanWeeks = (int) ($draft['planWeeks'] ?? 4) === 1 ? 1 : 4;
                $craftTitle = match ($craftKey) {
                    'afternoon' => 'Afternoon Craft',
                    'day' => 'Day Craft',
                    'intermittent' => 'Intermittent Craft',
                    'business' => 'Business Craft',
                    default => 'Full Craft',
                };
            }
        }

        $priceCalories = PartnerKitchenPrice::priceCaloriesForCraft($priceCalories, $craftKey);

        return [
            'selectedPlanWeeks' => $selectedPlanWeeks,
            'oneWeek' => PartnerKitchenPrice::packageAmount($craftKey, $priceCalories, $weekDuration, 1),
            'fourWeeks' => PartnerKitchenPrice::packageAmount($craftKey, $priceCalories, $weekDuration, 4),
            'billedDaysOneWeek' => PartnerKitchenPrice::billedDays($weekDuration, 1),
            'billedDaysFourWeeks' => PartnerKitchenPrice::billedDays($weekDuration, 4),
            'craftTitle' => $craftTitle,
        ];
    }

    public function storePayment(StoreCheckoutPaymentRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;

        abort_if($profile === null, 404);

        if ($profile->intake_declaration_accepted_at === null) {
            return redirect()->route('checkout.details');
        }

        $isAdminPreview = $user?->isAdmin() === true && $user?->isCustomer() !== true;

        $shouldResumeChooseMeals = $isAdminPreview
            || $request->session()->pull('checkout_resume_choose_meals') === true
            || $request->session()->get(ConsultationResumeDraftController::SESSION_RESUME_KEY) === true
            || $request->session()->has(ConsultationResumeDraftController::SESSION_DRAFT_KEY);

        if ($shouldResumeChooseMeals) {
            $request->session()->put(ConsultationResumeDraftController::SESSION_RESUME_KEY, true);

            return redirect()
                ->route('consultation.crafted-for-you', ['choose_meals' => 1])
                ->with('success', 'Payment recorded. Choose your meals to finish your plan.');
        }

        return redirect()
            ->route('checkout.payment')
            ->with('success', 'Payment instructions recorded. Complete Benefit Pay to finish your order.');
    }

    public function policies(): View
    {
        $user = request()->user();

        return view('pages.checkout.policies', [
            'customerName' => $user?->name ?? '',
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
            'paymentUrl' => route('checkout.payment'),
            'refundPolicyUrl' => route('policies.refund').'#refund',
            'freezePolicyUrl' => route('policies.refund').'#freeze',
        ]);
    }
}
