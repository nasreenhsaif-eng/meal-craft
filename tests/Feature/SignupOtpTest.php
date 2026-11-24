<?php

use App\Enums\OnboardingStep;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Notifications\SignupVerificationCode;
use App\Support\SignupOtp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('join page mounts the signup form', function () {
    $this->get(route('join'))
        ->assertOk()
        ->assertSee('mc-auth-join-root', false)
        ->assertSee('mc-auth-join-config', false);
});

test('registration requires a valid unique mobile number', function () {
    CustomerProfile::factory()->create([
        'phone' => '+97333001234',
    ]);

    $this->post(route('register.store'), [
        'name' => 'No Phone',
        'email' => 'nophone@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('phone');

    $this->post(route('register.store'), [
        'name' => 'Bad Phone',
        'email' => 'badphone@example.com',
        'phone' => '123',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('phone');

    $this->post(route('register.store'), [
        'name' => 'Taken Phone',
        'email' => 'taken@example.com',
        'phone' => '+97333001234',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('phone');

    $this->assertGuest();
});

test('registration sends one code to email and whatsapp and waits for verification', function () {
    Notification::fake();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test']]]),
    ]);
    config([
        'services.whatsapp.token' => 'test-token',
        'services.whatsapp.phone_number_id' => '12345',
        'services.whatsapp.otp_template' => 'meal_craft_otp',
        'services.whatsapp.send_http' => true,
    ]);

    $response = $this->post(route('register.store'), [
        'name' => 'Jane Customer',
        'email' => 'jane@example.com',
        'phone' => '3300 1234',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('join.verify'));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->customerProfile?->phone)->toBe('+97333001234')
        ->and($user->customerProfile?->phone_verified_at)->toBeNull()
        ->and($user->email_verified_at)->toBeNull();

    $code = null;

    Notification::assertSentTo($user, SignupVerificationCode::class, function (SignupVerificationCode $notification) use (&$code): bool {
        $code = $notification->code;

        return strlen($notification->code) === 6;
    });

    Http::assertSent(function ($request) use ($code): bool {
        return $request->url() === 'https://graph.facebook.com/v21.0/12345/messages'
            && $request['to'] === '97333001234'
            && $request['template']['components'][0]['parameters'][0]['text'] === $code
            && $request['template']['components'][1]['parameters'][0]['text'] === $code;
    });

    $this->get(route('onboarding.show', ['step' => OnboardingStep::Gender->value]))
        ->assertRedirect(route('join.verify'));
});

test('the correct otp continues to onboarding and stamps both verified timestamps', function () {
    Notification::fake();

    $this->post(route('register.store'), signupRegistrationPayload());

    $user = User::query()->where('email', 'otp@example.com')->firstOrFail();
    $code = capturedSignupCode($user);

    $this->post(route('join.verify.store'), ['code' => $code])
        ->assertRedirect(route('onboarding.show', ['step' => OnboardingStep::Gender->value], absolute: false));

    $user->refresh();

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->customerProfile?->phone_verified_at)->not->toBeNull();
});

test('wrong and expired codes are rejected', function () {
    Notification::fake();

    $this->post(route('register.store'), signupRegistrationPayload('wrong@example.com', '33001111'));

    $user = User::query()->where('email', 'wrong@example.com')->firstOrFail();
    $code = capturedSignupCode($user);
    $wrong = $code === '000000' ? '111111' : '000000';

    $this->post(route('join.verify.store'), ['code' => $wrong])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->email_verified_at)->toBeNull();

    SignupOtp::forget($user);

    $this->post(route('join.verify.store'), ['code' => $code])
        ->assertSessionHasErrors('code');
});

test('resend is throttled', function () {
    Notification::fake();

    $this->post(route('register.store'), signupRegistrationPayload('resend@example.com', '33002222'));

    $this->post(route('join.verify.resend'))->assertRedirect();
    $this->post(route('join.verify.resend'))->assertRedirect();
    $this->post(route('join.verify.resend'))->assertRedirect();
    $this->post(route('join.verify.resend'))->assertStatus(429);
});

test('grandfathered customers are not sent to the otp screen', function () {
    $customer = User::factory()->customer()->create();
    CustomerProfile::factory()->for($customer)->withoutOnboarding()->create();

    $this->actingAs($customer)
        ->get(route('onboarding.show', ['step' => OnboardingStep::Gender->value]))
        ->assertOk();
});

/**
 * @return array<string, string>
 */
function signupRegistrationPayload(string $email = 'otp@example.com', string $phone = '33003333'): array
{
    return [
        'name' => 'OTP Customer',
        'email' => $email,
        'phone' => $phone,
        'password' => 'password',
        'password_confirmation' => 'password',
    ];
}

function capturedSignupCode(User $user): string
{
    $code = null;

    Notification::assertSentTo($user, SignupVerificationCode::class, function (SignupVerificationCode $notification) use (&$code): bool {
        $code = $notification->code;

        return strlen($notification->code) === 6;
    });

    expect($code)->toBeString()->not->toBeEmpty();

    return $code;
}
