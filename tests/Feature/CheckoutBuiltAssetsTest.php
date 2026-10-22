<?php

use App\Models\CustomerProfile;
use App\Models\User;

test('checkout pages load built assets even when vite hot file exists', function () {
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

        $paymentHtml = $this->actingAs($customer)
            ->get(route('checkout.payment'))
            ->assertSuccessful()
            ->getContent();

        expect($paymentHtml)
            ->toContain('Payment checkout')
            ->and($paymentHtml)->toContain('data-customer-onboarding-frame')
            ->and($paymentHtml)->toContain('/build/')
            ->and($paymentHtml)->not->toContain(':5173')
            ->and($paymentHtml)->not->toContain('@vite/client');

        $detailsHtml = $this->actingAs($customer)
            ->get(route('checkout.details'))
            ->assertSuccessful()
            ->getContent();

        expect($detailsHtml)
            ->toContain('data-customer-onboarding-frame')
            ->and($detailsHtml)->toContain('/build/')
            ->and($detailsHtml)->not->toContain(':5173')
            ->and($detailsHtml)->not->toContain('@vite/client')
            ->and($detailsHtml)->not->toContain('admin-inertia');
    } finally {
        if (file_exists($hot)) {
            unlink($hot);
        }

        if (file_exists($backup)) {
            rename($backup, $hot);
        }
    }
});
