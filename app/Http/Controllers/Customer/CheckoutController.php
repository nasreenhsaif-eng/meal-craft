<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCheckoutDetailsRequest;
use App\Http\Requests\Customer\StoreCheckoutPaymentRequest;
use App\Services\CustomerCraftPlanPresentationService;
use App\Support\CheckoutBasket;
use App\Support\CustomerIntake;
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

    public function delivery(): Response
    {
        $user = request()->user();

        if (request()->query('resume') === 'choose_meals') {
            request()->session()->put('checkout_resume_choose_meals', true);
        }

        return Inertia::render('Checkout/Delivery', [
            'customerName' => $user?->name ?? '',
            'detailsUrl' => route('checkout.details'),
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
        ]);
    }

    public function details(): View
    {
        $user = request()->user();
        $profile = $user?->customerProfile;

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
        $profile = $request->user()?->customerProfile;

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
        $profile = $user?->customerProfile;

        abort_if($profile === null, 404);

        if ($profile->intake_declaration_accepted_at === null) {
            return redirect()->route('checkout.details');
        }

        $promoCode = request()->query('promo');
        $promoCode = is_string($promoCode) ? $promoCode : null;

        // Server-rendered HTML — no React/Inertia mount (Chrome blank-page workaround).
        return view('pages.checkout.payment', [
            'customerName' => $user?->name ?? '',
            'basket' => CheckoutBasket::forProfile($profile, $presentation, $promoCode),
            'detailsUrl' => route('checkout.details'),
            'homeUrl' => route('app.home'),
            'profileUrl' => route('app.profile'),
            'shopUrl' => route('app.shop'),
            'mealPlanSummaryUrl' => route('app.meal-plan'),
            'submitUrl' => route('checkout.payment.store'),
        ]);
    }

    public function storePayment(StoreCheckoutPaymentRequest $request): RedirectResponse
    {
        $profile = $request->user()?->customerProfile;

        abort_if($profile === null, 404);

        if ($profile->intake_declaration_accepted_at === null) {
            return redirect()->route('checkout.details');
        }

        if ($request->session()->pull('checkout_resume_choose_meals') === true) {
            return redirect()
                ->away(route('consultation.crafted-for-you', ['choose_meals' => 1]))
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
