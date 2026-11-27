<?php

use App\Models\CustomerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('customer profiles receive a unique code on create', function () {
    $first = CustomerProfile::factory()->create();
    $second = CustomerProfile::factory()->create();

    expect($first->unique_code)->toMatch('/^MC-[A-Z0-9]{8}$/')
        ->and($second->unique_code)->toMatch('/^MC-[A-Z0-9]{8}$/')
        ->and($second->unique_code)->not->toBe($first->unique_code);
});
