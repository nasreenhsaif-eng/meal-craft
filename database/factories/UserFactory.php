<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AdminPortalPermission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => UserRole::Admin,
            'admin_permissions' => AdminPortalPermission::keys(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function craftMasterGeneral(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::CraftMasterGeneral,
            'admin_permissions' => null,
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Customer,
            'admin_permissions' => null,
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Staff,
            'admin_permissions' => null,
        ]);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function withAdminPermissions(array $permissions): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
            'admin_permissions' => AdminPortalPermission::sanitize($permissions),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
