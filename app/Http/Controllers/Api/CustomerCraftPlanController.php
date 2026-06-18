<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCustomerCraftPlanRequest;
use App\Services\CustomerCraftPlanService;
use App\Support\AdminConsultationPreviewProfile;
use Illuminate\Http\JsonResponse;

class CustomerCraftPlanController extends Controller
{
    public function store(StoreCustomerCraftPlanRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = AdminConsultationPreviewProfile::resolve($user);

        if ($profile === null || $profile->daily_calorie_target === null) {
            return response()->json([
                'message' => 'Set your daily calorie target before submitting a craft plan.',
            ], 422);
        }

        $plan = CustomerCraftPlanService::storeSubmission($profile, $request->validated());

        return response()->json([
            'message' => 'Craft plan saved.',
            'summary_url' => route('app.meal-plan', absolute: false),
            'plan' => [
                'id' => $plan->id,
                'craft_key' => $plan->craft_key,
                'week_duration' => $plan->week_duration,
                'selected_weekdays' => $plan->selected_weekdays,
                'submitted_at' => $plan->submitted_at?->toIso8601String(),
                'days' => $plan->days->map(fn ($day) => [
                    'day_of_week' => $day->day_of_week,
                    'include_soup' => $day->include_soup,
                    'meals' => $day->meals->map(fn ($row) => [
                        'meal_id' => $row->meal_id,
                        'slot' => $row->slot->value,
                        'position' => $row->position,
                    ])->values(),
                ])->values(),
            ],
        ], 201);
    }
}
