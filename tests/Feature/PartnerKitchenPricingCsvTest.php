<?php

use App\Enums\UserRole;
use App\Models\PartnerKitchenPriceCard;
use App\Models\User;
use Database\Seeders\PartnerKitchenPriceSeeder;
use Illuminate\Http\UploadedFile;

test('guests cannot download partner kitchen pricing csv', function (): void {
    $this->get(route('admin.partner-kitchen-pricing.export-csv'))->assertRedirect();
});

test('guests cannot import partner kitchen pricing csv', function (): void {
    $this->post(route('admin.partner-kitchen-pricing.import-csv'))->assertRedirect();
});

test('admins can export partner kitchen pricing as csv', function (): void {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get(route('admin.partner-kitchen-pricing.export-csv'));

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->not->toBe('')
        ->and($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('scope,calorie_row,billed_days,amount_bhd,discount_type,discount_bhd')
        ->and($csv)->toContain('full,')
        ->and($csv)->toContain('business,');
});

test('admins can import partner kitchen pricing csv to update amounts', function (): void {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $card = PartnerKitchenPriceCard::query()
        ->where('scope', 'full')
        ->where('calorie_row', 1200)
        ->where('billed_days', 20)
        ->firstOrFail();

    $csv = implode("\r\n", [
        'scope,calorie_row,billed_days,amount_bhd,discount_type,discount_bhd',
        'full,1200,20,333.5,percent,10',
        '',
    ]);

    $file = UploadedFile::fake()->createWithContent('partner-kitchen-pricing.csv', $csv);

    $this->actingAs($admin)
        ->post(route('admin.partner-kitchen-pricing.import-csv'), [
            'file' => $file,
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'))
        ->assertSessionHas('success');

    $fresh = $card->fresh();

    expect((float) $fresh->amount_bhd)->toBe(333.5)
        ->and($fresh->discount_type)->toBe('percent')
        ->and((float) $fresh->discount_bhd)->toBe(10.0)
        ->and($fresh->netAmountBhd())->toBe(300.15);
});

test('partner kitchen pricing csv import rejects invalid scope', function (): void {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $csv = implode("\r\n", [
        'scope,calorie_row,billed_days,amount_bhd,discount_type,discount_bhd',
        'unknown,1200,20,100,amount,0',
        '',
    ]);

    $file = UploadedFile::fake()->createWithContent('bad-pricing.csv', $csv);

    $this->actingAs($admin)
        ->post(route('admin.partner-kitchen-pricing.import-csv'), [
            'file' => $file,
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'))
        ->assertSessionHas('error');
});
