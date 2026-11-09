<?php

use App\Enums\UserRole;
use App\Models\PartnerKitchenPriceCard;
use App\Models\User;
use App\Support\PartnerKitchenPrice;
use Database\Seeders\PartnerKitchenPriceSeeder;

test('admins can open partner kitchen pricing and see seeded cards', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)
        ->get(route('admin.partner-kitchen-pricing'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/PartnerKitchenPricing')
            ->has('cards')
            ->where('currency', 'BHD'));

    expect(PartnerKitchenPriceCard::query()->count())->toBeGreaterThan(20);

    $scopes = collect($response->inertiaProps('cards'))
        ->pluck('scope')
        ->unique()
        ->values()
        ->all();

    expect($scopes)->toBe(PartnerKitchenPrice::CRAFT_DISPLAY_ORDER);
});

test('admins can update a price card amount', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $card = PartnerKitchenPriceCard::query()
        ->where('scope', 'full')
        ->where('calorie_row', 2000)
        ->where('billed_days', 28)
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.partner-kitchen-pricing.update'), [
            'cards' => [
                [
                    'id' => $card->id,
                    'amount_bhd' => 360,
                    'discount_type' => 'amount',
                    'discount_bhd' => 15,
                ],
            ],
            'limited_time_discount' => 20,
            'limited_time_discount_label' => 'Limited time offer',
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'));

    $fresh = $card->fresh();

    expect((float) $fresh->amount_bhd)->toBe(360.0)
        ->and($fresh->discount_type)->toBe('amount')
        ->and((float) $fresh->discount_bhd)->toBe(15.0)
        ->and($fresh->netAmountBhd())->toBe(345.0);
});

test('admins can apply a percent discount on a price card', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $card = PartnerKitchenPriceCard::query()
        ->where('scope', 'full')
        ->where('calorie_row', 1200)
        ->where('billed_days', 20)
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.partner-kitchen-pricing.update'), [
            'cards' => [
                [
                    'id' => $card->id,
                    'amount_bhd' => 200,
                    'discount_type' => 'percent',
                    'discount_bhd' => 10,
                ],
            ],
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'));

    $fresh = $card->fresh();

    expect($fresh->discount_type)->toBe('percent')
        ->and((float) $fresh->discount_bhd)->toBe(10.0)
        ->and($fresh->netAmountBhd())->toBe(180.0);
});

test('customers cannot open partner kitchen pricing', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.partner-kitchen-pricing'))
        ->assertRedirect();
});
