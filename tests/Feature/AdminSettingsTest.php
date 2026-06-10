<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin users can access admin settings profile page', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('admin.settings.profile'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Settings/Profile')
            ->where('profile.name', $admin->name)
            ->where('profile.email', $admin->email));
});

test('customers cannot access admin settings', function () {
    $customer = User::factory()->customer()->create();

    $this->actingAs($customer)
        ->get(route('admin.settings.profile'))
        ->assertRedirect($customer->homePath())
        ->assertSessionHas('error');
});

test('admin users are redirected away from legacy settings routes', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('profile.edit'))
        ->assertRedirect(route('admin.settings.profile'));

    $this->actingAs($admin)
        ->get(route('security.edit'))
        ->assertRedirect(route('admin.settings.security'));

    $this->actingAs($admin)
        ->get(route('appearance.edit'))
        ->assertRedirect(route('admin.settings.appearance'));
});

test('admin users can update their profile from admin settings', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.settings.profile.update'), [
            'name' => 'New Name',
            'email' => 'new@example.com',
        ])
        ->assertRedirect(route('admin.settings.profile'))
        ->assertSessionHas('success');

    $admin->refresh();

    expect($admin->name)->toBe('New Name')
        ->and($admin->email)->toBe('new@example.com');
});

test('admin users can update their password from admin settings', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->put(route('admin.settings.security.password'), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect(route('admin.settings.security'))
        ->assertSessionHas('success');

    expect(Hash::check('new-password-123', $admin->fresh()->password))->toBeTrue();
});
