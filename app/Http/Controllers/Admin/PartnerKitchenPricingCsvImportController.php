<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\PartnerKitchenPriceCardsImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PartnerKitchenPricingCsvImportController extends Controller
{
    public function __invoke(
        Request $request,
        PartnerKitchenPriceCardsImport $import,
    ): RedirectResponse {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        try {
            $result = $import->import($validated['file']);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.partner-kitchen-pricing')
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.partner-kitchen-pricing')
            ->with('success', __(
                'CSV import complete: :updated updated, :created created.',
                [
                    'updated' => $result['updated'],
                    'created' => $result['created'],
                ],
            ));
    }
}
