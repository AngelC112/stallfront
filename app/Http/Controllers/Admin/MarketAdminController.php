<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MarketAdminController extends Controller
{
    public function index(): View
    {
        $markets = Market::query()
            ->withCount('orders')
            ->orderBy('weekday')
            ->orderBy('name')
            ->get();

        return view('admin.markets.index', compact('markets'));
    }

    public function create(): View
    {
        return view('admin.markets.form', ['market' => new Market]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateMarket($request);
        $validated['is_active'] = $request->boolean('is_active');
        Market::create($validated);

        return redirect()->route('admin.markets.index')->with('success', __('Mercato creato.'));
    }

    public function edit(Market $market): View
    {
        return view('admin.markets.form', compact('market'));
    }

    public function update(Request $request, Market $market): RedirectResponse
    {
        $market->update([
            ...$this->validateMarket($request),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('admin.markets.index')->with('success', __('Mercato aggiornato.'));
    }

    public function destroy(Market $market): RedirectResponse
    {
        if ($market->orders()->exists()) {
            throw ValidationException::withMessages([
                'market' => __('Questo mercato è collegato a ordini e non può essere eliminato. Disattivalo invece.'),
            ]);
        }

        $market->delete();

        return redirect()->route('admin.markets.index')->with('success', __('Mercato eliminato.'));
    }

    private function validateMarket(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'location' => ['required', 'string', 'max:255'],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'minimum_order_days' => ['required', 'integer', 'between:0,60'],
            'order_cutoff_time' => ['required', 'date_format:H:i'],
            'pickup_cutoff_time' => ['required', 'date_format:H:i'],
        ], [
            'name.required' => __('Inserisci il nome del mercato.'),
            'location.required' => __('Inserisci il luogo del mercato.'),
            'weekday.required' => __('Seleziona il giorno settimanale.'),
            'order_cutoff_time.required' => __('Imposta l’orario limite per ordinare.'),
            'pickup_cutoff_time.required' => __('Imposta l’orario limite per il ritiro.'),
        ]);

        return $validated;
    }
}
