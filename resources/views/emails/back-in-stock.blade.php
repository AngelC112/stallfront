<x-mail::message>
# {{ __('Di nuovo disponibile') }}

{{ $variantLabel
    ? __('La variante :variant del prodotto :product è di nuovo disponibile.', ['variant' => $variantLabel, 'product' => $productName])
    : __('Il prodotto :product è di nuovo disponibile.', ['product' => $productName]) }}

<x-mail::button :url="$productUrl">
{{ __('Visualizza il prodotto') }}
</x-mail::button>

{{ __('Grazie,') }}<br>
{{ \App\Models\Setting::get('store.name', config('app.name')) }}
</x-mail::message>
