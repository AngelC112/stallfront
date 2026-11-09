<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\AvailabilityService;
use App\Services\CartSummaryService;
use App\Services\PickupMarketAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        private CartSummaryService $cartSummary,
        private AvailabilityService $availability,
        private PickupMarketAvailabilityService $pickupAvailability,
    ) {}

    public function index(Request $request): View
    {
        return $this->listing($request);
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);

        return $this->listing($request, $category);
    }

    private function listing(Request $request, ?Category $selectedCategory = null): View
    {
        $search = trim((string) $request->query('q', ''));

        $categories = Category::query()
            ->where('is_active', true)
            ->withCount(['products as storefront_products_count' => fn ($query) => $query
                ->where('is_active', true)
                ->where('is_visible', true)])
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->with(['category', 'photos'])
            ->where('is_active', true)
            ->where('is_visible', true)
            ->when($selectedCategory, fn ($query) => $query->where('category_id', $selectedCategory->id))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'LIKE', "%{$search}%")
                ->orWhere('short_description', 'LIKE', "%{$search}%")))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        // Stato mostrato sulla scheda: pulsante, etichetta e giorni di preparazione fuori stagione.
        $productStates = $products->getCollection()->mapWithKeys(fn (Product $product): array => [
            $product->id => [
                'orderable' => $this->availability->isProductCurrentlyOrderable($product),
                'soldOut' => $product->track_stock && $product->available_stock <= 0,
                'lowStock' => $product->track_stock && $product->available_stock > 0 && $product->isLowStock(),
                'offSeasonDays' => $this->availability->extraFulfillmentDays($product),
            ],
        ]);

        $cart = $this->cartSummary->summary($request);
        $pickups = $this->pickupAvailability->upcomingPickups(6);

        return view('storefront.index', [
            'categories' => $categories,
            'products' => $products,
            'productStates' => $productStates,
            'selectedCategory' => $selectedCategory,
            'search' => $search,
            'cart' => $cart,
            'cartCount' => $cart['count'],
            'nextPickup' => $pickups->firstWhere('closed_reason', null),
            'pickups' => $pickups,
        ]);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active && $product->is_visible, 404);

        $product->load([
            'category',
            'photos',
            'optionTypes.values',
            'activeVariants.optionValues.optionType',
            'activeVariants.photo',
        ]);

        $optionGroups = $product->optionTypes
            ->map(fn ($optionType) => [
                'id' => $optionType->id,
                'name' => $optionType->name,
                'values' => $optionType->values
                    ->map(fn ($value) => [
                        'id' => $value->id,
                        'label' => $value->value,
                    ])
                    ->values(),
            ])
            ->values();

        $variants = $product->activeVariants
            ->map(fn ($variant) => [
                'id' => $variant->id,
                'label' => $variant->optionValues
                    ->map(fn ($value) => ($value->optionType?->name ? $value->optionType->name.': ' : '').$value->value)
                    ->implode(' · '),
                'options' => $variant->optionValues
                    ->mapWithKeys(fn ($value) => [(string) $value->option_type_id => (int) $value->id]),
                'image' => $variant->photo?->product_id === $product->id
                    ? $variant->photo->url
                    : $product->coverPhotoUrl(),
                'priceCents' => $variant->price_cents_or_default,
                'availableStock' => $variant->available_stock,
            ])
            ->values();

        $relatedProducts = Product::query()
            ->with(['category', 'photos'])
            ->where('is_active', true)
            ->where('is_visible', true)
            ->when($product->category_id, fn ($query) => $query->where('category_id', $product->category_id))
            ->whereKeyNot($product->id)
            ->orderBy('name')
            ->limit(4)
            ->get();

        $cartCount = collect(session('cart', []))->sum('quantity');

        return view('storefront.show', compact('product', 'relatedProducts', 'variants', 'optionGroups', 'cartCount'));
    }
}
