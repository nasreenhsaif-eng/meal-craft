<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCheckoutDetailsRequest;
use App\Http\Requests\Customer\StoreCheckoutPaymentRequest;
use App\Services\CustomerCraftPlanPresentationService;
use App\Support\CheckoutBasket;
use App\Support\CustomerIntake;
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
            'deliveryUrl' => route('checkout.delivery'),
            'recipesUrl' => route('meal-plan.recipes'),
        ]);
    }

    public function delivery(): Response
    {
        $user = request()->user();

        return Inertia::render('Checkout/Delivery', [
            'customerName' => $user?->name ?? '',
            'fulfillmentUrl' => route('checkout.fulfillment'),
            'detailsUrl' => route('checkout.details'),
            'homeUrl' => route('app.home'),
        ]);
    }

    public function details(): Response
    {
        $user = request()->user();
        $profile = $user?->customerProfile;

        abort_if($profile === null, 404);

        return Inertia::render('Checkout/CustomerDetails', [
            ...CustomerIntake::pageProps($profile),
            'customerName' => $user?->name ?? '',
            'submitUrl' => route('checkout.details.store'),
            'deliveryUrl' => route('checkout.delivery'),
            'homeUrl' => route('app.home'),
        ]);
    }

    public function storeDetails(StoreCheckoutDetailsRequest $request): RedirectResponse
    {
        $profile = $request->user()?->customerProfile;

        abort_if($profile === null, 404);

        CustomerIntake::applyCheckout($profile, $request->validated());

        return redirect()
            ->route('checkout.payment')
            ->with('success', 'Your details were confirmed.');
    }

    public function payment(CustomerCraftPlanPresentationService $presentation): Response|RedirectResponse
    {
        $user = request()->user();
        $profile = $user?->customerProfile;

        abort_if($profile === null, 404);

        if ($profile->intake_declaration_accepted_at === null) {
            return redirect()->route('checkout.details');
        }

        $promoCode = request()->query('promo');
        $promoCode = is_string($promoCode) ? $promoCode : null;

        return Inertia::render('Checkout/Payment', [
            'customerName' => $user?->name ?? '',
            'basket' => CheckoutBasket::forProfile($profile, $presentation, $promoCode),
            'detailsUrl' => route('checkout.details'),
            'homeUrl' => route('app.home'),
            'submitUrl' => route('checkout.payment.store'),
            'applyPromoUrl' => route('checkout.payment'),
        ]);
    }

    public function storePayment(StoreCheckoutPaymentRequest $request): RedirectResponse
    {
        $profile = $request->user()?->customerProfile;

        abort_if($profile === null, 404);

        if ($profile->intake_declaration_accepted_at === null) {
            return redirect()->route('checkout.details');
        }

        return redirect()
            ->route('checkout.payment')
            ->with('success', 'Payment instructions recorded. Complete Benefit Pay to finish your order.');
    }
}
