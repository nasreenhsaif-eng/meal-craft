<?php

use App\Models\User;

test('guests cannot visit the consultation crafted for you page', function () {
    $this->get(route('consultation.crafted-for-you'))
        ->assertRedirect(route('login'));
});

test('authenticated customers can visit the consultation crafted for you page', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($user)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk()
        ->assertSee('mc-consultation-crafted-root', false);
});

test('admin users can preview the customer consultation page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('consultation.crafted-for-you'))
        ->assertOk()
        ->assertSee('mc-consultation-crafted-root', false);
});
