<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OnboardingStep;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'is_active', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function isAdmin(): bool
    {
        return $this->role->isStaff();
    }

    public function isCustomer(): bool
    {
        return $this->role->isCustomer();
    }

    public function canAccessCustomerPortal(): bool
    {
        return $this->isCustomer() || $this->isAdmin();
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->customerProfile?->onboarding_completed_at !== null;
    }

    public function currentOnboardingStep(): OnboardingStep
    {
        return $this->customerProfile?->onboarding_step ?? OnboardingStep::Welcome;
    }

    public function homePath(): string
    {
        if ($this->isAdmin()) {
            return route('admin.dashboard', absolute: false);
        }

        if ($this->hasCompletedOnboarding()) {
            return route('app.home', absolute: false);
        }

        return route('onboarding.show', [
            'step' => $this->currentOnboardingStep()->value,
        ], absolute: false);
    }

    public function initials(): string
    {
        $name = trim((string) ($this->name ?? ''));

        if ($name === '') {
            return '?';
        }

        return Str::of($name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $word) => Str::substr($word, 0, 1))
            ->implode('');
    }
}
