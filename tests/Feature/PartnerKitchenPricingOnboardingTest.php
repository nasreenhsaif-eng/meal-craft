<?php

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\PlanCaloriePrice;
use App\Models\User;
use App\Support\PartnerKitchenPrice;
use Database\Seeders\PartnerKitchenPriceSeeder;

test('admin price updates appear on consultation partner kitchen pricing', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'daily_calorie_target' => 1200,
    ]);

    $price = PlanCaloriePrice::query()
        ->where('calorie_tier', 1200)
        ->where('days_count', 20)
        ->whereHas('plan', fn ($query) => $query->where('key', 'full'))
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.partner-kitchen-pricing.update'), [
            'prices' => [
                [
                    'id' => $price->id,
                    'base_total_price' => 321,
                    'discounted_total_price' => null,
                ],
            ],
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'));

    expect($price->fresh()->base_total_price)->toBe(321)
        ->and(PartnerKitchenPrice::packageAmount('full', 1200, 5, 4))->toBe(321.0);

    $response = $this->actingAs($customer)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk();

    preg_match(
        '/id="mc-consultation-crafted-config" type="application\/json">(.*?)<\/script>/s',
        $response->getContent(),
        $matches,
    );

    $config = json_decode(html_entity_decode($matches[1] ?? '{}', ENT_QUOTES), true);
    $options = $config['partnerKitchenPricing']['planLengthOptions']['full'] ?? [];
    $twentyDay = collect($options)->firstWhere('billedDays', 20);

    expect($twentyDay)->not->toBeNull()
        ->and((float) $twentyDay['amount'])->toBe(321.0);
});

test('admin package discount updates consultation plan length amounts', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'daily_calorie_target' => 1200,
    ]);

    $price = PlanCaloriePrice::query()
        ->where('calorie_tier', 1200)
        ->where('days_count', 20)
        ->whereHas('plan', fn ($query) => $query->where('key', 'full'))
        ->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.partner-kitchen-pricing.update'), [
            'prices' => [
                [
                    'id' => $price->id,
                    'base_total_price' => 200,
                    'discounted_total_price' => 180,
                ],
            ],
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'));

    $response = $this->actingAs($customer)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk();

    preg_match(
        '/id="mc-consultation-crafted-config" type="application\/json">(.*?)<\/script>/s',
        $response->getContent(),
        $matches,
    );

    $config = json_decode(html_entity_decode($matches[1] ?? '{}', ENT_QUOTES), true);
    $options = $config['partnerKitchenPricing']['planLengthOptions']['full'] ?? [];
    $twentyDay = collect($options)->firstWhere('billedDays', 20);

    expect($twentyDay)->not->toBeNull()
        ->and((float) $twentyDay['amount'])->toBe(180.0);
});
