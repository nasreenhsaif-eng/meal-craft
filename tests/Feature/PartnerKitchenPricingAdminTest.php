<?php

use App\Enums\UserRole;
use App\Models\PartnerKitchenPriceCard;
use App\Models\User;
use Database\Seeders\PartnerKitchenPriceSeeder;

test('admins can open partner kitchen pricing and see seeded cards', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.partner-kitchen-pricing'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/PartnerKitchenPricing')
            ->has('cards')
            ->where('currency', 'BHD'));

    expect(PartnerKitchenPriceCard::query()->count())->toBeGreaterThan(20);
});

test('admins can update a price card amount', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $card = PartnerKitchenPriceCard::query()
        ->where('scope', 'shared')
        ->where('calorie_row', 2000)
        ->where('billed_days', 28)
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.partner-kitchen-pricing.update'), [
            'cards' => [
                [
                    'id' => $card->id,
                    'amount_bhd' => 360,
                ],
            ],
            'limited_time_discount' => 20,
            'limited_time_discount_label' => 'Limited time offer',
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'));

    expect((float) $card->fresh()->amount_bhd)->toBe(360.0);
});

test('customers cannot open partner kitchen pricing', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.partner-kitchen-pricing'))
        ->assertRedirect();
});
