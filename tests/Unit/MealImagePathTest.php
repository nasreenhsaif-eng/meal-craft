<?php

use App\Support\MealImagePath;
use Tests\TestCase;

uses(TestCase::class);

test('normalize strips public prefix slashes and storage url segment', function () {
    expect(MealImagePath::normalizeForDatabase('/images/meals/stew.jpg'))->toBe('images/meals/stew.jpg')
        ->and(MealImagePath::normalizeForDatabase('public/images/meals/stew.jpg'))->toBe('images/meals/stew.jpg')
        ->and(MealImagePath::normalizeForDatabase('public/public/images/meals/stew.jpg'))->toBe('images/meals/stew.jpg')
        ->and(MealImagePath::normalizeForDatabase('/storage/meals/upload.jpg'))->toBe('meals/upload.jpg')
        ->and(MealImagePath::normalizeForDatabase('  '))->toBeNull();
});

test('resolve url uses asset for public images directory', function () {
    $url = MealImagePath::resolveUrl('images/meals/stew.jpg');
    expect($url)->toContain('images/meals/stew.jpg');
});

test('resolve url returns absolute http urls unchanged', function () {
    expect(MealImagePath::resolveUrl('https://cdn.example.com/a.jpg'))->toBe('https://cdn.example.com/a.jpg');
});

test('should delete from public disk is false for web images and remote urls', function () {
    expect(MealImagePath::shouldDeleteFromPublicDisk('images/meals/x.jpg'))->toBeFalse()
        ->and(MealImagePath::shouldDeleteFromPublicDisk('meals/x.jpg'))->toBeTrue()
        ->and(MealImagePath::shouldDeleteFromPublicDisk('https://example.com/x.jpg'))->toBeFalse();
});
