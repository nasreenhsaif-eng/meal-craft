<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('admin can toggle another users active flag', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $other = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->from(route('admin.dashboard'))
        ->post(route('admin.users.toggle-active', $other))
        ->assertRedirect(route('admin.dashboard'));

    expect($other->fresh()->is_active)->toBeFalse();
});

test('admin cannot toggle their own active flag from the dashboard action', function () {
    $admin = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->from(route('admin.dashboard'))
        ->post(route('admin.users.toggle-active', $admin))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('error');

    expect($admin->fresh()->is_active)->toBeTrue();
});

test('admin can request a password reset link for another user', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.dashboard'))
        ->post(route('admin.users.password-reset', $other))
        ->assertRedirect(route('admin.dashboard'));

    Notification::assertSentTo($other, ResetPassword::class);
});
