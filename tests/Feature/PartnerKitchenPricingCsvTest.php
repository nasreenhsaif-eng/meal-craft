<?php

use App\Enums\UserRole;
use App\Models\PlanCaloriePrice;
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
        ->and($csv)->toContain('plan_key,calorie_tier,days_count,base_total_price,discounted_total_price')
        ->and($csv)->toContain('full,')
        ->and($csv)->toContain('business,');
});

test('admins can import partner kitchen pricing csv to update amounts', function (): void {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $price = PlanCaloriePrice::query()
        ->where('calorie_tier', 1200)
        ->where('days_count', 20)
        ->whereHas('plan', fn ($query) => $query->where('key', 'full'))
        ->firstOrFail();

    $csv = implode("\r\n", [
        'plan_key,calorie_tier,days_count,base_total_price,discounted_total_price',
        'full,1200,20,333,300',
        '',
    ]);

    $file = UploadedFile::fake()->createWithContent('partner-kitchen-pricing.csv', $csv);

    $this->actingAs($admin)
        ->post(route('admin.partner-kitchen-pricing.import-csv'), [
            'file' => $file,
        ])
        ->assertRedirect(route('admin.partner-kitchen-pricing'))
        ->assertSessionHas('success');

    $fresh = $price->fresh();

    expect($fresh->base_total_price)->toBe(333)
        ->and($fresh->discounted_total_price)->toBe(300)
        ->and($fresh->effectiveTotalPrice())->toBe(300)
        ->and($fresh->daily_rate)->toBe(15);
});

test('partner kitchen pricing csv import rejects invalid plan key', function (): void {
    $this->seed(PartnerKitchenPriceSeeder::class);
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $csv = implode("\r\n", [
        'plan_key,calorie_tier,days_count,base_total_price,discounted_total_price',
        'unknown,1200,20,100,',
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
