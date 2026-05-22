<?php

use App\IngredientsImport;
use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('ingredients csv import maps g6pd_trigger column', function () {
    $csv = <<<'CSV'
name,g6pd_trigger
Fava beans,1
Rice,0
CSV;

    $file = UploadedFile::fake()->createWithContent('ingredients-g6pd.csv', $csv);

    expect(app(IngredientsImport::class)->import($file))->toBe(2);

    expect(Ingredient::query()->where('name', 'Fava beans')->first())
        ->is_g6pd_trigger->toBeTrue();
    expect(Ingredient::query()->where('name', 'Rice')->first())
        ->is_g6pd_trigger->toBeFalse();
});

test('ingredients csv import treats fdc_id zero as null so multiple manual rows can import', function () {
    Ingredient::query()->create([
        'name' => 'Existing Spice',
        'fdc_id' => 0,
        'calories' => 100,
        'protein' => 1,
        'carbs' => 1,
        'fat' => 1,
        'b6' => 0,
        'b9_folate' => 0,
        'b12' => 0,
        'iron' => 0,
        'magnesium' => 0,
        'is_verified' => true,
    ]);

    $csv = <<<'CSV'
name,category,fdc_id,calories,protein,carbs,fat
Cloves,Spices,0,274,6,65.5
CSV;

    $file = UploadedFile::fake()->createWithContent('ingredients-fdc-zero.csv', $csv);

    expect(app(IngredientsImport::class)->import($file))->toBe(1);

    $cloves = Ingredient::query()->where('name', 'Cloves')->first();

    expect($cloves)->not->toBeNull()
        ->and($cloves->fdc_id)->toBeNull();
});

test('ingredients csv import accepts G6PD_Trigger header alias', function () {
    $csv = "name,G6PD_Trigger\nBroad beans,yes\n";

    $file = UploadedFile::fake()->createWithContent('ingredients-g6pd-alias.csv', $csv);

    app(IngredientsImport::class)->import($file);

    expect(Ingredient::query()->where('name', 'Broad beans')->first())
        ->is_g6pd_trigger->toBeTrue();
});
