<?php

use App\Enums\UserRole;
use App\Models\PlanCaloriePrice;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PartnerKitchenPrice;
use Database\Seeders\PartnerKitchenPriceSeeder;

test('admins can open partner kitchen pricing and see seeded plans', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)
        ->get(route('admin.partner-kitchen-pricing'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/PartnerKitchenPricing')
            ->has('plans', 5)
            ->has('prices')
            ->where('currency', 'BHD'));

    expect(PlanCaloriePrice::query()->count())->toBeGreaterThan(20);

    $keys = collect($response->inertiaProps('plans'))
        ->pluck('key')
        ->values()
        ->all();

    expect($keys)->toBe(PartnerKitchenPrice::CRAFT_DISPLAY_ORDER);
});

test('admins can update a package base price', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $price = PlanCaloriePrice::query()
        ->where('calorie_tier', 2000)
        ->where('days_count', 28)
        ->whereHas('plan', fn ($query) => $query->where('key', 'full'))
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.partner-kitchen-pricing.update'), [
            'prices' => [
                [
                    'id' => $price->id,
                    'base_total_price' => 360,
                    'discounted_total_price' => 345,
                ],
            ],
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'));

    $fresh = $price->fresh();

    expect($fresh->base_total_price)->toBe(360)
        ->and($fresh->discounted_total_price)->toBe(345)
        ->and($fresh->effectiveTotalPrice())->toBe(345)
        ->and($fresh->daily_rate)->toBe((int) round(345 / 28));
});

test('admins can apply a percent discount to a calorie tier', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->post(route('admin.partner-kitchen-pricing.discounts.apply'), [
            'discount_type' => 'percent',
            'discount_value' => 10,
            'plan_keys' => ['full'],
            'calorie_tiers' => [1200],
            'days_counts' => [20],
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'));

    $price = PlanCaloriePrice::query()
        ->where('calorie_tier', 1200)
        ->where('days_count', 20)
        ->whereHas('plan', fn ($query) => $query->where('key', 'full'))
        ->firstOrFail();

    expect($price->base_total_price)->toBe(200)
        ->and($price->discounted_total_price)->toBe(180)
        ->and($price->effectiveTotalPrice())->toBe(180)
        ->and(PartnerKitchenPrice::packageAmount('full', 1200, 5, 4))->toBe(180.0);
});

test('admins can reset package discounts', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $price = PlanCaloriePrice::query()
        ->where('calorie_tier', 1200)
        ->where('days_count', 20)
        ->whereHas('plan', fn ($query) => $query->where('key', 'full'))
        ->firstOrFail();
    $price->applyDiscount('amount', 15);

    $this->actingAs($admin)
        ->post(route('admin.partner-kitchen-pricing.discounts.reset'), [
            'apply_all' => true,
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'));

    expect($price->fresh()->discounted_total_price)->toBeNull()
        ->and($price->fresh()->effectiveTotalPrice())->toBe(200);
});

test('twenty four day packages are featured', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    expect(PlanCaloriePrice::query()->where('days_count', 24)->where('is_featured', false)->exists())->toBeFalse()
        ->and(PlanCaloriePrice::query()->where('days_count', 20)->where('is_featured', true)->exists())->toBeFalse();
});

test('customers cannot open partner kitchen pricing', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.partner-kitchen-pricing'))
        ->assertRedirect();
});

test('seeded plans only include their eligible calorie tiers', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $full = SubscriptionPlan::query()->where('key', 'full')->firstOrFail();
    $business = SubscriptionPlan::query()->where('key', 'business')->firstOrFail();
    $day = SubscriptionPlan::query()->where('key', 'day')->firstOrFail();

    expect($full->calorieTiers())->toBe([1200, 1300, 1400, 1500, 1800, 2000])
        ->and($business->calorieTiers())->toBe([650])
        ->and($day->calorieTiers())->toBe([850, 950, 1200, 1300, 1400, 1500])
        ->and($full->prices()->where('calorie_tier', 850)->exists())->toBeFalse();
});
