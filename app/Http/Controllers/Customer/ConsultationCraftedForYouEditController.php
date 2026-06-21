<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\ConsultationEditDraftBuilder;
use App\Support\AdminConsultationPreviewProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConsultationCraftedForYouEditController extends Controller
{
    public function __invoke(
        Request $request,
        ConsultationEditDraftBuilder $draftBuilder,
    ): RedirectResponse {
        $user = $request->user();
        $profile = $user !== null ? AdminConsultationPreviewProfile::resolve($user) : null;

        $plan = $profile?->craftPlans()
            ->whereNotNull('submitted_at')
            ->latest('submitted_at')
            ->first();

        if ($plan === null || $profile?->daily_calorie_target === null) {
            return redirect()->route('consultation.crafted-for-you');
        }

        $editDraft = $draftBuilder->buildFromPlan($plan);

        if ($editDraft === null) {
            return redirect()->route('consultation.crafted-for-you');
        }

        $request->session()->put('consultation_edit_draft', $editDraft);

        return redirect()->route('consultation.crafted-for-you', ['edit' => 1]);
    }
}
