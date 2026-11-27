<?php

test('google search favicon assets exist at stable brand paths', function () {
    expect(file_exists(public_path('brand/meal-craft-icon.png')))->toBeTrue();
    expect(file_exists(public_path('brand/meal-craft-icon-48.png')))->toBeTrue();
    expect(file_exists(public_path('favicon.ico')))->toBeTrue();
    expect(file_exists(public_path('favicon.svg')))->toBeTrue();

    $primary = getimagesize(public_path('brand/meal-craft-icon.png'));
    expect($primary)->not->toBeFalse();
    expect($primary[0])->toBeGreaterThanOrEqual(48);
    expect($primary[1])->toBe($primary[0]);
});

test('home page declares the google-eligible brand favicon', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('/brand/meal-craft-icon.png', false)
        ->assertSee('/brand/meal-craft-icon-48.png', false);
});
