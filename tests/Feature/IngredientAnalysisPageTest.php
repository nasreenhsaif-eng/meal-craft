<?php

use App\Models\User;
use Livewire\Livewire;

test('legacy meal craft analysis url redirects to ingredients workspace', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/ingredients/analysis')
        ->assertNotFound();
});

test('ingredients page is local-only for authenticated users', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('ingredients.index'))
        ->assertOk()
        ->assertDontSee('ingredient-analyzer-root', false)
        ->assertSee('id="ingredients-advanced"', false);
});

test('guests cannot view ingredients workspace', function (): void {
    $this->get(route('ingredients.index'))
        ->assertRedirect();
});

test('ingredients workspace sets library search when enrich focus event is dispatched', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::ingredients')
        ->dispatch('focus-ingredient-library-search', q: 'Chicken, breast, meat only, raw')
        ->assertSet('libraryTableSearch', 'Chicken, breast, meat only, raw');
});
