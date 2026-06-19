<?php

use App\Support\UsdaNutrientMath;
use App\Support\VitaminK2Resolver;

test('menaquinone is read from fdc id 1183 and ndb 428', function (): void {
    $by = [
        UsdaNutrientMath::FDC_MENAQUINONE_4 => 8.6,
    ];

    expect(VitaminK2Resolver::menaquinoneMcgPer100gFromFdcMap($by))->toBe(8.6)
        ->and(UsdaNutrientMath::vitaminK2McgPer100gFromMap($by))->toBe(8.6);
});

test('k1 dominant vegetables resolve to zero k2 without fdc menaquinone', function (): void {
    $by = [
        UsdaNutrientMath::FDC_PHYLLOQUINONE => 483.0,
    ];

    expect(VitaminK2Resolver::resolve('Spinach (Fresh)', 'Vegetables', $by))->toBe(0.0);
});

test('fdc menaquinone is used for dairy when reported', function (): void {
    $by = [
        UsdaNutrientMath::FDC_MENAQUINONE_4 => 8.6,
        UsdaNutrientMath::FDC_PHYLLOQUINONE => 2.4,
    ];

    expect(VitaminK2Resolver::resolve('Cheddar Cheese', 'Dairy', $by))->toBe(8.6);
});

test('natto uses clinical mk7 override when fdc lacks menaquinone', function (): void {
    $by = [
        UsdaNutrientMath::FDC_PHYLLOQUINONE => 23.1,
    ];

    expect(VitaminK2Resolver::resolve('Natto', 'Legumes', $by))
        ->toBe(VitaminK2Resolver::NATTO_MK7_MCG_PER_100G);
});

test('butter uses literature mk4 override when fdc lacks menaquinone', function (): void {
    $by = [
        UsdaNutrientMath::FDC_PHYLLOQUINONE => 7.0,
    ];

    expect(VitaminK2Resolver::resolve('Butter (Unsalted)', 'Fats', $by))
        ->toBe(VitaminK2Resolver::BUTTER_GHEE_MK4_MCG_PER_100G);
});

test('almond butter is treated as k1 dominant', function (): void {
    $by = [
        UsdaNutrientMath::FDC_PHYLLOQUINONE => 34.1,
    ];

    expect(VitaminK2Resolver::resolve('Almond Butter', 'Fats/Nuts', $by))->toBe(0.0);
});

test('beef in proteins category resolves to red meat k2 estimate without fdc menaquinone', function (): void {
    expect(VitaminK2Resolver::resolve('Beef Ground Lean', 'Proteins', []))
        ->toBe(VitaminK2Resolver::RED_MEAT_MK4_MCG_PER_100G)
        ->and(VitaminK2Resolver::resolve('Beef Brisket', 'Proteins', []))
        ->toBe(VitaminK2Resolver::RED_MEAT_MK4_MCG_PER_100G);
});

test('fdc menaquinone still wins over meat estimate when present', function (): void {
    $by = [
        UsdaNutrientMath::FDC_MENAQUINONE_4 => 3.2,
    ];

    expect(VitaminK2Resolver::resolve('Beef Sirloin', 'Proteins', $by))->toBe(3.2);
});
