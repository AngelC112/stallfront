<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductOptionType;
use App\Models\ProductOptionValue;
use App\Models\ProductPhoto;
use App\Models\ProductVariant;
use App\Services\BackInStockNotifier;
use App\Support\SlugGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Intervention\Image\Laravel\Facades\Image;

class ProductAdminController extends Controller
{
    private const THUMB_WIDTH = 300;

    private const FULL_WIDTH = 1600;

    private const QUALITY = 82;

    /**
     * Filtri disponibili nell'elenco prodotti (chiave => etichetta).
     */
    public const FILTERS = [
        'all' => 'Tutti',
        'out_of_stock' => 'Esauriti',
        'low_stock' => 'Scorta bassa',
        'off_season' => 'Fuori stagione',
        'hidden' => 'Nascosti',
    ];

    public function index(Request $request): View
    {
        $filter = array_key_exists((string) $request->query('filter'), self::FILTERS)
            ? (string) $request->query('filter')
            : 'all';
        $search = trim((string) $request->query('q', ''));

        $query = Product::query()
            ->with('category')
            ->latest();

        if ($search !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like));
            });
        }

        if ($filter === 'hidden') {
            $query->where('visibility', Product::VISIBILITY_HIDDEN);
        }

        // Giacenza e stagione dipendono da varianti e date: si calcolano come nel resto del sito,
        // quindi questi filtri lavorano sull'elenco già ristretto dalla ricerca.
        if (in_array($filter, ['out_of_stock', 'low_stock', 'off_season'], true)) {
            $matching = $query->get()->filter(fn (Product $product) => match ($filter) {
                'out_of_stock' => $product->track_stock && $product->available_stock <= 0,
                'low_stock' => $product->track_stock && $product->available_stock > 0 && $product->isLowStock(),
                'off_season' => $this->isOffSeason($product),
            })->values();

            $perPage = 15;
            $page = LengthAwarePaginator::resolveCurrentPage();
            $products = new LengthAwarePaginator(
                $matching->forPage($page, $perPage)->values(),
                $matching->count(),
                $perPage,
                $page,
                ['path' => $request->url()],
            );
        } else {
            $products = $query->paginate(15);
        }

        $products->appends(array_filter(['q' => $search, 'filter' => $filter !== 'all' ? $filter : null]));

        return view('admin.products.index', [
            'products' => $products,
            'filters' => array_map(fn (string $label): string => __($label), self::FILTERS),
            'activeFilter' => $filter,
            'search' => $search,
        ]);
    }

    /**
     * Prodotto di una categoria con periodo di vendita, oggi fuori da quel periodo.
     */
    private function isOffSeason(Product $product): bool
    {
        $category = $product->category;

        if (! $category || $product->always_available || $category->always_available) {
            return false;
        }

        return ! $category->isInSeason();
    }

    public function create(): View
    {
        $categories = Category::active()->ordered()->pluck('name', 'id');

        return view('admin.products.form', [
            'product' => new Product,
            'categories' => $categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateProduct($request);

        $product = DB::transaction(function () use ($request, $validated) {
            $slug = SlugGenerator::unique(Product::class, $validated['name']);

            $product = Product::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'category_id' => $validated['category_id'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'price_cents' => $validated['price_cents'],
                'stock' => $validated['stock'] ?? 0,
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 3,
                'visibility' => $validated['visibility'],
                'season_behavior' => $validated['season_behavior'],
                'own_fulfillment_days' => $validated['own_fulfillment_days'],
                'created_by' => auth()->id(),
                'has_variants' => $request->boolean('has_variants'),
                'track_stock' => $request->boolean('track_stock'),
                'is_active' => $validated['visibility'] !== Product::VISIBILITY_HIDDEN,
                'is_visible' => $validated['visibility'] === Product::VISIBILITY_VISIBLE,
                'always_available' => $validated['season_behavior'] === Product::SEASON_PURCHASABLE_LONGER,
                'off_season_fulfillment_days' => $validated['own_fulfillment_days'],
            ]);

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $index => $file) {
                    $this->handleImageUpload($file, $product, false, $index);
                }
            }

            $this->applyCoverByIndex($request, $product);
            $this->syncVariants($request, $product);

            return $product;
        });

        return redirect()
            ->route('admin.products.edit', $product)
            ->with('success', __('Prodotto creato con successo.'));
    }

    public function edit(Product $product): View
    {
        $categories = Category::active()->ordered()->pluck('name', 'id');

        $product->load([
            'photos' => fn ($q) => $q->orderBy('position')->orderBy('id'),
            'optionTypes.values',
            'variants.optionValues',
        ]);

        $photosJs = $product->photos->map(fn (ProductPhoto $p) => [
            'id' => $p->id,
            'uid' => 'e'.$p->id,
            'url' => $p->thumbUrl(),
            'fullUrl' => $p->url,
            'position' => $p->position,
            'is_cover' => (bool) $p->is_cover,
        ])->values();

        $typesJs = $product->optionTypes
            ->sortBy([['position', 'asc'], ['id', 'asc']])
            ->map(fn ($t) => [
                'name' => $t->name,
                'values' => $t->values
                    ->sortBy([['position', 'asc'], ['id', 'asc']])
                    ->map(fn ($v) => ['value' => $v->value])
                    ->values(),
                'newValue' => '',
            ])->values();

        $variantsJs = $product->variants->map(fn (ProductVariant $v) => [
            'id' => $v->id,
            'label' => $v->optionValues->pluck('value')->implode(' · ') ?: __('Variante'),
            'parts' => $v->optionValues->pluck('value')->values(),
            'sku' => $v->sku ?? '',
            'priceExtra' => $v->price_cents ? (($v->price_cents - $product->price_cents) / 100) : 0,
            'stock' => $v->stock ?? 0,
            'always_available' => false,
            'photoId' => $v->photo_id,
            'photoIndex' => null,
        ])->values();

        return view('admin.products.form', [
            'product' => $product,
            'categories' => $categories,
            'photosJs' => $photosJs,
            'typesJs' => $typesJs,
            'variantsJs' => $variantsJs,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validateProduct($request, $product->id);
        $wasOrderable = $product->isOrderable();

        DB::transaction(function () use ($request, $validated, $product) {
            $attributes = [
                'name' => $validated['name'],
                'category_id' => $validated['category_id'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'price_cents' => $validated['price_cents'],
                'stock' => $validated['stock'] ?? 0,
                'low_stock_threshold' => $validated['low_stock_threshold'] ?? 3,
                'visibility' => $validated['visibility'],
                'season_behavior' => $validated['season_behavior'],
                'own_fulfillment_days' => $validated['own_fulfillment_days'],
                'has_variants' => $request->boolean('has_variants'),
                'track_stock' => $request->boolean('track_stock'),
                'is_active' => $validated['visibility'] !== Product::VISIBILITY_HIDDEN,
                'is_visible' => $validated['visibility'] === Product::VISIBILITY_VISIBLE,
                'always_available' => $validated['season_behavior'] === Product::SEASON_PURCHASABLE_LONGER,
                'off_season_fulfillment_days' => $validated['own_fulfillment_days'],
            ];

            if ($product->name !== $validated['name']) {
                $attributes['slug'] = SlugGenerator::unique(Product::class, $validated['name'], $product->id);
            }

            $product->update($attributes);

            if ($request->filled('remove_photo_ids')) {
                $ids = array_filter(array_map('intval', explode(',', (string) $request->input('remove_photo_ids'))));

                if ($ids) {
                    $toDelete = $product->photos()->whereIn('id', $ids)->get();

                    foreach ($toDelete as $photo) {
                        Storage::disk($photo->disk)->delete($photo->path);

                        if ($photo->thumb_path) {
                            Storage::disk($photo->disk)->delete($photo->thumb_path);
                        }
                    }

                    $product->photos()->whereIn('id', $ids)->delete();
                }
            }

            if ($request->filled('photo_order')) {
                $this->applyPhotoOrder($product, $request->input('photo_order'));
            }

            if ($request->hasFile('photos')) {
                $nextPosition = ((int) $product->photos()->max('position')) + 1;

                foreach ($request->file('photos') as $file) {
                    $this->handleImageUpload($file, $product, false, $nextPosition++);
                }
            }

            $this->applyCoverByIndex($request, $product);
            $this->syncVariants($request, $product);
        });

        $product->refresh()->load('activeVariants.optionValues.optionType');

        if (! $wasOrderable && $product->isOrderable()) {
            app(BackInStockNotifier::class)->notifyAvailable($product);
        }

        return back()->with('success', __('Prodotto aggiornato.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        foreach ($product->photos as $photo) {
            Storage::disk($photo->disk)->delete($photo->path);

            if ($photo->thumb_path) {
                Storage::disk($photo->disk)->delete($photo->thumb_path);
            }
        }

        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', __('Prodotto eliminato.'));
    }

    protected function validateProduct(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'visibility' => ['required', 'in:visible,hidden,coming_soon'],
            'season_behavior' => ['required', 'in:follow_category,purchasable_longer,visible_not_purchasable,hidden'],
            'own_fulfillment_days' => ['nullable', 'integer', 'min:0'],
            'photos.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'cover_photo_index' => ['nullable', 'integer', 'min:0'],
            'remove_photo_ids' => ['nullable', 'string'],
            'photo_order' => ['nullable', 'string'],
        ], [
            'name.required' => __('Il nome è obbligatorio.'),
            'price_cents.required' => __('Il prezzo è obbligatorio.'),
            'photos.*.max' => __('Un\'immagine supera i 20MB.'),
        ]);
    }

    protected function handleImageUpload($file, Product $product, bool $makeCover = false, int $position = 0): ProductPhoto
    {
        $mime = (string) $file->getMimeType();
        $extension = match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            default => 'jpg',
        };

        $base = time().'_'.uniqid();
        $pathDir = 'products/'.$product->id;
        $fullPath = $pathDir.'/'.$base.'.'.$extension;
        $thumbPath = $pathDir.'/'.$base.'_thumb.jpg';

        $file->storeAs($pathDir, $base.'.'.$extension, 'public');
	$savedThumb = null;
        try {
            $original = Storage::disk('public')->get($fullPath);
            // Intervention Image 4: decode() legge l'immagine, clone è il clone PHP,
            // encodeUsingFileExtension() scrive il formato scelto.
            $image = Image::getFacadeRoot()->decode($original);
            $image->orient(); // rispetta la rotazione EXIF delle foto da telefono

            $full = clone $image;
            $full->scaleDown(width: self::FULL_WIDTH);
            Storage::disk('public')->put($fullPath, (string) $full->encodeUsingFileExtension($extension, quality: self::QUALITY));

            $thumb = clone $image;
            $thumb->scaleDown(width: self::THUMB_WIDTH);
            Storage::disk('public')->put($thumbPath, (string) $thumb->encodeUsingFileExtension('jpg', quality: self::QUALITY));
            $savedThumb = $thumbPath;
        } catch (\Throwable $e) {
            report($e);
        }

        return ProductPhoto::create([
            'product_id' => $product->id,
            'disk' => 'public',
            'path' => $fullPath,
            'alt' => $product->name,
            'is_cover' => $makeCover,
            'position' => $position,
	    'thumb_path' => $savedThumb,
        ]);
    }

    protected function processImage(string $sourcePath, int $maxWidth, ?string $destinationPath = null): void
    {
        $manager = Image::getFacadeRoot();
        $image = null;

        foreach (['read', 'open', 'make'] as $method) {
            if (method_exists($manager, $method)) {
                $image = $manager->{$method}($sourcePath);
                break;
            }
        }

        if (! $image) {
            return;
        }

        if (method_exists($image, 'scaleDown')) {
            $image->scaleDown(width: $maxWidth);
        } elseif (method_exists($image, 'resize')) {
            $image->resize($maxWidth, null, function ($c) {
                $c->aspectRatio();

                if (method_exists($c, 'upsize')) {
                    $c->upsize();
                }
            });
        }

        $target = $destinationPath ?? $sourcePath;

        if (method_exists($image, 'save')) {
            try {
                $image->save($target, self::QUALITY);
            } catch (\Throwable) {
                $image->save($target);
            }
        } elseif (method_exists($image, 'encode')) {
            Storage::disk('public')->put(
                str_replace(Storage::disk('public')->path(''), '', $target),
                (string) $image->encode(null, self::QUALITY)
            );
        }
    }

    protected function applyPhotoOrder(Product $product, string $orderCsv): void
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $orderCsv))));

        if (! $ids) {
            return;
        }

        $existing = $product->photos()->whereIn('id', $ids)->pluck('id')->all();
        $position = 0;

        foreach ($ids as $id) {
            if (in_array($id, $existing, true)) {
                $product->photos()->where('id', $id)->update(['position' => $position++]);
            }
        }
    }

    protected function applyCoverByIndex(Request $request, Product $product): void
    {
        $index = $request->input('cover_photo_index');
        $cover = null;

        if ($index !== null && $index !== '') {
            $photos = $product->photos()
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            $cover = $photos->get((int) $index);
        }

        if (! $cover) {
            $cover = $product->photos()->where('is_cover', true)->first();
        }

        if (! $cover) {
            $cover = $product->photos()->orderBy('position')->orderBy('id')->first();
        }

        if ($cover) {
            $product->photos()->update(['is_cover' => false]);
            $product->photos()->whereKey($cover->id)->update(['is_cover' => true]);
        }
    }

    protected function syncVariants(Request $request, Product $product): void
    {
        $typesInput = $request->input('option_types', []);
        $variantsInput = $request->input('variants', []);

        if (empty($typesInput) || empty($variantsInput)) {
            $product->variants()->forceDelete();
            $product->optionTypes()->each(fn ($t) => $t->values()->delete());
            $product->optionTypes()->delete();

            return;
        }

        DB::transaction(function () use ($product, $typesInput, $variantsInput) {
            $product->variants()->forceDelete();
            $product->optionTypes()->each(fn ($t) => $t->values()->delete());
            $product->optionTypes()->delete();

            $valueLookupTable = [];

            foreach ($typesInput as $pos => $typeData) {
                $typeName = trim($typeData['name'] ?? '');

                if ($typeName === '') {
                    continue;
                }

                $type = ProductOptionType::create([
                    'product_id' => $product->id,
                    'name' => $typeName,
                    'position' => $pos,
                ]);

                $valueLookupTable[$typeName] = [];

                foreach (($typeData['values'] ?? []) as $valPos => $valNameRaw) {
                    $valName = trim((string) $valNameRaw);

                    if ($valName === '') {
                        continue;
                    }

                    $value = ProductOptionValue::create([
                        'option_type_id' => $type->id,
                        'value' => $valName,
                        'position' => $valPos,
                    ]);

                    $valueLookupTable[$typeName][$valName] = $value->id;
                }
            }

            $photosCollection = $product->photos()
                ->orderBy('position')
                ->orderBy('id')
                ->get();

            foreach ($variantsInput as $varData) {
                $partsLabels = $varData['parts'] ?? [];

                if (empty($partsLabels)) {
                    continue;
                }

                $realOptionValueIds = [];
                $valid = true;

                foreach ($partsLabels as $label) {
                    $foundId = null;

                    foreach ($valueLookupTable as $valuesArr) {
                        if (isset($valuesArr[$label])) {
                            $foundId = $valuesArr[$label];
                            break;
                        }
                    }

                    if ($foundId === null) {
                        $valid = false;
                        break;
                    }

                    $realOptionValueIds[] = $foundId;
                }

                if (! $valid || empty($realOptionValueIds)) {
                    continue;
                }

                $basePrice = $product->price_cents;
                $extraEuro = (float) ($varData['price_extra'] ?? 0);
                $totalCents = $basePrice + (int) round($extraEuro * 100);

                $assignedPhotoId = null;

                if (isset($varData['photo_index']) && $varData['photo_index'] !== '') {
                    $idx = (int) $varData['photo_index'];

                    if ($idx >= 0 && $idx < $photosCollection->count()) {
                        $assignedPhotoId = $photosCollection[$idx]->id;
                    }
                }

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $varData['sku'] ?? null,
                    'barcode' => $varData['barcode'] ?? null,
                    'price_cents' => $totalCents,
                    'stock' => (int) ($varData['stock'] ?? 0),
                    'is_active' => true,
                    'photo_id' => $assignedPhotoId,
                ]);

                $variant->optionValues()->attach($realOptionValueIds);
            }
        });
    }
}
