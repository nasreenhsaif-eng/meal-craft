<?php

use App\Http\Controllers\Api\ConsultationResumeDraftController;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\PartnerKitchenPrice;
use Database\Seeders\PartnerKitchenPriceSeeder;

test('partner kitchen ticket uses billed days from the consultation draft', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'daily_calorie_target' => 1200,
    ]);

    // Older submitted plan must not override the in-progress draft choice.
    $customer->customerProfile->craftPlans()->create([
        'craft_key' => 'full',
        'week_duration' => 7,
        'plan_weeks' => 4,
        'selected_weekdays' => [1, 2, 3, 4, 5, 6, 7],
        'submitted_at' => now(),
    ]);

    $expectedAmount = PartnerKitchenPrice::amountForBilledDays('full', 1200, 20);

    $this->actingAs($customer)
        ->withSession([
            ConsultationResumeDraftController::SESSION_DRAFT_KEY => [
                'craftKey' => 'full',
                'weekDuration' => 5,
                'planWeeks' => 4,
                'selectedBilledDays' => 20,
                'selectedDays' => [1, 2, 3, 4, 5],
                'fulfillmentChoice' => 'partner_kitchen',
            ],
        ])
        ->get(route('checkout.delivery', ['resume' => 'choose_meals']))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Checkout/Delivery')
            ->where('billedDays', 20)
            ->where('selectedPlanWeeks', 4)
            ->where('selectedQuote', fn ($value) => (float) $value === (float) $expectedAmount)
            ->where('craftTitle', 'Full Craft'));
});
