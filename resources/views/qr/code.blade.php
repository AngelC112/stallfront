<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::htmlLang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('QR Code - Ordine') }} {{ $order->order_number }}</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; background: #f8f9fa; padding: 24px 16px; text-align: center; }
        .container { max-width: 400px; margin: 0 auto; }
        h1 { color: #1a2a2a; margin-bottom: 20px; font-size: 22px; }
        .qr-wrapper { background: #fff; border: 2px solid #e2e8f0; border-radius: 12px; padding: 20px; margin: 20px 0; }
        .qrcode { width: 100%; max-width: 260px; height: auto; margin: 0 auto; display: block; }
        .order-info { color: #666; font-size: 14px; }
        .order-info p { margin: 8px 0; }
        .btn-download { display: inline-block; margin-top: 20px; padding: 12px 20px; min-height: 44px; background: #2c3e50; color: #fff; text-decoration: none; border-radius: 6px; }
        .meta { margin-top: 30px; padding-top: 30px; border-top: 1px solid #e2e8f0; color: #888; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ __('QR Code Ordine') }} {{ $order->order_number }}</h1>

        <div class="qr-wrapper">
            <img src="{{ route('qr.image', ['orderNumber' => $order->order_number], false) }}"
                 alt="QR Code ordine {{ $order->order_number }}"
                 class="qrcode">
        </div>

        <div class="order-info">
            <p><strong>{{ __('Mercato:') }}</strong> {{ $order->market_name ?? __('Nessun mercato') }}</p>
            <p><strong>{{ __('Posizione:') }}</strong> {{ $order->market_location ?? '' }}</p>
            @if($order->pickup_date)
                <p><strong>{{ __('Data ritiro:') }}</strong> {{ $order->pickup_date->isoFormat('L') }}</p>
            @endif
            <p><strong>{{ __('Importo totale:') }}</strong> {{ \App\Support\Format::money($order->total_cents) }}</p>
            <p><strong>{{ __('Metodo:') }}</strong> {{ $order->fulfillmentMethodLabel() }}</p>
        </div>

        <a href="{{ route('qr.image', ['orderNumber' => $order->order_number, 'download' => 1], false) }}" class="btn-download">{{ __('Scarica QR Code') }}</a>

        <div class="meta">
            {{ __('Ordine :order_number', ['order_number' => $order->order_number]) }}
        </div>
    </div>
</body>
</html>
