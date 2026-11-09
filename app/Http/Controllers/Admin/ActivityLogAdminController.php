<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogAdminController extends Controller
{
    public function index(Request $request): View
    {
        $logs = ActivityLog::query()
            ->with('user')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->query('user_id')))
            ->when($request->filled('action'), fn ($query) => $query->where('action', 'LIKE', $request->query('action').'%'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.activity-log.index', [
            'logs' => $logs,
            'staff' => User::query()->whereIn('role', [User::ROLE_SELLER, User::ROLE_COLLABORATOR])->orderBy('name')->get(),
        ]);
    }
}
