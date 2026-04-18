<?php

use App\Models\FdcFoodIndex;
use App\Models\User;
use Livewire\Livewire;
use Livewire\Exceptions\MethodNotFoundException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('fdc import command upserts rows from csv', function () {
    $path = tempnam(sys_get_temp_dir(), 'fdc_csv_');
    file_put_contents($path, "fdc_id,description,data_type,food_category,ndb_number\n900001,\"Test food A\",Foundation,\"Beverages\",111\n");

    $this->artisan('fdc:import', ['path' => $path])->assertSuccessful();

    @unlink($path);

    expect(FdcFoodIndex::query()->where('fdc_id', 900001)->exists())->toBeTrue()
        ->and(FdcFoodIndex::query()->where('fdc_id', 900001)->value('food_category'))->toBe('Beverages');
});

test('ingredients external fdc/usda nutrition loader is disabled (local-only mode)', function () {
    $this->actingAs(User::factory()->create());

    FdcFoodIndex::factory()->create([
        'fdc_id' => 880_001,
        'description' => 'Indexed apple',
        'data_type' => 'Foundation',
        'food_category' => 'Fruits and Fruit Juices',
    ]);

    expect(fn () => Livewire::test('pages::ingredients')->call('loadNutritionFromFdcIndex', 880_001))
        ->toThrow(MethodNotFoundException::class);
});
