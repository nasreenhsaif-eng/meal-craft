<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCustomerProfileRequest;
use App\Models\CustomerProfile;
use App\Support\CustomerIntake;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerProfileController extends Controller
{
    public function index(): Response
    {
        $customers = CustomerProfile::query()
            ->with(['user:id,name,email,is_active,created_at'])
            ->latest()
            ->get()
            ->map(fn (CustomerProfile $profile): array => [
                'id' => $profile->id,
                'name' => $profile->user->name,
                'email' => $profile->user->email,
                'phone' => $profile->phone,
                'uniqueCode' => $profile->unique_code,
                'plannedStartDate' => $profile->planned_start_date?->toDateString(),
                'isActive' => (bool) $profile->user->is_active,
                'onboardingStep' => $profile->onboarding_step?->value,
                'onboardingCompletedAt' => $profile->onboarding_completed_at?->toIso8601String(),
                'dailyCalorieTarget' => $profile->daily_calorie_target,
                'joinedAt' => $profile->user->created_at?->toIso8601String(),
                'showUrl' => route('admin.customers.show', $profile),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/CustomerProfiles', [
            'customers' => $customers,
        ]);
    }

    public function show(CustomerProfile $customer): Response
    {
        $customer->load(['user:id,name,email,is_active', 'craftPlans']);

        return Inertia::render('Admin/CustomerProfileShow', [
            ...CustomerIntake::pageProps($customer),
            'submitUrl' => route('admin.customers.update', $customer),
            'listUrl' => route('admin.customers'),
            'customerName' => $customer->user?->name ?? '',
        ]);
    }

    public function update(UpdateCustomerProfileRequest $request, CustomerProfile $customer): RedirectResponse
    {
        CustomerIntake::apply($customer, $request->validated());

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'Customer profile saved.');
    }
}
