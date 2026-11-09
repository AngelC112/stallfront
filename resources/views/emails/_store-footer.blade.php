<span style="font-size: 12px; color: #6b7280;">
{{ $store['name'] }}@if (! empty($store['address'])) · {{ $store['address'] }}@endif @if (! empty($store['vat'])) · {{ __('P.IVA :vat', ['vat' => $store['vat']]) }}@endif
</span>
