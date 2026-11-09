<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\AvailabilityService;
use App\Services\CartPersistenceService;
use App\Services\NotificationService;
use App\Services\OrderStatusService;
use App\Services\PickupMarketAvailabilityService;
use App\Support\NotificationEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        private OrderStatusService $statusService,
        private PickupMarketAvailabilityService $pickupAvailability,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): View
    {
        $orders = $request->user()
            ->orders()
            ->withCount('items')
            ->latest('placed_at')
            ->paginate(10);

        return view('account.orders.index', compact('orders'));
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeOwner($request, $order);

        $order->load('items');
        $canChangeDate = $this->dateChangeTarget($order) !== null;

        return view('account.orders.show', [
            'order' => $order,
            'canCancel' => $this->statusService->canTransition($order, Order::STATUS_CANCELLED, OrderStatusService::ACTOR_CUSTOMER),
            'canChangeDate' => $canChangeDate,
            'dateOptions' => $canChangeDate ? $this->pickupAvailability->availableDatesForOrder($order) : collect(),
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        $previousStatus = $order->status;

        $this->statusService->transition(
            $order,
            Order::STATUS_CANCELLED,
            OrderStatusService::ACTOR_CUSTOMER,
            $request->user(),
            __('Annullato dal cliente'),
        );

        $this->notifySellerIfInProgress($order, $previousStatus, __('ha annullato l’ordine'));

        return redirect()
            ->route('account.orders.show', ['order' => $order->order_number])
            ->with('success', __('Ordine annullato.'));
    }

    public function reschedule(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        $target = $this->dateChangeTarget($order);

        if ($target === null) {
            throw ValidationException::withMessages([
                'pickup_option' => __('Per questo ordine non si può cambiare la data.'),
            ]);
        }

        $validated = $request->validate([
            'pickup_option' => ['required', 'string', 'max:40'],
        ], [
            'pickup_option.required' => __('Seleziona un mercato e una data di ritiro.'),
        ]);

        [$marketId, $date] = array_pad(explode('|', $validated['pickup_option'], 2), 2, null);

        $choice = $this->pickupAvailability
            ->availableDatesForOrder($order)
            ->first(fn (array $option): bool => (string) $option['market_id'] === (string) $marketId && $option['pickup_date'] === $date);

        if (! $choice) {
            throw ValidationException::withMessages([
                'pickup_option' => __('La data selezionata non è più disponibile. Scegli un’altra data.'),
            ]);
        }

        $attributes = [
            'market_id' => $choice['market_id'],
            'market_name' => $choice['market_name'],
            'market_location' => $choice['location'],
            'pickup_date' => $choice['pickup_date'],
        ];
        $reason = __('Nuova data scelta dal cliente: :market il :date', ['market' => $choice['market_name'], 'date' => \App\Support\Format::date($choice['pickup_date'])]);
        $previousStatus = $order->status;

        if ($target === 'same') {
            $this->statusService->changePickup($order, OrderStatusService::ACTOR_CUSTOMER, $request->user(), $reason, $attributes);
        } else {
            $this->statusService->transition($order, $target, OrderStatusService::ACTOR_CUSTOMER, $request->user(), $reason, $attributes);
        }

        $this->notifySellerIfInProgress($order, $previousStatus, __('ha cambiato data di ritiro in :market il :date', ['market' => $choice['market_name'], 'date' => \App\Support\Format::date($choice['pickup_date'])]));

        return redirect()
            ->route('account.orders.show', ['order' => $order->order_number])
            ->with('success', __('Data di ritiro aggiornata.'));
    }

    /**
     * Rimette nel carrello i prodotti di un ordine passato, se ancora acquistabili.
     */
    public function reorder(Request $request, Order $order, CartPersistenceService $cart, AvailabilityService $availability): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        $lines = $cart->persist($request, $request->session()->get('cart', []));
        $added = 0;
        $skipped = 0;

        foreach ($order->items as $item) {
            $product = $item->product_id ? Product::query()->with('activeVariants')->find($item->product_id) : null;
            $variant = $item->variant_id ? $product?->activeVariants->firstWhere('id', $item->variant_id) : null;

            if (! $product || ! $product->is_active || ! $product->is_visible
                || ! $availability->isProductCurrentlyOrderable($product)
                || ($product->has_variants && ! $variant)) {
                $skipped++;

                continue;
            }

            $quantity = $item->quantity;

            if ($product->track_stock) {
                $quantity = min($quantity, $variant?->available_stock ?? $product->available_stock);
            }

            if ($quantity < 1) {
                $skipped++;

                continue;
            }

            $key = $product->id.':'.($variant?->id ?? 0);
            $lines[$key] = ['product_id' => $product->id, 'variant_id' => $variant?->id, 'quantity' => $quantity];
            $added++;
        }

        $cart->persist($request, $lines);

        $message = $added > 0
            ? trans_choice(':count prodotto aggiunto al carrello.|:count prodotti aggiunti al carrello.', $added).($skipped ? ' '.trans_choice(':count non è più disponibile.|:count non sono più disponibili.', $skipped) : '')
            : __('I prodotti di questo ordine non sono più disponibili.');

        return redirect()->route('cart.index')->with('status', $message);
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }

    /**
     * Come si sposta la data: 'same' = stesso stato, altrimenti lo stato di arrivo. Null = non consentito.
     */
    private function dateChangeTarget(Order $order): ?string
    {
        if ($order->fulfillment_method !== Order::FULFILLMENT_PICKUP) {
            return null;
        }

        return match ($order->status) {
            Order::STATUS_RECEIVED, Order::STATUS_PREPARING, Order::STATUS_READY => 'same',
            Order::STATUS_TO_RESCHEDULE => Order::STATUS_RECEIVED,
            Order::STATUS_NOT_COLLECTED => Order::STATUS_RESCHEDULED,
            default => null,
        };
    }

    /**
     * Il venditore va avvisato solo se l’ordine era già in preparazione o evaso.
     */
    private function notifySellerIfInProgress(Order $order, string $previousStatus, string $action): void
    {
        if (! in_array($previousStatus, [Order::STATUS_PREPARING, Order::STATUS_READY], true)) {
            return;
        }

        $this->notifications->queueForOrder(NotificationEvent::SELLER_ORDER_CHANGED, $order->fresh(), ['motivazione' => $action]);
    }
}
