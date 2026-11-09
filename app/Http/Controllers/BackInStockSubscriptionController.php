<?php

namespace App\Http\Controllers;

use App\Models\BackInStockSubscription;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BackInStockSubscriptionController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active && $product->is_visible, 404);

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        $variant = null;

        if ($product->has_variants) {
            $variant = $product->activeVariants()
                ->with('optionValues.optionType')
                ->whereKey($validated['variant_id'] ?? 0)
                ->first();

            if (! $variant) {
                throw ValidationException::withMessages(['variant_id' => __('Seleziona una variante valida.')]);
            }
        }

        $availableStock = $variant?->available_stock ?? $product->available_stock;

        if (! $product->track_stock || $availableStock > 0) {
            throw ValidationException::withMessages(['email' => __('Questo prodotto è disponibile e non richiede un avviso.')]);
        }

        $variantLabel = $variant?->optionValues
            ->map(fn ($value) => ($value->optionType?->name ? $value->optionType->name.': ' : '').$value->value)
            ->implode(' · ');

        BackInStockSubscription::query()->updateOrCreate(
            [
                'product_id' => $product->id,
                'email' => mb_strtolower($validated['email']),
                'variant_key' => mb_strtolower($variantLabel ?? ''),
            ],
            ['variant_label' => $variantLabel, 'locale' => app()->getLocale()],
        );

        return back()->with('status', __('Ti avviseremo quando questo articolo tornerà disponibile.'));
    }
}
