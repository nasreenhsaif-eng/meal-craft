<?php

use App\Enums\DietProtocol;
use App\Models\CustomerProfile;
use App\Support\PrimaryFullCraftMainSlots;

test('balanced protocol uses chicken plate and fish primary main slots', function (): void {
    expect(PrimaryFullCraftMainSlots::forProtocol(DietProtocol::Balanced))->toBe([1, 3])
        ->and(PrimaryFullCraftMainSlots::forProtocol(null))->toBe([1, 3]);
});

test('nutrient dense protocol uses chicken plate and liver primary main slots', function (): void {
    expect(PrimaryFullCraftMainSlots::forProtocol(DietProtocol::NutrientDense))->toBe([1, 5]);
});

test('profile diet protocol drives primary main slots', function (): void {
    $balanced = new CustomerProfile(['diet_protocol' => 'balanced']);
    $dense = new CustomerProfile(['diet_protocol' => 'nutrient_dense']);

    expect(PrimaryFullCraftMainSlots::forProfile($balanced))->toBe([1, 3])
        ->and(PrimaryFullCraftMainSlots::isPrimarySlot(3, $balanced))->toBeTrue()
        ->and(PrimaryFullCraftMainSlots::isPrimarySlot(5, $balanced))->toBeFalse()
        ->and(PrimaryFullCraftMainSlots::forProfile($dense))->toBe([1, 5])
        ->and(PrimaryFullCraftMainSlots::isPrimarySlot(5, $dense))->toBeTrue()
        ->and(PrimaryFullCraftMainSlots::isPrimarySlot(3, $dense))->toBeFalse();
});
