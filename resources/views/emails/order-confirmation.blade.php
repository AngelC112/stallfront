<x-mail::message>
# {{ __('Ordine ricevuto') }}

{{ __('Grazie :name, abbiamo ricevuto l’ordine :number.', ['name' => $order->customer_name, 'number' => $order->order_number]) }}

{{ __('Modalità:') }} **{{ $order->fulfillment_method === 'delivery' ? __('Consegna a domicilio') : $order->fulfillmentMethodLabel() }}**  
{{ __('Pagamento:') }} **{{ $order->paymentMethodLabel() }}**  
@if ($order->pickup_date)
{{ __('Data di ritiro desiderata:') }} **{{ \App\Support\Format::date($order->pickup_date) }}**  
@endif
@if ($order->market_name)
{{ __('Mercato:') }} **{{ $order->market_name }}** · {{ $order->market_location }}  
@endif
@if ($order->delivery_address)
{{ __('Indirizzo:') }} {{ $order->delivery_address }}  
@endif

| {{ __('Articolo') }} | {{ __('Quantità') }} | {{ __('Totale') }} |
|:--|--:|--:|
@foreach ($order->items as $item)
| {{ $item->product_name }}{{ $item->variant_label ? ' · '.$item->variant_label : '' }} | {{ $item->quantity }} | {{ \App\Support\Format::money($item->line_total_cents) }} |
@endforeach

@if ($order->delivery_cost_cents > 0)
{{ __('Consegna:') }} {{ \App\Support\Format::money($order->delivery_cost_cents) }}  
@endif
{{ __('Totale da pagare:') }} **{{ \App\Support\Format::money($order->total_cents) }}**

@if ($showQr && isset($message) && ($qrImage = $qrSource($message)))
<x-mail::qr :src="$qrImage" :code="$order->order_number" />
@endif

<x-mail::button :url="route('account.orders.show', ['order' => $order->order_number])">
{{ __('Vedi ordine') }}
</x-mail::button>

{{ \App\Models\Setting::get('store.name', config('app.name')) }}
</x-mail::message>
