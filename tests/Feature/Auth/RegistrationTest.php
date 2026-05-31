<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyFeature(Features::registration());
});

test('fortify register page renders the customer join form', function () {
    $this->get('/register')->assertOk();
});

test('join page can be rendered', function () {
    $this->get(route('join'))->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('onboarding.show', ['step' => 'welcome'], absolute: false));

    $this->assertAuthenticated();
});
