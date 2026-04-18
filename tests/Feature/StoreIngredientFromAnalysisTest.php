<?php

use App\Models\User;

test('save-from-analysis endpoint is disabled (local-only mode)', function (): void {
    $this->postJson('/ingredients/from-analysis', [])->assertNotFound();
});

test('authenticated users also cannot save from analysis', function (): void {
    $this->actingAs(User::factory()->create())
        ->postJson('/ingredients/from-analysis', [])
        ->assertNotFound();
});
