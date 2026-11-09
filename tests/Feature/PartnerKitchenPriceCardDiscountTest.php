<?php

use App\Models\PartnerKitchenPriceCard;
use App\Support\PartnerKitchenPrice;
use Database\Seeders\PartnerKitchenPriceSeeder;

test('package amount uses the net price after card amount discount', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $card = PartnerKitchenPriceCard::query()
        ->where('scope', 'full')
        ->where('calorie_row', 1200)
        ->where('billed_days', 20)
        ->firstOrFail();

    $before = PartnerKitchenPrice::packageAmount('full', 1200, 5, 4);

    $card->update([
        'discount_type' => PartnerKitchenPriceCard::DISCOUNT_TYPE_AMOUNT,
        'discount_bhd' => 10,
    ]);

    expect(PartnerKitchenPrice::packageAmount('full', 1200, 5, 4))->toBe(round(max(0, $before - 10), 3))
        ->and($card->fresh()->netAmountBhd())->toBe(round(max(0, (float) $card->amount_bhd - 10), 3));
});

test('package amount uses the net price after card percent discount', function () {
    $this->seed(PartnerKitchenPriceSeeder::class);

    $card = PartnerKitchenPriceCard::query()
        ->where('scope', 'full')
        ->where('calorie_row', 1200)
        ->where('billed_days', 20)
        ->firstOrFail();

    $card->update([
        'amount_bhd' => 200,
        'discount_type' => PartnerKitchenPriceCard::DISCOUNT_TYPE_PERCENT,
        'discount_bhd' => 10,
    ]);

    expect(PartnerKitchenPrice::packageAmount('full', 1200, 5, 4))->toBe(180.0)
        ->and($card->fresh()->netAmountBhd())->toBe(180.0);
});
