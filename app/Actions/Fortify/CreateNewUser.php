<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use App\Models\CustomerProfile;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered customer account.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['phone'] = PhoneNumber::normalize($input['phone'] ?? null) ?? ($input['phone'] ?? '');

        Validator::make($input, [
            ...$this->profileRules(),
            'phone' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{7,14}$/',
                Rule::unique(CustomerProfile::class, 'phone'),
            ],
            'password' => $this->passwordRules(),
        ], [
            'phone.regex' => __('Enter a valid mobile number, including the country code.'),
            'phone.unique' => __('This mobile number is already registered.'),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => UserRole::Customer,
        ]);

        CustomerProfile::query()->create([
            'user_id' => $user->id,
            'onboarding_step' => OnboardingStep::Gender,
            'phone' => $input['phone'],
        ]);

        return $user;
    }
}
