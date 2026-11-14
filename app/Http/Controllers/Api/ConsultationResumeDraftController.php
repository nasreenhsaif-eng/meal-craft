<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreConsultationResumeDraftRequest;
use Illuminate\Http\JsonResponse;

class ConsultationResumeDraftController extends Controller
{
    public const SESSION_DRAFT_KEY = 'consultation_resume_draft';

    public const SESSION_RESUME_KEY = 'consultation_resume_choose_meals';

    public function store(StoreConsultationResumeDraftRequest $request): JsonResponse
    {
        $request->session()->put(self::SESSION_DRAFT_KEY, $request->validated());
        $request->session()->put(self::SESSION_RESUME_KEY, true);
        // Also set the checkout flag so payment redirects even if delivery is opened without ?resume=.
        $request->session()->put('checkout_resume_choose_meals', true);

        return response()->json([
            'message' => 'Consultation draft saved for checkout resume.',
        ]);
    }

    public static function forget(mixed $session): void
    {
        $session->forget([self::SESSION_DRAFT_KEY, self::SESSION_RESUME_KEY]);
    }
}
