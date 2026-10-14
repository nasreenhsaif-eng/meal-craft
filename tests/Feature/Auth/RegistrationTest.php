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
        'phone' => '+97333004444',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('join.verify'));

    $this->assertAuthenticated();
});
