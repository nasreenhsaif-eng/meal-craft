<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AdminPortalPermission;
use Illuminate\Support\Facades\Hash;

test('craft master general can update an admin portal permissions checklist', function () {
    $cmg = User::factory()->craftMasterGeneral()->create();
    $admin = User::factory()->withAdminPermissions(['ingredient-db'])->create([
        'name' => 'Limited Admin',
        'email' => 'limited-admin@example.com',
    ]);

    $this->actingAs($cmg)
        ->put(route('admin.users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'admin_permissions' => ['ingredient-db', 'customer-profiles', 'pricing'],
        ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('success');

    expect($admin->fresh()->admin_permissions)->toBe([
        'ingredient-db',
        'customer-profiles',
        'pricing',
    ]);
});

test('craft master general can create an admin and assign portal permissions', function () {
    $cmg = User::factory()->craftMasterGeneral()->create();

    $this->actingAs($cmg)
        ->post(route('admin.users.store'), [
            'name' => 'Limited Admin',
            'email' => 'limited-admin@example.com',
            'password' => 'password-12345',
            'password_confirmation' => 'password-12345',
            'admin_permissions' => ['ingredient-db', 'customer-profiles'],
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('success');

    $created = User::query()->where('email', 'limited-admin@example.com')->first();

    expect($created)->not->toBeNull()
        ->and($created->role)->toBe(UserRole::Admin)
        ->and($created->admin_permissions)->toBe(['ingredient-db', 'customer-profiles'])
        ->and(Hash::check('password-12345', $created->password))->toBeTrue();
});

test('admin without pricing cannot open subscription pricing routes', function () {
    $admin = User::factory()->withAdminPermissions(['ingredient-db'])->create();

    $this->actingAs($admin)
        ->get(route('admin.partner-kitchen-pricing'))
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('error');
});

test('admin with pricing can open subscription pricing routes', function () {
    $admin = User::factory()->withAdminPermissions(['pricing'])->create();

    $this->actingAs($admin)
        ->get(route('admin.partner-kitchen-pricing'))
        ->assertSuccessful();
});

test('admin cannot create another admin user', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Nope',
            'email' => 'nope-admin@example.com',
            'password' => 'password-12345',
            'password_confirmation' => 'password-12345',
            'admin_permissions' => AdminPortalPermission::keys(),
        ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('error');

    expect(User::query()->where('email', 'nope-admin@example.com')->exists())->toBeFalse();
});

test('staff cannot access the admin portal', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertRedirect($staff->homePath())
        ->assertSessionHas('error');
});

test('dashboard shares only allowed admin permissions for limited admins', function () {
    $admin = User::factory()->withAdminPermissions(['meal-hub', 'pricing'])->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('auth.user.isCraftMasterGeneral', false)
            ->where('auth.user.adminPermissions', ['meal-hub', 'pricing'])
            ->where('users', []));
});

test('craft master general dashboard lists portal users and full checklist', function () {
    $cmg = User::factory()->craftMasterGeneral()->create([
        'name' => 'Craft Master',
        'email' => 'cmg@example.com',
    ]);
    User::factory()->withAdminPermissions(['pricing'])->create([
        'name' => 'Pricing Admin',
        'email' => 'pricing@example.com',
    ]);

    $this->actingAs($cmg)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('isCraftMasterGeneral', true)
            ->has('users', 2)
            ->has('permissionChecklist', 2)
            ->where('auth.user.adminPermissions', AdminPortalPermission::keys()));
});
