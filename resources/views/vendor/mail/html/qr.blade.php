@props(['src', 'code'])
<table class="panel" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="panel-content" align="center">
<p style="text-align:center;margin:0 0 8px;"><strong>Mostra questo QR al banco al momento del ritiro</strong></p>
<img src="{{ $src }}" alt="QR dell’ordine {{ $code }}" width="200" height="200" style="display:block;margin:0 auto;width:200px;height:200px;">
<p style="text-align:center;margin:8px 0 0;">Codice: <strong>{{ $code }}</strong></p>
</td>
</tr>
</table>
