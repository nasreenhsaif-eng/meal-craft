<?php

use App\Support\UsdaNutrientMath;
use Tests\TestCase;

uses(TestCase::class);

test('map by nutrient indexes fdc nutrient id and ndb number', function (): void {
    $foodNutrients = [
        [
            'nutrient' => [
                'id' => 1106,
                'number' => '318',
                'name' => 'Vitamin A, RAE',
            ],
            'amount' => 12.5,
        ],
    ];

    $map = UsdaNutrientMath::mapByNutrientNumber($foodNutrients);

    expect($map['1106'])->toBe(12.5)
        ->and($map['318'])->toBe(12.5);
});

test('value for nutrient keys prefers fdc id over ndb number', function (): void {
    $by = [
        '1106' => 99.0,
        '318' => 1.0,
    ];

    expect(UsdaNutrientMath::valueForNutrientKeys($by, UsdaNutrientMath::FDC_VITAMIN_A_RAE, '318'))->toBe(99.0);
});

test('value for nutrient keys falls back to ndb when fdc id missing', function (): void {
    $by = [
        '415' => 0.42,
    ];

    expect(UsdaNutrientMath::valueForNutrientKeys($by, UsdaNutrientMath::FDC_VITAMIN_B6, '415'))->toBe(0.42);
});

test('macros micronutrients use fdc ids when present', function (): void {
    $by = [
        '1008' => 100,
        '1003' => 10,
        '1004' => 5,
        '1005' => 20,
        '291' => 2,
        '1106' => 50,
        '1175' => 0.2,
        '1178' => 1.5,
        '1177' => 8,
        '401' => 10,
        '1087' => 120,
        '303' => 1,
        '306' => 200,
        '304' => 30,
        '1095' => 0.88,
        '1109' => 0.15,
    ];

    $macros = UsdaNutrientMath::macrosMicronutrientsPer100g($by);

    expect($macros['vitamin_a_rae_mcg'])->toBe(50.0)
        ->and($macros['vitamin_b6_mg'])->toBe(0.2)
        ->and($macros['vitamin_b12_mcg'])->toBe(1.5)
        ->and($macros['folate_mcg'])->toBe(8.0)
        ->and($macros['calcium_mg'])->toBe(120.0)
        ->and($macros['zinc_mg'])->toBe(0.88)
        ->and($macros['vitamin_e_mg'])->toBe(0.15);
});

test('map by nutrient indexes top level nutrientId', function (): void {
    $map = UsdaNutrientMath::mapByNutrientNumber([
        ['nutrientId' => 1177, 'amount' => 4.5],
    ]);

    expect($map['1177'])->toBe(4.5);
});

test('fdc key nutrients map extracts tracked fdc ids from nutrient map', function (): void {
    $by = [
        '1175' => 0.5,
        '1177' => 12,
        '1178' => 0.4,
        '1106' => 8,
        '1087' => 100,
    ];

    $key = UsdaNutrientMath::fdcKeyNutrientsPer100gFromMap($by);

    expect($key[UsdaNutrientMath::FDC_VITAMIN_B6])->toBe(0.5)
        ->and($key[UsdaNutrientMath::FDC_FOLATE])->toBe(12.0)
        ->and($key[UsdaNutrientMath::FDC_VITAMIN_B12])->toBe(0.4);
});

test('map by nutrient indexes attrId as fdc nutrient id', function (): void {
    $map = UsdaNutrientMath::mapByNutrientNumber([
        ['attrId' => 1178, 'amount' => 0.33],
    ]);

    expect($map['1178'])->toBe(0.33);
});

test('map by nutrient ignores rows without amount or value', function (): void {
    $map = UsdaNutrientMath::mapByNutrientNumber([
        ['nutrient' => ['id' => 1178, 'number' => '418']],
        ['nutrient' => ['id' => 1178, 'number' => '418'], 'amount' => 0.21],
    ]);

    expect($map['1178'])->toBe(0.21)
        ->and($map['418'])->toBe(0.21);
});

test('scale to portion multiplies per 100g by portion grams over 100', function (): void {
    $per100 = ['vitamin_b12_mcg' => 0.21];
    $portion = UsdaNutrientMath::scaleToPortion($per100, 120.0);

    expect($portion['vitamin_b12_mcg'])->toBe(0.252);
});

test('full food detail lacks b12 or folate when either amount is zero or missing', function (): void {
    expect(UsdaNutrientMath::fullFoodDetailLacksB12OrFolate([
        'foodNutrients' => [
            ['nutrient' => ['id' => 1178], 'amount' => 0.1],
            ['nutrient' => ['id' => 1177], 'amount' => 0],
        ],
    ]))->toBeTrue();

    expect(UsdaNutrientMath::fullFoodDetailLacksB12OrFolate([
        'foodNutrients' => [
            ['nutrient' => ['id' => 1178], 'amount' => 0],
            ['nutrient' => ['id' => 1177], 'amount' => 8],
        ],
    ]))->toBeTrue();

    expect(UsdaNutrientMath::fullFoodDetailLacksB12OrFolate([
        'foodNutrients' => [
            ['nutrient' => ['id' => 1178], 'amount' => 0.05],
            ['nutrient' => ['id' => 1177], 'amount' => 12],
        ],
    ]))->toBeFalse();
});

test('full food detail lacks positive b6 b12 and folate when any is zero or missing', function (): void {
    expect(UsdaNutrientMath::fullFoodDetailLacksPositiveB6B12AndFolate([
        'foodNutrients' => [
            ['nutrient' => ['id' => 1175], 'amount' => 0.9],
            ['nutrient' => ['id' => 1178], 'amount' => 0.1],
            ['nutrient' => ['id' => 1177], 'amount' => 0],
        ],
    ]))->toBeTrue();

    expect(UsdaNutrientMath::fullFoodDetailLacksPositiveB6B12AndFolate([
        'foodNutrients' => [
            ['nutrient' => ['id' => 1175], 'amount' => 0.97],
            ['nutrient' => ['id' => 1178], 'amount' => 0.25],
            ['nutrient' => ['id' => 1177], 'amount' => 10.8],
        ],
    ]))->toBeFalse();
});

test('foundation detail needs sr legacy fallback only for foundation data with b vitamin gaps', function (): void {
    expect(UsdaNutrientMath::foundationDetailNeedsSrLegacyMicronutrientFallback([
        'dataType' => 'Foundation',
        'foodNutrients' => [
            ['nutrient' => ['id' => 1178], 'amount' => 0],
            ['nutrient' => ['id' => 1177], 'amount' => 5],
        ],
    ]))->toBeTrue();

    expect(UsdaNutrientMath::foundationDetailNeedsSrLegacyMicronutrientFallback([
        'dataType' => 'foundation',
        'foodNutrients' => [
            ['nutrient' => ['id' => 1175], 'amount' => 0],
            ['nutrient' => ['id' => 1178], 'amount' => 0.2],
            ['nutrient' => ['id' => 1177], 'amount' => 6],
        ],
    ]))->toBeTrue();

    expect(UsdaNutrientMath::foundationDetailNeedsSrLegacyMicronutrientFallback([
        'dataType' => 'SR Legacy',
        'foodNutrients' => [
            ['nutrient' => ['id' => 1178], 'amount' => 0],
            ['nutrient' => ['id' => 1177], 'amount' => 5],
        ],
    ]))->toBeFalse();
});

test('full food detail lacks b6 or b12 when amounts are zero or missing', function (): void {
    expect(UsdaNutrientMath::fullFoodDetailLacksB6OrB12([
        'foodNutrients' => [
            ['nutrient' => ['id' => 1175], 'amount' => 0.5],
            ['nutrient' => ['id' => 1178], 'amount' => 0],
        ],
    ]))->toBeTrue();

    expect(UsdaNutrientMath::fullFoodDetailLacksB6OrB12([
        'foodNutrients' => [
            ['nutrient' => ['id' => 1175], 'amount' => 0.5],
            ['nutrient' => ['id' => 1178], 'amount' => 0.4],
        ],
    ]))->toBeFalse();
});

test('macros prefer fdc mineral ids when present', function (): void {
    $by = [
        '1008' => 100,
        '1003' => 10,
        '1004' => 5,
        '1005' => 20,
        '291' => 0,
        '1089' => 1.2,
        '1090' => 28,
        '1092' => 310,
        '1095' => 0.5,
        '1109' => 0.09,
    ];

    $m = UsdaNutrientMath::macrosMicronutrientsPer100g($by);

    expect($m['iron_mg'])->toBe(1.2)
        ->and($m['magnesium_mg'])->toBe(28.0)
        ->and($m['potassium_mg'])->toBe(310.0)
        ->and($m['zinc_mg'])->toBe(0.5)
        ->and($m['vitamin_e_mg'])->toBe(0.09);
});
