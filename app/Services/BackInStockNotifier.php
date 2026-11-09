<?php

namespace App\Services;

use App\Mail\BackInStockMail;
use App\Models\BackInStockSubscription;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Mail;

class BackInStockNotifier
{
    public function notifyAvailable(Product $product): int
    {
        if (! $product->track_stock) {
            return 0;
        }

        $product->loadMissing(['activeVariants.optionValues.optionType']);
        $sent = 0;

        BackInStockSubscription::query()
            ->where('product_id', $product->id)
            ->each(function (BackInStockSubscription $subscription) use ($product, &$sent): void {
                $variant = $subscription->variant_key === ''
                    ? null
                    : $this->matchingVariant($product, $subscription->variant_key);

                if ($subscription->variant_key !== '' && ! $variant) {
                    return;
                }

                $availableStock = $variant?->available_stock ?? $product->available_stock;

                if ($availableStock <= 0) {
                    return;
                }

                Mail::to($subscription->email)->locale(\App\Support\Locales::normalize($subscription->locale) ?? \App\Support\Locales::fallback())->send(new BackInStockMail(
                    $product->name,
                    route('storefront.product', ['product' => $product]),
                    $subscription->variant_label,
                ));

                $subscription->delete();
                $sent++;
            });

        return $sent;
    }

    private function matchingVariant(Product $product, string $variantKey): ?ProductVariant
    {
        return $product->activeVariants->first(function (ProductVariant $variant) use ($variantKey): bool {
            $label = $variant->optionValues
                ->map(fn ($value) => ($value->optionType?->name ? $value->optionType->name.': ' : '').$value->value)
                ->implode(' · ');

            return mb_strtolower($label) === $variantKey;
        });
    }
}
