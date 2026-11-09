<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class CartPersistenceService
{
    public function restoreForUser(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $this->persist($request, $request->session()->get('cart', []));
        }

        $cart = $this->normalize($user->cart ?? []);
        $guestCart = $this->normalize($request->session()->get('cart', []));

        foreach ($guestCart as $lineKey => $guestLine) {
            if (isset($cart[$lineKey])) {
                $cart[$lineKey]['quantity'] = min(99, $cart[$lineKey]['quantity'] + $guestLine['quantity']);

                continue;
            }

            $cart[$lineKey] = $guestLine;
        }

        return $this->persist($request, $cart);
    }

    public function persist(Request $request, mixed $cart): array
    {
        $cart = $this->normalize($cart);
        $request->session()->put('cart', $cart);

        $user = $request->user();

        if ($user instanceof User) {
            $user->forceFill(['cart' => $cart])->save();
        }

        return $cart;
    }

    private function normalize(mixed $cart): array
    {
        if (! is_array($cart)) {
            return [];
        }

        $normalized = [];

        foreach ($cart as $line) {
            if (! is_array($line)) {
                continue;
            }

            $productId = (int) ($line['product_id'] ?? 0);

            if ($productId < 1) {
                continue;
            }

            $variantId = ! empty($line['variant_id']) ? (int) $line['variant_id'] : null;
            $lineKey = $productId.':'.($variantId ?? 0);
            $quantity = min(99, max(1, (int) ($line['quantity'] ?? 1)));

            if (isset($normalized[$lineKey])) {
                $normalized[$lineKey]['quantity'] = min(99, $normalized[$lineKey]['quantity'] + $quantity);

                continue;
            }

            $normalized[$lineKey] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }
}
