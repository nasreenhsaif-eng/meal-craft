<?php

use App\Models\CustomerProfile;
use App\Models\User;

test('guests cannot visit the consultation crafted for you page', function () {
    $this->get(route('consultation.crafted-for-you'))
        ->assertRedirect(route('login'));
});

test('authenticated customers can visit the consultation crafted for you page', function () {
    $user = User::factory()->customer()->create();
    CustomerProfile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk()
        ->assertSee('mc-consultation-crafted-root', false)
        ->assertSee('/app', false)
        ->assertSee('Your plan', false);
});

test('admin users can preview the customer consultation page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk()
        ->assertSee('mc-consultation-crafted-root', false);
});
