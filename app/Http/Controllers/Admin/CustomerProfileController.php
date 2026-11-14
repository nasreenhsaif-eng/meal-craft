<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerSubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCustomerProfileRequest;
use App\Models\CustomerProfile;
use App\Models\CustomerSubscription;
use App\Support\AdminPortalPermission;
use App\Support\CustomerIntake;
use App\Support\CustomerSubscriptionRecorder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CustomerProfileController extends Controller
{
    public function index(): Response
    {
        $canEdit = request()->user()?->hasAdminPermission(AdminPortalPermission::CustomerProfiles) ?? false;

        $customers = CustomerProfile::query()
            ->with([
                'user:id,name,email,is_active,created_at',
                'craftPlans',
                'subscriptions' => fn ($query) => $query
                    ->where('status', CustomerSubscriptionStatus::Paid)
                    ->latest('paid_at')
                    ->with(['freezes' => fn ($freezes) => $freezes->orderBy('frozen_on')]),
            ])
            ->latest()
            ->get()
            ->map(function (CustomerProfile $profile) use ($canEdit): array {
                /** @var CustomerSubscription|null $subscription */
                $subscription = $profile->subscriptions->first();

                return [
                    'id' => $profile->id,
                    'name' => $profile->user?->name ?? '',
                    'uniqueCode' => $profile->unique_code,
                    'isActive' => (bool) ($profile->user?->is_active ?? false),
                    'onboardingStep' => $profile->onboarding_step?->value,
                    'onboardingCompletedAt' => $profile->onboarding_completed_at?->toIso8601String(),
                    'updateUrl' => $canEdit ? route('admin.customers.update', $profile) : null,
                    'fields' => CustomerIntake::toFormArray($profile),
                    'subscription' => CustomerSubscriptionRecorder::toAdminArray($subscription),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Admin/CustomerProfiles', [
            'customers' => $customers,
            'canEdit' => $canEdit,
            'options' => CustomerIntake::optionLists(),
        ]);
    }

    public function show(CustomerProfile $customer): Response
    {
        $customer->load(['user:id,name,email,is_active', 'craftPlans']);
        $canEdit = request()->user()?->hasAdminPermission(AdminPortalPermission::CustomerProfiles) ?? false;
        $subscription = $customer->latestPaidSubscription();
        $subscription?->load(['freezes' => fn ($query) => $query->orderBy('frozen_on')]);

        return Inertia::render('Admin/CustomerProfileShow', [
            ...CustomerIntake::pageProps($customer),
            'canEdit' => $canEdit,
            'submitUrl' => $canEdit ? route('admin.customers.update', $customer) : null,
            'listUrl' => route('admin.customers'),
            'customerName' => $customer->user?->name ?? '',
            'subscription' => CustomerSubscriptionRecorder::toAdminArray($subscription),
        ]);
    }

    public function update(UpdateCustomerProfileRequest $request, CustomerProfile $customer): RedirectResponse
    {
        CustomerIntake::apply($customer, $request->validated());

        return redirect()
            ->back(302, [], route('admin.customers'))
            ->with('success', 'Customer profile saved.');
    }
}
