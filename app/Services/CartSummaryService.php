<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Righe e totale del carrello in sessione, usati dalla pagina carrello e dal riepilogo in vetrina.
 * Le righe di prodotti non più disponibili vengono tolte dal carrello.
 */
class CartSummaryService
{
    public function __construct(
        private CartPersistenceService $cartPersistence,
        private AvailabilityService $availabilityService,
    ) {}

    /**
     * @return array{items: Collection<int, array<string, mixed>>, totalCents: int, count: int, offSeasonDays: int}
     */
    public function summary(Request $request): array
    {
        $cart = $this->cartPersistence->persist($request, $request->session()->get('cart', []));
        $productIds = collect($cart)->pluck('product_id')->filter()->unique();
        $products = $productIds->isEmpty() ? collect() : Product::query()
            ->with(['category', 'photos', 'activeVariants.optionValues.optionType', 'activeVariants.photo'])
            ->where('is_active', true)
            ->where('is_visible', true)
            ->whereKey($productIds)
            ->get()
            ->keyBy('id');

        $items = collect();
        $totalCents = 0;
        $validCart = [];

        foreach ($cart as $lineKey => $line) {
            $product = $products->get((int) ($line['product_id'] ?? 0));
            $variantId = (int) ($line['variant_id'] ?? 0);
            $variant = $variantId ? $product?->activeVariants->firstWhere('id', $variantId) : null;

            if (! $product || ($variantId && ! $variant)) {
                continue;
            }

            $quantity = max(1, (int) ($line['quantity'] ?? 1));
            $priceCents = $variant?->price_cents_or_default ?? $product->price_cents;
            $lineTotalCents = $priceCents * $quantity;
            $totalCents += $lineTotalCents;
            $validCart[$lineKey] = [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $quantity,
            ];

            $items->push([
                'key' => $lineKey,
                'product' => $product,
                'variant' => $variant,
                'variantLabel' => $variant?->optionValues
                    ->map(fn ($value) => ($value->optionType?->name ? $value->optionType->name.': ' : '').$value->value)
                    ->implode(' · '),
                'image' => $variant?->photo?->url ?? $product->coverPhotoUrl(),
                'quantity' => $quantity,
                'priceCents' => $priceCents,
                'lineTotalCents' => $lineTotalCents,
                'availableStock' => $product->track_stock
                    ? ($variant?->available_stock ?? $product->available_stock)
                    : null,
                'offSeasonDays' => $this->availabilityService->extraFulfillmentDays($product),
            ]);
        }

        $validCart = $this->cartPersistence->persist($request, $validCart);

        return [
            'items' => $items,
            'totalCents' => $totalCents,
            'count' => collect($validCart)->sum('quantity'),
            'offSeasonDays' => (int) ($items->max('offSeasonDays') ?? 0),
        ];
    }
}
