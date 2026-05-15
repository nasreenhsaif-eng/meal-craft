<?php

use App\Support\MealImagePath;
use Tests\TestCase;

uses(TestCase::class);

test('normalize strips public prefix slashes and storage url segment', function () {
    expect(MealImagePath::normalizeForDatabase('/images/meals/stew.jpg'))->toBe('images/meals/stew.jpg')
        ->and(MealImagePath::normalizeForDatabase('public/images/meals/stew.jpg'))->toBe('images/meals/stew.jpg')
        ->and(MealImagePath::normalizeForDatabase('public/public/images/meals/stew.jpg'))->toBe('images/meals/stew.jpg')
        ->and(MealImagePath::normalizeForDatabase('/storage/meals/upload.jpg'))->toBe('meals/upload.jpg')
        ->and(MealImagePath::normalizeForDatabase('meal-craft.test/images/meals/stew.jpg'))->toBe('images/meals/stew.jpg')
        ->and(MealImagePath::normalizeForDatabase('  '))->toBeNull();
});

test('resolve url uses asset for public images directory', function () {
    $url = MealImagePath::resolveUrl('images/meals/placeholder.svg');

    expect($url)->toContain('images/meals/placeholder.svg');
});

test('resolve url returns placeholder when public image file is missing', function () {
    $url = MealImagePath::resolveUrl('images/meals/does-not-exist.jpg');

    expect($url)->toContain('images/meals/placeholder.svg');
});

test('resolve url finds alternate extension under public images meals', function () {
    $pngPath = public_path('images/meals/Chocolate-Orange-Brownie-(N).png');
    if (! is_file($pngPath)) {
        $this->markTestSkipped('Sample meal image not present in public/images/meals.');
    }

    $url = MealImagePath::resolveUrl('images/meals/Chocolate-Orange-Brownie-(N).jpg');

    expect($url)->toContain('Chocolate-Orange-Brownie-(N).png');
});

test('resolve url returns absolute http urls unchanged', function () {
    expect(MealImagePath::resolveUrl('https://cdn.example.com/a.jpg'))->toBe('https://cdn.example.com/a.jpg');
});

test('should delete from public disk is false for web images and remote urls', function () {
    expect(MealImagePath::shouldDeleteFromPublicDisk('images/meals/x.jpg'))->toBeFalse()
        ->and(MealImagePath::shouldDeleteFromPublicDisk('meals/x.jpg'))->toBeTrue()
        ->and(MealImagePath::shouldDeleteFromPublicDisk('https://example.com/x.jpg'))->toBeFalse();
});
