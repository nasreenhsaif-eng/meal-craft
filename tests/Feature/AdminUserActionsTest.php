<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('craft master general can toggle another users active flag', function () {
    $cmg = User::factory()->craftMasterGeneral()->create(['is_active' => true]);
    $other = User::factory()->create(['is_active' => true]);

    $this->actingAs($cmg)
        ->from(route('admin.dashboard'))
        ->post(route('admin.users.toggle-active', $other))
        ->assertRedirect(route('admin.dashboard'));

    expect($other->fresh()->is_active)->toBeFalse();
});

test('admin cannot toggle another users active flag', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $other = User::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->from(route('admin.dashboard'))
        ->post(route('admin.users.toggle-active', $other))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('error');

    expect($other->fresh()->is_active)->toBeTrue();
});

test('craft master general cannot toggle their own active flag from the dashboard action', function () {
    $cmg = User::factory()->craftMasterGeneral()->create(['is_active' => true]);

    $this->actingAs($cmg)
        ->from(route('admin.dashboard'))
        ->post(route('admin.users.toggle-active', $cmg))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('error');

    expect($cmg->fresh()->is_active)->toBeTrue();
});

test('craft master general can request a password reset link for another user', function () {
    Notification::fake();

    $cmg = User::factory()->craftMasterGeneral()->create();
    $other = User::factory()->create();

    $this->actingAs($cmg)
        ->from(route('admin.dashboard'))
        ->post(route('admin.users.password-reset', $other))
        ->assertRedirect(route('admin.dashboard'));

    Notification::assertSentTo($other, ResetPassword::class);
});
