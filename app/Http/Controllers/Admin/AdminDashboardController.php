<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AdminPortalPermission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Admin command-center dashboard (Inertia).
     *
     * Stats are intentionally hardcoded for now so the UI can ship before live aggregates exist.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $isCraftMaster = $user?->isCraftMasterGeneral() ?? false;

        $portalUsers = $isCraftMaster
            ? User::query()
                ->whereIn('role', [UserRole::Admin, UserRole::CraftMasterGeneral])
                ->orderBy('name')
                ->get()
                ->map(fn (User $portalUser): array => [
                    'id' => (string) $portalUser->id,
                    'name' => $portalUser->name,
                    'email' => $portalUser->email,
                    'role' => $portalUser->role->value,
                    'roleLabel' => $portalUser->role->label(),
                    'permissions' => $portalUser->adminPermissionKeys(),
                    'isDeactivated' => ! $portalUser->is_active,
                    'isCraftMasterGeneral' => $portalUser->isCraftMasterGeneral(),
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('Admin/Dashboard', [
            'adminName' => $user?->name ?? 'Admin',
            'adminEmail' => $user?->email ?? '',
            'isCraftMasterGeneral' => $isCraftMaster,
            'permissionChecklist' => AdminPortalPermission::checklist(),
            'users' => $portalUsers,
            'stats' => [
                'totalSubmissions' => 0,
                'totalRevenue' => 0,
                // Kept as the user list for the dashboard table (not a count).
                'activeUsers' => $portalUsers,
                'customersCount' => 0,
                'ingredientCount' => 0,
                'mealCount' => 0,
                'mealPlanCount' => 0,
                'totalCost' => 0,
                'grossProfit' => 0,
            ],
        ]);
    }
}
