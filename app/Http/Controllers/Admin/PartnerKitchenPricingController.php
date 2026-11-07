<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartnerKitchenDiscountRequest;
use App\Http\Requests\Admin\UpdatePartnerKitchenPricingRequest;
use App\Models\PartnerKitchenDiscount;
use App\Models\PartnerKitchenPriceCard;
use App\Support\PartnerKitchenPrice;
use Database\Seeders\PartnerKitchenPriceSeeder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PartnerKitchenPricingController extends Controller
{
    public function index(): Response
    {
        if (PartnerKitchenPriceCard::query()->count() === 0) {
            (new PartnerKitchenPriceSeeder)->run();
        }

        $cards = PartnerKitchenPriceCard::query()
            ->orderBy('scope')
            ->orderBy('calorie_row')
            ->orderBy('billed_days')
            ->get()
            ->map(fn (PartnerKitchenPriceCard $card): array => [
                'id' => $card->id,
                'scope' => $card->scope,
                'calorieRow' => (int) $card->calorie_row,
                'billedDays' => (int) $card->billed_days,
                'amountBhd' => round((float) $card->amount_bhd, 3),
            ])
            ->values()
            ->all();

        $discounts = PartnerKitchenDiscount::query()
            ->orderByDesc('id')
            ->get()
            ->map(fn (PartnerKitchenDiscount $discount): array => [
                'id' => $discount->id,
                'label' => $discount->label,
                'kind' => $discount->kind,
                'amountBhd' => round((float) $discount->amount_bhd, 3),
                'isActive' => (bool) $discount->is_active,
            ])
            ->values()
            ->all();

        $limitedTime = PartnerKitchenPrice::activeLimitedTimeDiscount();

        return Inertia::render('Admin/PartnerKitchenPricing', [
            'cards' => $cards,
            'discounts' => $discounts,
            'limitedTimeDiscount' => $limitedTime['amount'],
            'limitedTimeDiscountLabel' => $limitedTime['label'],
            'deliveryCost' => round((float) config('checkout.delivery_cost', 5), 3),
            'currency' => (string) config('checkout.currency', 'BHD'),
        ]);
    }

    public function update(UpdatePartnerKitchenPricingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach ($validated['cards'] as $row) {
            PartnerKitchenPriceCard::query()
                ->whereKey($row['id'])
                ->update(['amount_bhd' => round((float) $row['amount_bhd'], 3)]);
        }

        if (array_key_exists('limited_time_discount', $validated) || array_key_exists('limited_time_discount_label', $validated)) {
            $existing = PartnerKitchenDiscount::query()
                ->where('kind', 'limited_time')
                ->orderByDesc('id')
                ->first();

            $payload = [
                'kind' => 'limited_time',
                'label' => (string) ($validated['limited_time_discount_label']
                    ?? $existing?->label
                    ?? config('checkout.limited_time_discount_label', 'Limited time offer')),
                'amount_bhd' => round((float) ($validated['limited_time_discount']
                    ?? $existing?->amount_bhd
                    ?? config('checkout.limited_time_discount', 20)), 3),
                'is_active' => true,
            ];

            if ($existing instanceof PartnerKitchenDiscount) {
                $existing->update($payload);
            } else {
                PartnerKitchenDiscount::query()->create($payload);
            }
        }

        return redirect()
            ->route('admin.partner-kitchen-pricing')
            ->with('success', __('Partner Kitchen prices were updated.'));
    }

    public function storeDiscount(StorePartnerKitchenDiscountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        PartnerKitchenDiscount::query()->create([
            'label' => $validated['label'],
            'kind' => 'fixed',
            'amount_bhd' => round((float) $validated['amount_bhd'], 3),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()
            ->route('admin.partner-kitchen-pricing')
            ->with('success', __('Discount added.'));
    }

    public function destroyDiscount(PartnerKitchenDiscount $discount): RedirectResponse
    {
        if ($discount->kind === 'limited_time') {
            $discount->update(['is_active' => false]);
        } else {
            $discount->delete();
        }

        return redirect()
            ->route('admin.partner-kitchen-pricing')
            ->with('success', __('Discount removed.'));
    }
}
