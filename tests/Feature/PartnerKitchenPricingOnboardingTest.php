<?php

use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\PartnerKitchenPriceCard;
use App\Models\User;
use App\Support\PartnerKitchenPrice;
use Database\Seeders\PartnerKitchenPriceSeeder;

test('admin price card updates appear on consultation partner kitchen pricing', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'daily_calorie_target' => 1200,
    ]);

    $card = PartnerKitchenPriceCard::query()
        ->where('scope', 'full')
        ->where('calorie_row', 1200)
        ->where('billed_days', 20)
        ->firstOrFail();

    $updatedAmount = 321.5;

    $this->actingAs($admin)
        ->put(route('admin.partner-kitchen-pricing.update'), [
            'cards' => [
                [
                    'id' => $card->id,
                    'amount_bhd' => $updatedAmount,
                    'discount_type' => 'amount',
                    'discount_bhd' => 0,
                ],
            ],
            'limited_time_discount' => 20,
            'limited_time_discount_label' => 'Limited time offer',
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'));

    expect((float) $card->fresh()->amount_bhd)->toBe($updatedAmount)
        ->and(PartnerKitchenPrice::packageAmount('full', 1200, 5, 4))->toBe($updatedAmount);

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
        ->and((float) $twentyDay['amount'])->toBe($updatedAmount);
});

test('admin percent discount updates consultation plan length amounts', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'daily_calorie_target' => 1200,
    ]);

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
            'limited_time_discount' => 20,
            'limited_time_discount_label' => 'Limited time offer',
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
