<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartPersistenceService;
use App\Services\CartSummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        private CartPersistenceService $cartPersistence,
        private CartSummaryService $cartSummary,
    ) {}

    public function index(Request $request): View
    {
        $summary = $this->cartSummary->summary($request);

        return view('storefront.cart', [
            'items' => $summary['items'],
            'totalCents' => $summary['totalCents'],
            'cartCount' => $summary['count'],
            'offSeasonDays' => $summary['offSeasonDays'],
        ]);
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        abort_unless($product->is_active && $product->is_visible, 404);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        $variant = null;

        if ($product->has_variants) {
            if (empty($validated['variant_id'])) {
                throw ValidationException::withMessages(['variant_id' => __('Seleziona una variante.')]);
            }

            $variant = $product->activeVariants()
                ->with('optionValues.optionType')
                ->whereKey($validated['variant_id'])
                ->first();

            if (! $variant) {
                throw ValidationException::withMessages(['variant_id' => __('La variante selezionata non è valida.')]);
            }
        }

        $lineKey = $product->id.':'.($variant?->id ?? 0);
        $cart = $this->cartPersistence->persist($request, $request->session()->get('cart', []));
        $quantity = (int) $validated['quantity'] + (int) ($cart[$lineKey]['quantity'] ?? 0);

        if ($product->track_stock) {
            $availableStock = $variant?->available_stock ?? $product->available_stock;

            if ($availableStock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => __('La quantità richiesta supera la disponibilità attuale.'),
                ]);
            }
        }

        $cart[$lineKey] = [
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'quantity' => $quantity,
        ];
        $this->cartPersistence->persist($request, $cart);

        // Dalla griglia della vetrina si resta sulla pagina: il carrello si aggiorna di lato.
        if ($request->boolean('stay')) {
            return back()->with('status', __('Prodotto aggiunto al carrello.'));
        }

        return redirect()->route('cart.index')->with('status', __('Prodotto aggiunto al carrello.'));
    }

    public function update(Request $request, string $lineKey): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cart = $this->cartPersistence->persist($request, $request->session()->get('cart', []));

        if (! isset($cart[$lineKey])) {
            abort(404);
        }

        $line = $cart[$lineKey];
        $product = Product::query()->with('activeVariants.optionValues.optionType')->findOrFail($line['product_id']);
        $variant = ! empty($line['variant_id'])
            ? $product->activeVariants->firstWhere('id', (int) $line['variant_id'])
            : null;

        if (! $product->is_active || ! $product->is_visible || (! empty($line['variant_id']) && ! $variant)) {
            unset($cart[$lineKey]);
            $this->cartPersistence->persist($request, $cart);

            return redirect()->route('cart.index')->with('status', __('Un prodotto non è più disponibile ed è stato rimosso.'));
        }

        if ($product->track_stock) {
            $availableStock = $variant?->available_stock ?? $product->available_stock;

            if ($availableStock < (int) $validated['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => __('La quantità richiesta supera la disponibilità attuale.'),
                ]);
            }
        }

        $cart[$lineKey]['quantity'] = (int) $validated['quantity'];
        $this->cartPersistence->persist($request, $cart);

        return redirect()->route('cart.index')->with('status', __('Carrello aggiornato.'));
    }

    public function destroy(Request $request, string $lineKey): RedirectResponse
    {
        $cart = $this->cartPersistence->persist($request, $request->session()->get('cart', []));
        unset($cart[$lineKey]);
        $this->cartPersistence->persist($request, $cart);

        return redirect()->route('cart.index')->with('status', __('Prodotto rimosso dal carrello.'));
    }
}
