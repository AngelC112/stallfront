<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderStatusService;
use App\Support\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Modalità banco: ricerca rapida (testo o QR) e due sole azioni, ritirato o non ritirato.
 */
class BancoController extends Controller
{
    private const COUNTER_STATUSES = [Order::STATUS_COLLECTED, Order::STATUS_NOT_COLLECTED];

    /**
     * Stati mostrati nell’elenco del giorno: da consegnare e già gestiti al banco.
     */
    private const DAY_STATUSES = [
        Order::STATUS_RECEIVED,
        Order::STATUS_PREPARING,
        Order::STATUS_READY,
        Order::STATUS_RESCHEDULED,
        Order::STATUS_COLLECTED,
        Order::STATUS_NOT_COLLECTED,
    ];

    /**
     * Elenco dei ritiri del giorno scelto (oggi per default), filtrabile per mercato.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'market' => ['nullable', 'integer'],
        ]);

        $date = isset($validated['date']) ? Carbon::createFromFormat('Y-m-d', $validated['date'])->startOfDay() : today();
        $marketId = $validated['market'] ?? null;

        $dayOrders = Order::query()
            ->with(['items', 'notes.author'])
            ->where('fulfillment_method', Order::FULFILLMENT_PICKUP)
            ->whereDate('pickup_date', $date)
            ->whereIn('status', self::DAY_STATUSES)
            ->when($marketId, fn ($query) => $query->where('market_id', $marketId))
            ->orderBy('market_name')
            ->orderBy('customer_name')
            ->get();

        $markets = Market::query()->orderBy('name')->get(['id', 'name']);

        return view('admin.banco.index', [
            'date' => $date,
            'marketId' => $marketId,
            'markets' => $markets,
            'dayOrders' => $dayOrders->map(fn (Order $order): array => $this->payload($order, $request->user()))->values(),
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if ($term === '') {
            return response()->json(['results' => []]);
        }

        $digits = preg_replace('/\D+/', '', $term);

        $orders = Order::query()
            ->with(['items', 'notes.author'])
            ->where(function ($query) use ($term, $digits) {
                $query->where('order_number', 'LIKE', "%{$term}%")
                    ->orWhere('customer_name', 'LIKE', "%{$term}%")
                    ->orWhere('customer_email', 'LIKE', "%{$term}%")
                    ->orWhere('customer_phone', 'LIKE', "%{$term}%");

                if (strlen($digits) >= 4) {
                    $query->orWhere('customer_phone', 'LIKE', "%{$digits}%");
                }
            })
            ->latest('placed_at')
            ->limit(10)
            ->get()
            ->map(fn (Order $order): array => $this->payload($order, $request->user()));

        return response()->json(['results' => $orders]);
    }

    public function updateStatus(Request $request, Order $order, OrderStatusService $statusService): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::COUNTER_STATUSES)],
        ]);

        $statusService->transition($order, $validated['status'], OrderStatusService::ACTOR_STAFF, $request->user());

        return response()->json(['order' => $this->payload($order->fresh(['items', 'notes.author']), $request->user())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Order $order, User $user): array
    {
        $statusService = app(OrderStatusService::class);
        $canSeeNotes = $user->hasPermission(Permission::ORDERS_NOTES_MANAGE);
        $isPickup = $order->fulfillment_method === Order::FULFILLMENT_PICKUP;

        $actions = $isPickup
            ? array_values(array_filter(
                self::COUNTER_STATUSES,
                fn (string $status): bool => $statusService->canTransition($order, $status, OrderStatusService::ACTOR_STAFF),
            ))
            : [];

        $canOpenDetail = $user->hasPermission($isPickup ? Permission::ORDERS_MARKET_MANAGE : Permission::ORDERS_DELIVERY_MANAGE);

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'status' => $order->status,
            'status_label' => $order->statusLabel(),
            'fulfillment' => $order->fulfillmentMethodLabel(),
            'market' => $order->market_name,
            'market_id' => $order->market_id,
            'pickup_date' => $order->pickup_date?->translatedFormat('D j M'),
            'total' => \App\Support\Format::money($order->total_cents),
            'paid' => $order->payment_status === 'paid',
            'items' => $order->items->map(fn ($item): array => [
                'quantity' => $item->quantity,
                'name' => $item->product_name,
                'variant' => $item->variant_label,
            ])->all(),
            'customer_note' => $order->customer_note,
            'internal_notes' => $canSeeNotes ? $order->notes->pluck('body')->all() : [],
            'actions' => $actions,
            'url' => $canOpenDetail ? route('admin.orders.show', $order) : null,
        ];
    }
}
