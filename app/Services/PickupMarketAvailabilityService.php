<?php

namespace App\Services;

use App\Models\Market;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class PickupMarketAvailabilityService
{
    private const LOOKAHEAD_DAYS = 90;

    public function __construct(
        private AvailabilityService $availabilityService,
        private MarketClosureService $closureService,
    ) {}

    public function availableDates(array $cart, ?Carbon $now = null): Collection
    {
        $now = ($now ?? now())->copy();
        $productIds = collect($cart)->pluck('product_id')->filter()->unique();
        $products = Product::query()
            ->with(['category', 'activeVariants'])
            ->whereKey($productIds)
            ->get()
            ->keyBy('id');

        $extraLeadDays = 0;

        foreach ($cart as $line) {
            $product = $products->get((int) ($line['product_id'] ?? 0));
            $variant = ! empty($line['variant_id'])
                ? $product?->activeVariants->firstWhere('id', (int) $line['variant_id'])
                : null;

            if (! $product || ! $product->is_active || ! $product->is_visible
                || ! $this->availabilityService->isProductCurrentlyOrderable($product)
                || ($product->has_variants && ! $variant)) {
                return collect();
            }

            $quantity = max(1, (int) ($line['quantity'] ?? 1));
            $availableStock = ($variant?->available_stock ?? $product->available_stock)
                - app(StockReservationService::class)->reservedByOthers($product->id, $variant?->id, (int) Auth::id());

            if ($product->track_stock && $availableStock < $quantity) {
                return collect();
            }

            $extraLeadDays = max($extraLeadDays, $this->availabilityService->extraFulfillmentDays($product));
        }

        if ($cart === []) {
            return collect();
        }

        return $this->datesForLeadDays($extraLeadDays, $now);
    }

    /**
     * Date selezionabili per spostare un ordine già confermato.
     * La giacenza è già stata scalata, quindi non viene ricontrollata.
     */
    public function availableDatesForOrder(Order $order, ?Carbon $now = null): Collection
    {
        $productIds = $order->items->pluck('product_id')->filter()->unique();
        $products = Product::query()->with('category')->whereKey($productIds)->get();

        $extraLeadDays = $products
            ->map(fn (Product $product): int => $this->availabilityService->extraFulfillmentDays($product))
            ->max() ?? 0;

        return $this->datesForLeadDays($extraLeadDays, ($now ?? now())->copy());
    }

    /**
     * Date di mercato che il cliente vede in grigio, con il motivo.
     */
    public function unavailableDates(array $cart, ?Carbon $now = null, int $days = 35): Collection
    {
        $now = ($now ?? now())->copy();
        $products = Product::query()->with('category')->whereKey(collect($cart)->pluck('product_id')->filter()->unique())->get();
        $extraLeadDays = (int) ($products->map(fn (Product $product): int => $this->availabilityService->extraFulfillmentDays($product))->max() ?? 0);

        $firstDate = $now->copy()->startOfDay();
        $closureReason = $this->closureService->reasonLookup($firstDate, $firstDate->copy()->addDays($days));
        $markets = Market::query()->active()->whereNotNull('order_cutoff_time')->orderBy('name')->get();
        $dates = collect();

        for ($offset = 0; $offset <= $days; $offset++) {
            $pickupDate = $firstDate->copy()->addDays($offset);

            foreach ($markets as $market) {
                if ($pickupDate->dayOfWeekIso !== $market->weekday) {
                    continue;
                }

                $closed = $closureReason($market->id, $pickupDate->toDateString());
                $deadline = $pickupDate->copy()
                    ->subDays($market->minimum_order_days + $extraLeadDays)
                    ->setTimeFromTimeString($market->order_cutoff_time);

                $reason = match (true) {
                    $closed !== null => 'Chiuso: '.$closed,
                    $now->greaterThan($deadline) && $extraLeadDays > 0 => __('Troppo presto per il prodotto fuori stagione'),
                    $now->greaterThan($deadline) => __('Termine per ordinare scaduto'),
                    default => null,
                };

                if ($reason !== null) {
                    $dates->push([
                        'market_name' => $market->name,
                        'pickup_date' => $pickupDate->toDateString(),
                        'reason' => $reason,
                    ]);
                }
            }
        }

        return $dates;
    }

    /**
     * Prossime date di mercato per cui si può ancora ordinare, comprese quelle chiuse (con il motivo),
     * per la fascia "Prossimo ritiro" della vetrina.
     *
     * @return Collection<int, array{market_name: string, pickup_date: Carbon, deadline: Carbon, closed_reason: ?string}>
     */
    public function upcomingPickups(int $limit = 4, ?Carbon $now = null): Collection
    {
        $now = ($now ?? now())->copy();
        $firstDate = $now->copy()->startOfDay();
        $closureReason = $this->closureService->reasonLookup($firstDate, $firstDate->copy()->addDays(self::LOOKAHEAD_DAYS));
        $markets = Market::query()->active()->whereNotNull('order_cutoff_time')->orderBy('name')->get();
        $dates = collect();

        for ($offset = 0; $offset <= self::LOOKAHEAD_DAYS && $dates->count() < $limit; $offset++) {
            $pickupDate = $firstDate->copy()->addDays($offset);

            foreach ($markets as $market) {
                if ($pickupDate->dayOfWeekIso !== $market->weekday) {
                    continue;
                }

                $deadline = $pickupDate->copy()
                    ->subDays($market->minimum_order_days)
                    ->setTimeFromTimeString($market->order_cutoff_time);

                if ($now->greaterThan($deadline)) {
                    continue;
                }

                $dates->push([
                    'market_name' => $market->name,
                    'pickup_date' => $pickupDate,
                    'deadline' => $deadline,
                    'closed_reason' => $closureReason($market->id, $pickupDate->toDateString()),
                ]);
            }
        }

        return $dates->take($limit)->values();
    }

    private function datesForLeadDays(int $extraLeadDays, Carbon $now): Collection
    {
        $markets = Market::query()
            ->active()
            ->whereNotNull('order_cutoff_time')
            ->orderBy('weekday')
            ->orderBy('name')
            ->get();

        $dates = collect();
        $firstDate = $now->copy()->startOfDay();
        $closureReason = $this->closureService->reasonLookup($firstDate, $firstDate->copy()->addDays(self::LOOKAHEAD_DAYS));

        for ($offset = 0; $offset <= self::LOOKAHEAD_DAYS; $offset++) {
            $pickupDate = $firstDate->copy()->addDays($offset);

            foreach ($markets as $market) {
                if ($pickupDate->dayOfWeekIso !== $market->weekday) {
                    continue;
                }

                if ($closureReason($market->id, $pickupDate->toDateString()) !== null) {
                    continue;
                }

                $leadDays = $market->minimum_order_days + $extraLeadDays;
                $deadline = $pickupDate->copy()
                    ->subDays($leadDays)
                    ->setTimeFromTimeString($market->order_cutoff_time);

                if ($now->greaterThan($deadline)) {
                    continue;
                }

                $dates->push([
                    'market_id' => $market->id,
                    'market_name' => $market->name,
                    'location' => $market->location,
                    'weekday_label' => $market->weekdayLabel(),
                    'pickup_date' => $pickupDate->toDateString(),
                    'deadline' => $deadline,
                    'extra_lead_days' => $extraLeadDays,
                ]);
            }
        }

        return $dates->values();
    }
}
