<?php

use App\Enums\MealType;
use App\Enums\RecipeCategory;
use App\Enums\UserRole;
use App\Models\CustomerCraftPlan;
use App\Models\CustomerProfile;
use App\Models\Meal;
use App\Models\User;
use App\Support\AdminConsultationPreviewProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('craft plan stores fulfillment choice and meal plan summary receives it without delivery cta', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1800,
        'sex' => 'female',
        'activity_level' => 'moderate',
    ]);

    $breakfast = Meal::factory()->create([
        'meal_type' => MealType::Breakfast,
        'category' => RecipeCategory::Breakfast,
        'total_calories' => 250,
        'name' => 'DIY Oats',
    ]);
    $main = Meal::factory()->create([
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 400,
    ]);
    $salad = Meal::factory()->create([
        'meal_type' => MealType::Salad,
        'category' => RecipeCategory::SideSalad,
        'total_calories' => 150,
    ]);
    $dessert = Meal::factory()->create([
        'meal_type' => MealType::Dessert,
        'category' => RecipeCategory::Dessert,
        'total_calories' => 120,
    ]);

    $this->actingAs($user)->postJson('/api/customer/craft-plan', [
        'craft_key' => 'full',
        'fulfillment_choice' => 'diy',
        'week_duration' => 1,
        'selected_days' => [1],
        'days' => [
            [
                'day_of_week' => 1,
                'include_soup' => false,
                'selections' => [
                    'breakfasts' => [$breakfast->id],
                    'meals' => [$main->id],
                    'sideSalads' => [$salad->id],
                    'desserts' => [$dessert->id],
                ],
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('plan.fulfillment_choice', 'diy');

    $this->assertDatabaseHas('customer_craft_plans', [
        'craft_key' => 'full',
        'fulfillment_choice' => 'diy',
    ]);

    $this->actingAs($user)
        ->get(route('app.meal-plan'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/MealPlanSummary')
            ->where('craftPlan.fulfillmentChoice', 'diy')
            ->missing('fulfillmentUrl'));
});

test('partner kitchen fulfillment choice is persisted on the craft plan', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1800,
    ]);

    $main = Meal::factory()->create([
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 400,
    ]);

    $this->actingAs($user)->postJson('/api/customer/craft-plan', [
        'craft_key' => 'day',
        'fulfillment_choice' => 'partner_kitchen',
        'week_duration' => 1,
        'selected_days' => [1],
        'days' => [
            [
                'day_of_week' => 1,
                'include_soup' => false,
                'selections' => [
                    'meals' => [$main->id],
                ],
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('plan.fulfillment_choice', 'partner_kitchen');

    expect(CustomerCraftPlan::query()->latest('id')->value('fulfillment_choice'))->toBe('partner_kitchen');

    $this->actingAs($user)
        ->get(route('app.meal-plan'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('App/MealPlanSummary')
            ->where('craftPlan.fulfillmentChoice', 'partner_kitchen')
            ->missing('fulfillmentUrl'));
});

test('craft plan requires a fulfillment choice', function () {
    $user = User::factory()->create(['role' => UserRole::Customer]);
    CustomerProfile::factory()->for($user)->create([
        'daily_calorie_target' => 1500,
    ]);

    $main = Meal::factory()->create([
        'meal_type' => MealType::Main,
        'category' => RecipeCategory::Meal,
        'total_calories' => 350,
    ]);

    $this->actingAs($user)->postJson('/api/customer/craft-plan', [
        'craft_key' => 'day',
        'week_duration' => 1,
        'selected_days' => [1],
        'days' => [
            [
                'day_of_week' => 1,
                'include_soup' => false,
                'selections' => [
                    'meals' => [$main->id],
                ],
            ],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['fulfillment_choice']);
});

test('partner payment resume still redirects to consultation choose meals', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'intake_declaration_accepted_at' => now(),
        'daily_calorie_target' => 1800,
    ]);

    $this->actingAs($customer)
        ->withSession(['checkout_resume_choose_meals' => true])
        ->post(route('checkout.payment.store'), [
            'cart_confirmed' => true,
            'refund_policy_accepted' => true,
            'payment_method' => 'benefit_pay',
            'invoice_same_address' => true,
        ])
        ->assertRedirect(route('consultation.crafted-for-you', ['choose_meals' => 1]))
        ->assertSessionHas('success')
        ->assertSessionMissing('checkout_resume_choose_meals')
        ->assertSessionHas('consultation_resume_choose_meals', true);
});

test('partner resume draft is stashed in session and returned to consultation', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'intake_declaration_accepted_at' => now(),
        'daily_calorie_target' => 1800,
    ]);

    $draft = [
        'craftKey' => 'full',
        'weekDuration' => 7,
        'selectedDays' => [1, 2, 3, 4, 5, 6, 7],
        'fulfillmentChoice' => 'partner_kitchen',
        'selectedByDay' => [],
        'businessSideChoiceByDay' => [],
        'afternoonSoupOptInByDay' => [],
        'fullCraftSoupOptInByDay' => [],
        'environmentYes' => false,
    ];

    $this->actingAs($customer)
        ->postJson(route('api.customer.consultation-resume-draft.store'), $draft)
        ->assertSuccessful();

    $this->assertTrue(session()->get('consultation_resume_choose_meals'));
    $this->assertSame('full', session()->get('consultation_resume_draft.craftKey'));

    $this->actingAs($customer)
        ->get(route('consultation.crafted-for-you', ['choose_meals' => 1]))
        ->assertSuccessful()
        ->assertViewHas('consultationConfig', function (array $config) use ($draft): bool {
            return ($config['resumeChooseMeals'] ?? false) === true
                && ($config['resumeDraft']['craftKey'] ?? null) === $draft['craftKey']
                && ($config['resumeDraft']['weekDuration'] ?? null) === $draft['weekDuration']
                && ($config['resumeDraft']['fulfillmentChoice'] ?? null) === 'partner_kitchen';
        });
});

test('admin payment always resumes consultation choose meals', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $profile = AdminConsultationPreviewProfile::ensure($admin);
    $profile->forceFill([
        'intake_declaration_accepted_at' => now(),
    ])->save();

    $this->actingAs($admin)
        ->post(route('checkout.payment.store'), [
            'cart_confirmed' => true,
            'refund_policy_accepted' => true,
            'payment_method' => 'benefit_pay',
            'invoice_same_address' => true,
        ])
        ->assertRedirect(route('consultation.crafted-for-you', ['choose_meals' => 1]))
        ->assertSessionHas('success')
        ->assertSessionHas('consultation_resume_choose_meals', true);
});

test('partner payment resumes when only consultation resume draft session exists', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->create([
        'intake_declaration_accepted_at' => now(),
        'daily_calorie_target' => 1800,
    ]);

    $this->actingAs($customer)
        ->withSession([
            'consultation_resume_draft' => [
                'craftKey' => 'full',
                'weekDuration' => 7,
                'selectedDays' => [1, 2, 3, 4, 5, 6, 7],
                'fulfillmentChoice' => 'partner_kitchen',
            ],
            'consultation_resume_choose_meals' => true,
        ])
        ->post(route('checkout.payment.store'), [
            'cart_confirmed' => true,
            'refund_policy_accepted' => true,
            'payment_method' => 'benefit_pay',
            'invoice_same_address' => true,
        ])
        ->assertRedirect(route('consultation.crafted-for-you', ['choose_meals' => 1]))
        ->assertSessionHas('consultation_resume_choose_meals', true)
        ->assertSessionHas('consultation_resume_draft.craftKey', 'full');
});
