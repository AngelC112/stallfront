<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\View\View;

class DashboardAdminController extends Controller
{
    public function __invoke(DashboardService $dashboard): View
    {
        return view('admin.dashboard', ['stats' => $dashboard->overview()]);
    }
}
