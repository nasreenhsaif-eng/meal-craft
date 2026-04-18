<?php

use App\Models\Ingredient;
use Tests\TestCase;

uses(TestCase::class);

test('b-vitamin refetch helpers are removed (local-only mode)', function (): void {
    $ing = new Ingredient;

    expect(method_exists($ing, 'needsBvitaminOrFolateRefetch'))->toBeFalse()
        ->and(method_exists(Ingredient::class, 'micronutrientValueIsMissingOrZero'))->toBeFalse();
});
