<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderBlockNotice;
use App\Services\NoShowService;
use App\Support\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlockNoticeAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAccess($request);

        return view('admin.block-notices.index', [
            'notices' => OrderBlockNotice::query()->unseen()->with('order')->latest()->get(),
        ]);
    }

    public function seen(Request $request, OrderBlockNotice $notice): RedirectResponse
    {
        $this->authorizeAccess($request);

        $notice->update(['seen_at' => now(), 'seen_by' => $request->user()->id]);

        return redirect()->route('admin.block-notices.index');
    }

    public function seenAll(Request $request, NoShowService $noShow): RedirectResponse
    {
        $this->authorizeAccess($request);

        $noShow->markAllSeen($request->user());

        return redirect()->route('admin.block-notices.index')->with('success', 'Segnalazioni archiviate.');
    }

    private function authorizeAccess(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermission(Permission::ORDERS_MARKET_MANAGE)
            || $user->hasPermission(Permission::ORDERS_DELIVERY_MANAGE)
            || $user->hasPermission(Permission::COUNTER_USE),
            403,
        );
    }

    public static function unseenCount(): int
    {
        return OrderBlockNotice::query()->unseen()->count();
    }
}
