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

test('normalize prefixes bare image filenames with images meals directory', function () {
    expect(MealImagePath::normalizeForDatabase('Tamarind-Honey-&-Sesame-Chicken-w-Garlicky-Green-Beans.png'))
        ->toBe('images/meals/Tamarind-Honey-&-Sesame-Chicken-w-Garlicky-Green-Beans.png');
});

test('normalize converts same app http urls to relative images path', function () {
    expect(MealImagePath::normalizeForDatabase('http://meal-craft.test/images/meals/stew.png'))
        ->toBe('images/meals/stew.png')
        ->and(MealImagePath::normalizeForDatabase('https://example.com/cdn/photo.jpg'))
        ->toBe('https://example.com/cdn/photo.jpg');
});

test('normalize strips markdown image links from csv cells', function () {
    $markdown = '[http://meal-craft.test/images/meals/stew.png](https://www.google.com/search?q=test)';

    expect(MealImagePath::normalizeForDatabase($markdown))->toBe('images/meals/stew.png');
});

test('resolve url uses asset for public images directory', function () {
    $url = MealImagePath::resolveUrl('images/meals/placeholder.svg');

    expect($url)->toContain('images/meals/placeholder.svg');
});

test('resolve url percent encodes ampersands in filenames', function () {
    $path = 'images/meals/Vegan-Smoky-Cauliflower-&-Lentil-Stew-w-Quinoa-Bread-&-Tahini.png';
    if (! is_file(public_path($path))) {
        $this->markTestSkipped('Sample meal image not present in public/images/meals.');
    }

    $url = MealImagePath::resolveUrl($path);

    expect($url)->toContain('%26')
        ->and($url)->toContain('Vegan-Smoky-Cauliflower-%26-Lentil-Stew-w-Quinoa-Bread-%26-Tahini.png');
});

test('resolve url returns intended asset path when public image file is missing', function () {
    $url = MealImagePath::resolveUrl('images/meals/does-not-exist.jpg');

    expect($url)->toContain('does-not-exist.jpg')
        ->and($url)->not->toContain('placeholder.svg');
});

test('resolve url discovers image from meal title when stored path is wrong', function () {
    $path = public_path('images/meals/Vegan-Smoky-Cauliflower-&-Lentil-Stew-w-Quinoa-Bread-&-Tahini.png');
    if (! is_file($path)) {
        $this->markTestSkipped('Sample vegan stew image not present.');
    }

    $url = MealImagePath::resolveUrl(
        'images/meals/Vegan-Smoky-Cauliflower-&-Lentil-Stew-w-Tahini.png',
        'Vegan Smoky Cauliflower & Lentil Stew w Quinoa Bread & Tahini',
    );

    expect($url)->toContain('Quinoa-Bread')
        ->and($url)->toContain('%26');
});

test('resolve url discovers image from meal title when only bare filename stored', function () {
    $path = public_path('images/meals/Tamarind-Honey-&-Sesame-Chicken-w-Garlicky-Green-Beans.png');
    if (! is_file($path)) {
        $this->markTestSkipped('Sample tamarind image not present.');
    }

    $url = MealImagePath::resolveUrl(
        'Tamarind-Honey-&-Sesame-Chicken-w-Garlicky-Green-Beans.png',
        'Tamarind Honey & Sesame Chicken w Garlicky Green Beans',
    );

    expect($url)->toContain('Tamarind-Honey')
        ->and($url)->toContain('%26');
});

test('resolve url finds alternate extension under public images meals', function () {
    $pngPath = public_path('images/meals/Chocolate-Orange-Brownie-(N).png');
    if (! is_file($pngPath)) {
        $this->markTestSkipped('Sample meal image not present in public/images/meals.');
    }

    $url = MealImagePath::resolveUrl('images/meals/Chocolate-Orange-Brownie-(N).jpg');

    expect($url)->toContain('Chocolate-Orange-Brownie-%28N%29.png');
});

test('resolve url returns absolute http urls unchanged for external hosts', function () {
    expect(MealImagePath::resolveUrl('https://cdn.example.com/a.jpg'))->toBe('https://cdn.example.com/a.jpg');
});

test('resolve url rewrites same app http urls to current asset base', function () {
    $url = MealImagePath::resolveUrl('http://meal-craft.test/images/meals/stew.png');

    expect($url)->toContain('images/meals/stew.png');
});

test('resolve url serves storage disk paths', function () {
    $url = MealImagePath::resolveUrl('meals/upload.jpg');

    expect($url)->toContain('storage/meals/upload.jpg');
});

test('should delete from public disk is false for web images and remote urls', function () {
    expect(MealImagePath::shouldDeleteFromPublicDisk('images/meals/x.jpg'))->toBeFalse()
        ->and(MealImagePath::shouldDeleteFromPublicDisk('meals/x.jpg'))->toBeTrue()
        ->and(MealImagePath::shouldDeleteFromPublicDisk('https://example.com/x.jpg'))->toBeFalse();
});
