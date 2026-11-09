<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BlocklistService;
use App\Support\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $term = trim((string) $request->query('q'));
        $filter = $request->query('filter');

        $customers = User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->when($term !== '', function ($query) use ($term): void {
                $digits = preg_replace('/\D+/', '', $term);

                $query->where(function ($inner) use ($term, $digits): void {
                    $inner->where('name', 'LIKE', "%{$term}%")
                        ->orWhere('email', 'LIKE', "%{$term}%")
                        ->orWhere('phone', 'LIKE', "%{$term}%");

                    if (strlen($digits) >= 4) {
                        $inner->orWhere('phone', 'LIKE', "%{$digits}%");
                    }
                });
            })
            ->when($filter === 'delivery', fn ($query) => $query->where('delivery_enabled', true))
            ->when($filter === 'no_shows', fn ($query) => $query->where('no_show_count', '>', 0))
            ->when($filter === 'blocked', fn ($query) => $query->whereNotNull('blocked_at'))
            ->withCount('orders')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', ['customers' => $customers]);
    }

    public function show(Request $request, User $customer): View
    {
        $this->authorizeView($request);
        abort_unless($customer->isCustomer(), 404);

        return view('admin.customers.show', [
            'customer' => $customer,
            'orders' => $customer->orders()->latest('placed_at')->limit(10)->get(),
        ]);
    }

    public function updateDelivery(Request $request, User $customer, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeSellerOnly($request, $customer);

        $enabled = $request->boolean('delivery_enabled');

        if ($enabled && ! $customer->phone) {
            throw ValidationException::withMessages([
                'delivery_enabled' => __('Il cliente non ha un cellulare: serve per chiamarlo e avvisarlo.'),
            ]);
        }

        $customer->forceFill(['delivery_enabled' => $enabled])->save();
        $logger->log($enabled ? 'customer.delivery_enabled' : 'customer.delivery_disabled', $customer);

        return back()->with('success', $enabled ? __('Consegna a domicilio abilitata.') : __('Consegna a domicilio disabilitata.'));
    }

    public function verifyPhone(Request $request, User $customer, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeSellerOnly($request, $customer);

        abort_unless($customer->phone, 422);

        $customer->forceFill(['phone_verified_at' => now()])->save();
        $logger->log('customer.phone_verified_manually', $customer);

        return back()->with('success', __('Cellulare segnato come verificato.'));
    }

    public function unblock(Request $request, User $customer, BlocklistService $blocklist, ActivityLogger $logger): RedirectResponse
    {
        $this->authorizeSellerOnly($request, $customer);

        $blocklist->unblockUser($customer);
        $logger->log('customer.unblocked', $customer);

        return back()->with('success', __('Cliente sbloccato.'));
    }

    private function authorizeView(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermission(Permission::ORDERS_MARKET_MANAGE)
            || $user->hasPermission(Permission::ORDERS_DELIVERY_MANAGE)
            || $user->hasPermission(Permission::COUNTER_USE),
            403,
        );
    }

    /**
     * Sblocco, consegna a domicilio e verifica manuale spettano solo al venditore.
     */
    private function authorizeSellerOnly(Request $request, User $customer): void
    {
        abort_unless($request->user()->isSeller(), 403);
        abort_unless($customer->isCustomer(), 404);
    }
}
