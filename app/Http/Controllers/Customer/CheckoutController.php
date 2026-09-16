<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCheckoutDetailsRequest;
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

        CustomerIntake::apply($profile, $request->validated(), assignSubmissionId: true);

        return redirect()
            ->route('checkout.details')
            ->with('success', 'Your details were saved.');
    }
}
