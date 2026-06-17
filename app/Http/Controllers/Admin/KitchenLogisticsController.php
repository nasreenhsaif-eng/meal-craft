<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KitchenLogisticsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/KitchenLogistics', [
            'initialProductionDate' => now()->toDateString(),
            'kitchenDailySheetUrl' => url('/api/admin/kitchen/daily-sheet'),
        ]);
    }
}
