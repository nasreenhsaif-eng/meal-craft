<?php

use App\Models\User;
use App\Services\MealCraftMasterCsvExport;

test('guest cannot download meal craft csv template', function () {
    $this->get(route('admin.meal-library.csv-template'))->assertRedirect();
});

test('authenticated user downloads meal craft csv template with canonical headers and sample row', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.meal-library.csv-template'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $body = (string) $response->getContent();
    $lines = preg_split("/\r\n|\n|\r/", trim($body)) ?: [];
    expect($lines)->toHaveCount(2);

    $headerCells = str_getcsv($lines[0]);
    expect($headerCells)->toBe(MealCraftMasterCsvExport::MEAL_CRAFT_CSV_TEMPLATE_HEADERS);

    expect($lines[1])->toContain('Thai Red Curry Chicken w Roasted Pumpkin')
        ->and($lines[1])->toContain('Chicken Breast (100g)')
        ->and($lines[1])->toContain('Red Thai Curry Paste (Base) (45g)');
});
