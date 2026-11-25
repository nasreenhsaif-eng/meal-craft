<?php

use App\Models\User;
use App\Services\Nutrition\AdaptedMenuBuildOptionsFromRequest;
use Illuminate\Http\Request;

test('adapted menu options keep integer meal ids and drop sample deck ids', function () {
    $request = Request::create('/adapted-menu', 'GET', [
        'selected_main_meal_ids' => [12, 'meal-2'],
        'selected_breakfast_meal_ids' => ['breakfast-1'],
    ]);

    $options = AdaptedMenuBuildOptionsFromRequest::resolve($request, new User);

    expect($options['selected_main_meal_ids'])->toBe([12])
        ->and($options)->not->toHaveKey('selected_breakfast_meal_ids');
});
