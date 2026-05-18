<?php

use App\Models\Ingredient;
use App\Models\User;

test('guests cannot download ingredient library csv export', function (): void {
    $this->get(route('admin.ingredient-library.export-csv'))->assertRedirect();
});

test('guests cannot post ingredient library csv import', function (): void {
    $this->post(route('admin.ingredient-library.import-csv'))->assertRedirect();
});

test('authenticated users can export verified ingredients as csv', function (): void {
    $user = User::factory()->create();
    Ingredient::factory()->create([
        'is_verified' => true,
        'name' => 'Unique Export Row',
        'usda_food_category' => 'TestCat',
        'fdc_id' => 999001,
        'micronutrients' => ['vitamin_c' => 12.5, 'zinc' => 0.9],
    ]);

    $response = $this->actingAs($user)->get(route('admin.ingredient-library.export-csv'));

    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->not->toBe('')
        ->and($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('name,category,fdc_id')
        ->and($csv)->toContain(',description,instructions,finished_weight_grams,g6pd_trigger')
        ->and($csv)->toContain('Unique Export Row')
        ->and($csv)->toContain('TestCat');
});

test('ingredient library csv export flattens multiline instructions for excel', function (): void {
    $user = User::factory()->create();
    Ingredient::factory()->create([
        'is_verified' => true,
        'name' => 'Multiline Export Test',
        'description' => "Line one\nLine two",
        'instructions' => "Step 1: Mix.\nStep 2: Bake.",
    ]);

    $response = $this->actingAs($user)->get(route('admin.ingredient-library.export-csv'));

    $response->assertOk();
    $csv = $response->streamedContent();
    $withoutBom = substr($csv, 3);
    $physicalLines = preg_split('/\r\n|\n|\r/', trim($withoutBom)) ?: [];

    expect($physicalLines)->toHaveCount(2)
        ->and($physicalLines[1])->toContain('Multiline Export Test')
        ->and($physicalLines[1])->toContain('Line one Line two')
        ->and($physicalLines[1])->toContain('Step 1: Mix.\nStep 2: Bake.')
        ->and($physicalLines[1])->not->toContain("Step 1: Mix.\r\n");
});
