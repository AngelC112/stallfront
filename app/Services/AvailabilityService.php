<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;

class AvailabilityService
{
    public function isProductInSeason(Product $product): bool
    {
        if ($product->season_behavior === Product::SEASON_PURCHASABLE_LONGER) {
            return true;
        }

        if ($product->always_available) {
            return true;
        }

        if (! $product->category instanceof Category) {
            return true;
        }

        return $product->category->isInSeason();
    }

    public function fulfillmentDays(Product $product): int
    {
        $base = (int) Setting::get('orders.market_default_lead_time_days', 3);

        return $base + $this->extraFulfillmentDays($product);
    }

    public function extraFulfillmentDays(Product $product): int
    {
        $category = $product->category;

        if (! $category || $category->always_available || $category->isInSeason()) {
            return 0;
        }

        $usesLongerLeadTime = $product->season_behavior === Product::SEASON_PURCHASABLE_LONGER
            || ($product->season_behavior === Product::SEASON_FOLLOW && $category->off_season_behavior === 'long');

        if (! $usesLongerLeadTime) {
            return 0;
        }

        return (int) ($product->own_fulfillment_days
            ?? $product->off_season_fulfillment_days
            ?? $category->off_season_days
            ?? Setting::get('orders.off_season_default_days', 10));
    }

    public function isProductCurrentlyOrderable(Product $product): bool
    {
        if ($product->visibility !== Product::VISIBILITY_VISIBLE) {
            return false;
        }

        $category = $product->category;
        $isCategoryOutOfSeason = $category instanceof Category
            && ! $category->always_available
            && ! $category->isInSeason();

        if (! $isCategoryOutOfSeason) {
            return $product->isOrderable();
        }

        if (in_array($product->season_behavior, [Product::SEASON_HIDDEN, Product::SEASON_VISIBLE_NOT_PURCHASABLE], true)) {
            return false;
        }

        if ($product->season_behavior === Product::SEASON_FOLLOW
            && $category->off_season_behavior !== 'long') {
            return false;
        }

        return $product->isOrderable();
    }
}
