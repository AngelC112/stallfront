@extends('layouts.admin')

@section('page-title', __('Email inviate'))

@section('content')
    <div class="space-y-4">
        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('success') }}</div>
        @endif

        <div class="flex justify-end">
            <a href="{{ route('admin.customer-emails.create') }}" class="inline-flex min-h-11 items-center rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Scrivi email ai clienti') }}</a>
        </div>

        <form method="GET" class="flex flex-col gap-3 rounded-xl bg-white p-4 border border-gray-200 sm:flex-row sm:items-center">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Destinatario o oggetto') }}" aria-label="{{ __('Destinatario o oggetto') }}" class="block min-h-11 w-full rounded-xl border-gray-300 text-sm sm:max-w-xs">
            <select name="status" aria-label="{{ __('Stato') }}" class="min-h-11 rounded-xl border-gray-300 text-sm">
                <option value="">{{ __('Tutte') }}</option>
                <option value="sent" @selected(request('status') === 'sent')>{{ __('Inviate') }}</option>
                <option value="failed" @selected(request('status') === 'failed')>{{ __('Non inviate') }}</option>
                <option value="not_stored" @selected(request('status') === 'not_stored')>{{ __('Non ancora in "Inviate"') }}</option>
            </select>
            <button class="min-h-11 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filtra') }}</button>
        </form>

        <div class="overflow-x-auto rounded-xl bg-white border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Quando') }}</th>
                        <th class="px-4 py-3">{{ __('Destinatario') }}</th>
                        <th class="px-4 py-3">{{ __('Oggetto') }}</th>
                        <th class="px-4 py-3">{{ __('Tipo') }}</th>
                        <th class="px-4 py-3">{{ __('Stato') }}</th>
                        <th class="px-4 py-3">{{ __('Copia in "Inviate"') }}</th>
                        <th class="px-4 py-3"><span class="sr-only">{{ __('Azioni') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ ($log->sent_at ?? $log->created_at)->isoFormat('L LT') }}</td>
                            <td class="max-w-xs truncate px-4 py-3 text-gray-900" title="{{ $log->to_addresses }}">{{ $log->to_addresses }}</td>
                            <td class="max-w-xs truncate px-4 py-3 text-gray-900" title="{{ $log->subject }}">{{ $log->subject }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $log->mailable ? class_basename($log->mailable) : '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 {{ $log->isFailed() ? 'font-semibold text-red-700' : 'text-gray-700' }}">{{ $log->statusLabel() }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600" @if ($log->store_error) title="{{ $log->store_error }}" @endif>
                                {{ $log->sentCopyLabel() }}
                                @if ($log->store_error && $log->stored_in_sent_at === null)
                                    <span class="block max-w-xs truncate text-xs text-red-700">{{ $log->store_error }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                @if ($log->status === \App\Models\EmailLog::STATUS_SENT)
                                    <a href="{{ route('admin.email-log.show', $log) }}" class="inline-flex min-h-9 items-center rounded-xl border border-gray-300 px-3 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Apri') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">{{ __('Nessuna email registrata.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </div>
@endsection
