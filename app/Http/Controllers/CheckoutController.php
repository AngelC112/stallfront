<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Services\AvailabilityService;
use App\Services\CartPersistenceService;
use App\Services\NotificationService;
use App\Services\OrderStatusService;
use App\Services\PickupMarketAvailabilityService;
use App\Services\StockReservationService;
use App\Support\NotificationEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private CartPersistenceService $cartPersistence,
        private AvailabilityService $availabilityService,
        private PickupMarketAvailabilityService $pickupMarketAvailability,
        private StockReservationService $reservations,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        if (! $request->user()->canOrder()) {
            return redirect()->route('cart.index')->with('status', __('Il tuo account è bloccato per mancati ritiri: non puoi effettuare nuovi ordini. Contatta il venditore.'));
        }

        $cart = $this->cartPersistence->persist($request, $request->session()->get('cart', []));
        $productIds = collect($cart)->pluck('product_id')->unique();
        $products = Product::query()
            ->with(['category', 'photos', 'activeVariants.optionValues.optionType', 'activeVariants.photo'])
            ->where('is_active', true)
            ->where('is_visible', true)
            ->whereKey($productIds)
            ->get()
            ->keyBy('id');

        $items = collect();
        $validCart = [];
        $subtotalCents = 0;

        foreach ($cart as $lineKey => $line) {
            $product = $products->get((int) $line['product_id']);
            $variant = ! empty($line['variant_id'])
                ? $product?->activeVariants->firstWhere('id', (int) $line['variant_id'])
                : null;

            if (! $product || ($product->has_variants && ! $variant)) {
                continue;
            }

            $quantity = (int) $line['quantity'];
            $priceCents = $variant?->price_cents_or_default ?? $product->price_cents;
            $lineTotalCents = $priceCents * $quantity;
            $subtotalCents += $lineTotalCents;
            $validCart[$lineKey] = $line;

            $items->push([
                'product' => $product,
                'variantLabel' => $variant?->optionValues
                    ->map(fn ($value) => ($value->optionType?->name ? $value->optionType->name.': ' : '').$value->value)
                    ->implode(' · '),
                'image' => $variant?->photo?->url ?? $product->coverPhotoUrl(),
                'quantity' => $quantity,
                'unitPriceCents' => $priceCents,
                'lineTotalCents' => $lineTotalCents,
                'availableStock' => $product->track_stock
                    ? ($variant?->available_stock ?? $product->available_stock)
                    : null,
            ]);
        }

        $this->cartPersistence->persist($request, $validCart);

        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('status', __('Il carrello è vuoto o i prodotti non sono più disponibili.'));
        }

        try {
            $reservedUntil = $this->reservations->reserveCart($request->user(), $validCart);
        } catch (ValidationException $exception) {
            return redirect()->route('cart.index')->with('status', $exception->validator->errors()->first());
        }

        // La consegna a domicilio richiede l'abilitazione del venditore e il cellulare verificato.
        $canDeliver = $request->user()->delivery_enabled && $request->user()->hasPhoneVerified() && Setting::get('delivery.enabled', true);
        $marketDates = $this->pickupMarketAvailability->availableDates($validCart);

        return view('storefront.checkout', [
            'items' => $items,
            'subtotalCents' => $subtotalCents,
            'deliveryCostCents' => (int) Setting::get('delivery.cost_cents', 0),
            'deliveryMinCents' => (int) Setting::get('delivery.min_order_cents', 0),
            'canDeliver' => $canDeliver,
            'marketDates' => $marketDates,
            'unavailableDates' => $this->pickupMarketAvailability->unavailableDates($validCart),
            'reservedUntil' => $reservedUntil,
            'customer' => $request->user(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->canOrder()) {
            return redirect()->route('cart.index')->with('status', __('Il tuo account è bloccato per mancati ritiri: non puoi effettuare nuovi ordini. Contatta il venditore.'));
        }

        $validated = $request->validate([
            'fulfillment_method' => ['required', 'in:pickup,delivery'],
            'pickup_option' => ['required_if:fulfillment_method,pickup', 'nullable', 'string', 'max:40'],
            'delivery_address' => ['required_if:fulfillment_method,delivery', 'nullable', 'string', 'max:1000'],
            'delivery_phone' => ['required_if:fulfillment_method,delivery', 'nullable', 'string', 'max:30'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
        ], [
            'pickup_option.required_if' => __('Seleziona un mercato e una data di ritiro.'),
            'delivery_address.required_if' => __('Inserisci l’indirizzo di consegna.'),
            'delivery_phone.required_if' => __('Inserisci un numero di telefono per la consegna.'),
        ]);

        $user = $request->user();
        $isDelivery = $validated['fulfillment_method'] === Order::FULFILLMENT_DELIVERY;

        if ($isDelivery && (! $user->delivery_enabled || ! $user->hasPhoneVerified() || ! Setting::get('delivery.enabled', true))) {
            throw ValidationException::withMessages([
                'fulfillment_method' => __('La consegna a domicilio non è abilitata per il tuo account.'),
            ]);
        }

        $cart = $this->cartPersistence->persist($request, $request->session()->get('cart', []));

        if ($cart === []) {
            return redirect()->route('cart.index')->with('status', __('Il carrello è vuoto.'));
        }

        $selectedMarketDate = null;

        if (! $isDelivery) {
            $selection = explode('|', $validated['pickup_option'], 2);

            if (count($selection) !== 2 || ! ctype_digit($selection[0])) {
                throw ValidationException::withMessages(['pickup_option' => __('Seleziona una data di ritiro valida.')]);
            }

            $selectedMarketDate = $this->pickupMarketAvailability
                ->availableDates($cart)
                ->first(fn (array $marketDate): bool => $marketDate['market_id'] === (int) $selection[0]
                    && $marketDate['pickup_date'] === $selection[1]
                );

            if (! $selectedMarketDate) {
                throw ValidationException::withMessages([
                    'pickup_option' => __('La data selezionata non è più disponibile. Scegli un’altra data.'),
                ]);
            }
        }

        $deliveryCostCents = $isDelivery ? (int) Setting::get('delivery.cost_cents', 0) : 0;
        $deliveryMinCents = $isDelivery ? (int) Setting::get('delivery.min_order_cents', 0) : 0;

        $order = DB::transaction(function () use ($cart, $deliveryCostCents, $deliveryMinCents, $isDelivery, $selectedMarketDate, $user, $validated): Order {
            $orderLines = [];
            $subtotalCents = 0;

            foreach ($cart as $line) {
                $product = Product::query()
                    ->with('category')
                    ->lockForUpdate()
                    ->find($line['product_id']);

                if (! $product || ! $product->is_active || ! $product->is_visible || ! $this->availabilityService->isProductCurrentlyOrderable($product)) {
                    throw ValidationException::withMessages([
                        'cart' => __('Uno o più prodotti non sono più disponibili per l’ordine.'),
                    ]);
                }

                $variant = null;

                if ($product->has_variants) {
                    $variant = ProductVariant::query()
                        ->with('optionValues.optionType')
                        ->where('product_id', $product->id)
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->find($line['variant_id'] ?? null);

                    if (! $variant) {
                        throw ValidationException::withMessages([
                            'cart' => __('Una variante selezionata non è più disponibile.'),
                        ]);
                    }
                }

                $quantity = (int) $line['quantity'];
                $availableStock = ($variant?->available_stock ?? $product->available_stock)
                    - $this->reservations->reservedByOthers($product->id, $variant?->id, $user->id);

                if ($product->track_stock && $availableStock < $quantity) {
                    throw ValidationException::withMessages([
                        'cart' => __('La giacenza è cambiata: aggiorna il carrello prima di confermare.'),
                    ]);
                }

                $unitPriceCents = $variant?->price_cents_or_default ?? $product->price_cents;
                $lineTotalCents = $unitPriceCents * $quantity;
                $variantLabel = $variant?->optionValues
                    ->map(fn ($value) => ($value->optionType?->name ? $value->optionType->name.': ' : '').$value->value)
                    ->implode(' · ');
                $subtotalCents += $lineTotalCents;

                $orderLines[] = [
                    'product' => $product,
                    'variant' => $variant,
                    'variant_label' => $variantLabel,
                    'quantity' => $quantity,
                    'unit_price_cents' => $unitPriceCents,
                    'line_total_cents' => $lineTotalCents,
                ];
            }

            if ($isDelivery && $subtotalCents < $deliveryMinCents) {
                throw ValidationException::withMessages([
                    'fulfillment_method' => __('Il totale del carrello non raggiunge il minimo per la consegna a domicilio.'),
                ]);
            }

            $order = Order::create([
                'order_number' => $this->newOrderNumber(),
                'user_id' => $user->id,
                'market_id' => $selectedMarketDate['market_id'] ?? null,
                'market_name' => $selectedMarketDate['market_name'] ?? null,
                'market_location' => $selectedMarketDate['location'] ?? null,
                'customer_name' => $user->fullName(),
                'customer_email' => $user->email,
                'customer_phone' => $isDelivery ? $validated['delivery_phone'] : $user->phone,
                'fulfillment_method' => $validated['fulfillment_method'],
                'payment_method' => $isDelivery ? 'cash_on_delivery' : 'cash_on_pickup',
                'payment_status' => 'unpaid',
                'status' => Order::STATUS_RECEIVED,
                'pickup_date' => $isDelivery ? null : $selectedMarketDate['pickup_date'],
                'delivery_address' => $isDelivery ? $validated['delivery_address'] : null,
                'customer_note' => $validated['customer_note'] ?? null,
                'extra_season_days' => $isDelivery ? 0 : (int) ($selectedMarketDate['extra_lead_days'] ?? 0),
                'items_subtotal_cents' => $subtotalCents,
                'delivery_cost_cents' => $deliveryCostCents,
                'total_cents' => $subtotalCents + $deliveryCostCents,
                'placed_at' => now(),
            ]);

            foreach ($orderLines as $line) {
                $product = $line['product'];
                $variant = $line['variant'];
                $quantity = $line['quantity'];

                if ($product->track_stock) {
                    if ($variant) {
                        $variant->decrement('stock', $quantity);
                    } else {
                        $wasLowStock = $product->isLowStock();
                        $product->decrement('stock', $quantity);

                        if (! $wasLowStock && $product->isLowStock()) {
                            app(NotificationService::class)->queueForSeller(NotificationEvent::SELLER_LOW_STOCK, ['prodotto' => $product->name]);
                        }
                    }

                    StockMovement::create([
                        'product_id' => $product->id,
                        'variant_id' => $variant?->id,
                        'user_id' => $user->id,
                        'delta' => -$quantity,
                        'reason' => StockMovement::REASON_ORDER,
                        'note' => 'Ordine '.$order->order_number,
                    ]);
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'variant_label' => $line['variant_label'],
                    'sku' => $variant?->sku,
                    'quantity' => $quantity,
                    'unit_price_cents' => $line['unit_price_cents'],
                    'line_total_cents' => $line['line_total_cents'],
                ]);
            }

            return $order;
        });

        $this->cartPersistence->persist($request, []);
        $this->reservations->release($user);
        app(OrderStatusService::class)->recordCreation($order, $user);
        app(NotificationService::class)->queueForOrder(NotificationEvent::SELLER_NEW_ORDER, $order);
        $order->load('items');
        Mail::to($order->customer_email)->send(new OrderConfirmationMail($order));

        return redirect()->route('account.orders.show', ['order' => $order->order_number]);
    }

    /**
     * Codice non sequenziale e non indovinabile (8 caratteri casuali, senza 0/O e 1/I).
     */
    private function newOrderNumber(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $random = '';

            for ($i = 0; $i < 8; $i++) {
                $random .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            $number = 'ORD-'.now()->format('ymd').'-'.$random;
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }
}
