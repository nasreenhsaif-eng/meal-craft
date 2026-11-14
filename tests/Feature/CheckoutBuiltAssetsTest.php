<?php

use App\Models\CustomerProfile;
use App\Models\User;

test('checkout pages load as inertia when vite hot file exists', function () {
    $hot = public_path('hot');
    $hadHot = file_exists($hot);
    $backup = public_path('hot.checkout-built-assets-test.bak');

    if ($hadHot) {
        rename($hot, $backup);
    }

    file_put_contents($hot, 'http://127.0.0.1:5173');

    try {
        $customer = User::factory()->customer()->create();
        CustomerProfile::factory()->for($customer)->create([
            'intake_declaration_accepted_at' => now(),
        ]);

        $this->actingAs($customer)
            ->get(route('checkout.payment'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Checkout/Payment')
                ->etc());

        $this->actingAs($customer)
            ->get(route('checkout.details'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Checkout/CustomerDetails')
                ->etc());
    } finally {
        if (file_exists($hot)) {
            unlink($hot);
        }

        if (file_exists($backup)) {
            rename($backup, $hot);
        }
    }
});
