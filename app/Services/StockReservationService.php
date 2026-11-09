<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\StockReservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Riserva per pochi minuti la giacenza durante il checkout, così l’ultimo pezzo non si vende due volte.
 */
class StockReservationService
{
    public const DEFAULT_MINUTES = 15;

    public function minutes(): int
    {
        return max(1, (int) Setting::get('orders.checkout_reserve_minutes', self::DEFAULT_MINUTES));
    }

    /**
     * Pezzi trattenuti da altri clienti con una riserva ancora valida.
     */
    public function reservedByOthers(int $productId, ?int $variantId, int $userId): int
    {
        return (int) StockReservation::query()
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->where('user_id', '!=', $userId)
            ->where('expires_at', '>', now())
            ->sum('quantity');
    }

    /**
     * Riserva i prodotti del carrello. Sostituisce la riserva precedente dello stesso cliente.
     *
     * @param  array<string, array{product_id: int, variant_id: ?int, quantity: int}>  $cart
     *
     * @throws ValidationException se un prodotto non ha abbastanza giacenza libera
     */
    public function reserveCart(User $user, array $cart): Carbon
    {
        $expiresAt = now()->addMinutes($this->minutes());

        DB::transaction(function () use ($user, $cart, $expiresAt): void {
            StockReservation::query()->where('user_id', $user->id)->delete();

            foreach ($cart as $line) {
                $product = Product::query()->lockForUpdate()->find($line['product_id']);

                if (! $product || ! $product->track_stock) {
                    continue;
                }

                $variantId = ! empty($line['variant_id']) ? (int) $line['variant_id'] : null;
                $variant = $variantId ? ProductVariant::query()->lockForUpdate()->find($variantId) : null;

                $free = ($variant?->available_stock ?? $product->available_stock)
                    - $this->reservedByOthers($product->id, $variant?->id, $user->id);

                if ($free < (int) $line['quantity']) {
                    throw ValidationException::withMessages([
                        'cart' => __('«:product» non è più disponibile nella quantità richiesta: qualcun altro lo sta acquistando. Aggiorna il carrello.', ['product' => $product->name]),
                    ]);
                }

                StockReservation::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'quantity' => (int) $line['quantity'],
                    'expires_at' => $expiresAt,
                ]);
            }
        });

        return $expiresAt;
    }

    public function release(User $user): void
    {
        StockReservation::query()->where('user_id', $user->id)->delete();
    }

    public function pruneExpired(): int
    {
        return StockReservation::query()->where('expires_at', '<', now())->delete();
    }
}
