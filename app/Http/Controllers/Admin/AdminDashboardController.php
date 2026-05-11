<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return Inertia::render('Admin/Dashboard', [
            'adminName' => $user?->name ?? 'Admin',
            'adminEmail' => $user?->email ?? '',
            'stats' => [
                'totalSubmissions' => 0,
                'totalRevenue' => 0,
                'activeUsers' => [],
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
