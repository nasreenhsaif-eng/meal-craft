<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
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
            'homeUrl' => route('app.home'),
        ]);
    }
}
