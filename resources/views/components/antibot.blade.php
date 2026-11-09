{{-- Campo trappola per i bot: nascosto alle persone e ai lettori di schermo. --}}
<div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
    <label for="website">{{ __('Lascia vuoto questo campo') }}</label>
    <input type="text" id="website" name="website" value="" tabindex="-1" autocomplete="off">
</div>
<input type="hidden" name="form_started_at" value="{{ now()->timestamp }}">
