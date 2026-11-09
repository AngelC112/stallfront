@extends('layouts.admin')

@section('page-title', __('Registro attività'))

@section('content')
    <div class="space-y-4">
        <form method="GET" class="flex flex-col gap-3 rounded-xl bg-white p-4 border border-gray-200 sm:flex-row sm:items-center">
            <select name="user_id" aria-label="{{ __('Utente') }}" class="min-h-11 rounded-xl border-gray-300 text-sm">
                <option value="">{{ __('Tutti gli utenti') }}</option>
                @foreach ($staff as $member)
                    <option value="{{ $member->id }}" @selected((string) request('user_id') === (string) $member->id)>{{ $member->fullName() }}</option>
                @endforeach
            </select>
            <input type="search" name="action" value="{{ request('action') }}" placeholder="{{ __('Azione (es. order, product)') }}" aria-label="{{ __('Azione') }}" class="block min-h-11 w-full rounded-xl border-gray-300 text-sm sm:max-w-xs">
            <button class="min-h-11 rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filtra') }}</button>
        </form>

        <div class="overflow-x-auto rounded-xl bg-white border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">{{ __('Quando') }}</th>
                        <th class="px-4 py-3">{{ __('Chi') }}</th>
                        <th class="px-4 py-3">{{ __('Azione') }}</th>
                        <th class="px-4 py-3">{{ __('Su cosa') }}</th>
                        <th class="px-4 py-3">{{ __('Dettagli') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3 text-gray-600">{{ $log->created_at->isoFormat('L LT') }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ $log->user?->fullName() ?: __('Sistema') }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800">{{ $log->action }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                            <td class="max-w-xs truncate px-4 py-3 text-xs text-gray-500">{{ $log->details ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ __('Nessuna attività registrata.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $logs->links() }}
    </div>
@endsection
