<?php

use Illuminate\Support\Facades\Blade;

test('branding config exposes core identity fields', function () {
    expect(config('branding.name'))->not->toBeEmpty();
    expect(config('branding.colors.primary'))->toStartWith('#');
});

test('logo sheet svg exists for MealCraftLogo asset pipeline', function () {
    expect(file_exists(resource_path('images/branding/logo-sheet.svg')))->toBeTrue();
    expect(file_exists(public_path('images/branding/logo-sheet.svg')))->toBeTrue();

    expect(file_get_contents(resource_path('images/branding/logo-sheet.svg')))
        ->toBe(file_get_contents(public_path('images/branding/logo-sheet.svg')));

    $sprite = file_get_contents(public_path('images/branding/logo-sheet.svg'));
    expect($sprite)->toContain('viewBox="0 0 1340 1260"');
    expect($sprite)->toContain('height="1260"');
    expect($sprite)->toContain('id="mc-vertical-lockups"');
});

test('vertical lockup svgs exist and mirror resources to public', function () {
    foreach (
        [
            'vertical-lockup-standard.svg',
            'vertical-lockup-marketing.svg',
        ] as $file
    ) {
        expect(file_exists(resource_path('images/branding/'.$file)))->toBeTrue();
        expect(file_exists(public_path('images/branding/'.$file)))->toBeTrue();
        expect(file_get_contents(resource_path('images/branding/'.$file)))
            ->toBe(file_get_contents(public_path('images/branding/'.$file)));
    }
});

test('meal craft logo blade renders smart horizontal slice', function () {
    $html = Blade::render(
        '<x-meal-craft-logo variant="smart" :width="200" />'
    );

    expect($html)->toContain('viewBox="315 204 217 68"');
    expect($html)->toContain('data-tagline-opacity="0.6"');
    expect($html)->toContain('images/branding/logo-sheet.svg');
    expect($html)->toContain('height="1260"');
});

test('meal craft logo blade accepts deprecated standard alias for smart slice', function () {
    $html = Blade::render(
        '<x-meal-craft-logo variant="standard" :width="200" />'
    );

    expect($html)->toContain('viewBox="315 204 217 68"');
    expect($html)->toContain('data-tagline-opacity="0.6"');
});

test('meal craft logo blade renders leaf color slice', function () {
    $html = Blade::render(
        '<x-meal-craft-logo variant="leaf" color="red" :width="33" />'
    );

    expect($html)->toContain('viewBox="355 30 33 87"');
});

test('meal craft logo blade renders seal slice', function () {
    $html = Blade::render(
        '<x-meal-craft-logo variant="seal-xs" :width="84" />'
    );

    expect($html)->toContain('viewBox="72 515 84 84"');
});

test('meal craft logo blade renders seal-xl slice with stroke bleed height', function () {
    $html = Blade::render(
        '<x-meal-craft-logo variant="seal-xl" :width="400" />'
    );

    expect($html)->toContain('viewBox="836 348 448 428"');
});

test('meal craft logo blade renders vertical lockup sprite slices', function () {
    $minimal = Blade::render(
        '<x-meal-craft-logo variant="vertical-minimal" :width="280" />'
    );
    $smart = Blade::render(
        '<x-meal-craft-logo variant="vertical-smart" :width="280" />'
    );
    $marketing = Blade::render(
        '<x-meal-craft-logo variant="vertical-marketing" :width="400" />'
    );

    expect($minimal)->toContain('viewBox="222 660 280 180"');
    expect($minimal)->toContain('images/branding/logo-sheet.svg');
    expect($minimal)->toContain('width="1340"');
    expect($minimal)->toContain('height="1260"');

    expect($smart)->toContain('viewBox="222 840 280 220"');
    expect($smart)->toContain('data-tagline-opacity="0.6"');

    expect($marketing)->toContain('viewBox="470 870 400 380"');
    expect($marketing)->toContain('data-tagline-opacity="0.6"');
});

test('meal craft logo blade accepts deprecated vertical-standard alias for minimal vertical slice', function () {
    $html = Blade::render(
        '<x-meal-craft-logo variant="vertical-standard" :width="280" />'
    );

    expect($html)->toContain('viewBox="222 660 280 180"');
});
