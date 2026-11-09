<!DOCTYPE html>
<html lang="{{ \App\Support\Locales::htmlLang() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Ordini da evadere ·') }} {{ now()->isoFormat('L') }}</title>
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 12px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 14px; margin: 18px 0 6px; border-bottom: 2px solid #111; padding-bottom: 3px; }
        h3 { font-size: 12px; margin: 12px 0 4px; text-transform: uppercase; color: #444; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #ccc; padding: 4px 6px; text-align: left; vertical-align: top; }
        .qty { width: 48px; font-weight: bold; text-align: right; }
        .tag { display: inline-block; border: 1px solid #111; border-radius: 3px; padding: 0 4px; font-size: 10px; margin-left: 4px; }
        .note { margin: 8px 0; padding: 6px 8px; border: 1px solid #c60; background: #fff4e5; }
        .toolbar { margin-bottom: 12px; }
        .group { break-inside: avoid-page; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    <div class="toolbar"><button onclick="window.print()">{{ __('Stampa / Salva PDF') }}</button></div>

    <h1>{{ __('Ordini da evadere ·') }} {{ $viewMode === 'product' ? __('per prodotto') : __('per mercato e data') }}</h1>
    <p>{{ __('Stampato il') }} {{ now()->isoFormat('L LT') }}</p>

    @if ($futureOffSeason['count'] > 0)
        <p class="note">{{ $futureOffSeason['count'] }} {{ __('ordini fuori stagione futuri: la prima preparazione inizia il :date.', ['date' => $futureOffSeason['firstDate']->isoFormat('L')]) }}</p>
    @endif

    @forelse ($groups as $group)
        <section class="group">
            <h2>{{ $group['title'] }}{{ $group['date'] ? ' · '.$group['date']->isoFormat('L') : '' }}</h2>

            @if ($viewMode === 'product')
                <table>
                    <tbody>
                        @forelse ($group['lines'] as $line)
                            <tr>
                                <td class="qty">{{ $line['quantity'] }}</td>
                                <td>{{ $line['name'] }} {{ $line['variant'] ? "({$line['variant']})" : '' }}</td>
                                <td>{{ $line['orders']->pluck('customer_name')->unique()->implode(', ') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">{{ __('Niente da preparare.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                @if ($group['fulfilled']->isNotEmpty())
                    <h3>{{ __('Già evasi') }}</h3>
                    <table>
                        <tbody>
                            @foreach ($group['fulfilled'] as $order)
                                <tr><td>{{ $order->customer_name }}</td><td>#{{ $order->order_number }}</td><td>{{ $order->statusLabel() }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @else
                <table>
                    <thead>
                        <tr><th>{{ __('Cliente') }}</th><th>{{ __('Ordine') }}</th><th>{{ __('Contenuto') }}</th><th>{{ __('Incasso') }}</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($group['orders'] as $order)
                            <tr>
                                <td>
                                    {{ $order->customer_name }}
                                    @if ($order->customer_phone)<br>{{ $order->customer_phone }}@endif
                                </td>
                                <td>
                                    #{{ $order->order_number }}<br>{{ $order->statusLabel() }}
                                    @if ($order->isDueToday())<span class="tag">{{ __('da preparare oggi') }}</span>@endif
                                    @if ($order->isOffSeason())<span class="tag">{{ __('fuori stagione') }}</span>@endif
                                </td>
                                <td>
                                    @foreach ($order->items as $item)
                                        {{ $item->quantity }}x {{ $item->product_name }} {{ $item->variant_label ? "({$item->variant_label})" : '' }}<br>
                                    @endforeach
                                </td>
                                <td>{{ \App\Support\Format::money($order->total_cents) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @empty
        <p>{{ __('Non ci sono ordini da mostrare.') }}</p>
    @endforelse
</body>
</html>
