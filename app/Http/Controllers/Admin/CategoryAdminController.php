<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\SlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryAdminController extends Controller
{
    public function index(): View
    {
        $categories = Category::query()
            ->withCount('products')
            ->orderBy('position')
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new Category]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);

        $validated['slug'] = SlugGenerator::unique(Category::class, $validated['name']);

        Category::create($validated);

        return redirect()->route('admin.categories.index')->with('success', __('Categoria creata.'));
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validateData($request);

        if ($category->name !== $validated['name']) {
            $validated['slug'] = SlugGenerator::unique(Category::class, $validated['name'], $category->id);
        }

        $category->update($validated);

        return redirect()->route('admin.categories.index')->with('success', __('Categoria aggiornata.'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', __('Categoria eliminata.'));
    }

    protected function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],

            // Logica stagionale
            'always_available' => ['boolean'],
            'season_start_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'season_start_month' => ['nullable', 'integer', 'between:1,12'],
            'season_start_day' => ['nullable', 'integer', 'between:1,31'],
            'season_end_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'season_end_month' => ['nullable', 'integer', 'between:1,12'],
            'season_end_day' => ['nullable', 'integer', 'between:1,31'],

            'off_season_behavior' => ['required_with:always_available,false', 'in:long,visible,hidden'],
            'off_season_days' => ['required_with:always_available,false', 'integer', 'min:0', 'max:99'],

            'position' => ['nullable', 'integer', 'min:0'],
        ], [
            'name.required' => __('Il nome è obbligatorio.'),
            'off_season_behavior.in' => __('Seleziona un comportamento valido per il fuori stagione.'),
            'off_season_days.min' => __('I giorni di evasione non possono essere negativi.'),
        ]);

        // Se "Sempre disponibile" è TRUE, azzeriamo i campi stagionali
        // per garantire coerenza nel DB (null invece di vecchi valori).
        if (! empty($data['always_available'])) {
            $data['season_start_year'] = null;
            $data['season_start_month'] = null;
            $data['season_start_day'] = null;
            $data['season_end_year'] = null;
            $data['season_end_month'] = null;
            $data['season_end_day'] = null;
            // Manteniamo off_season_behavior/days settati a default o null?
            // Meglio null se non serve, ma lasciamoli come sono se vuoi conservarli per riattivazione.
            // Per pulizia totale:
            $data['off_season_behavior'] = 'long';
            $data['off_season_days'] = 10;
        } else {
            // Se FALSE, assicuriamoci che ci siano almeno mese/giorno base
            // La validazione above richiede required_with, quindi se arriva qui è presente.
        }

        // Gestione checkbox is_active (se non spuntato non arriva nulla)
        $data['is_active'] = $request->has('is_active');
        $data['always_available'] = $request->has('always_available');

        return $data;
    }
}
