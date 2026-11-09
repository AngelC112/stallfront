@php($blockNoticeCount = \App\Http\Controllers\Admin\BlockNoticeAdminController::unseenCount())
@if ($blockNoticeCount > 0)
    <a href="{{ route('admin.block-notices.index') }}" class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-[#8a2a2a] bg-[#fbe3e3] px-4 py-3 text-sm font-bold text-[#8a2a2a] hover:brightness-95">
        <span>{{ trans_choice(':count ordine annullato per blocco cliente|:count ordini annullati per blocco cliente', $blockNoticeCount) }}</span>
        <span>{{ __('Vedi ›') }}</span>
    </a>
@endif
