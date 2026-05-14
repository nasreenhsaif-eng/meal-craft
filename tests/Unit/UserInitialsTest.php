<?php

use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

test('initials returns placeholder when name is empty or missing', function () {
    $user = new User(['name' => null, 'email' => 'a@b.test']);
    expect($user->initials())->toBe('?');

    $user->name = '';
    expect($user->initials())->toBe('?');

    $user->name = '   ';
    expect($user->initials())->toBe('?');
});

test('initials uses up to two name parts', function () {
    $user = new User(['name' => 'Alice Bob', 'email' => 'a@b.test']);
    expect($user->initials())->toBe('AB');

    $user->name = 'Carol';
    expect($user->initials())->toBe('C');
});
