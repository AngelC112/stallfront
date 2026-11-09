@extends('layouts.admin')

@section('page-title', __('Ferie e chiusure'))

@section('content')
    <div class="space-y-6">
        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="flex justify-end">
            <a href="{{ route('admin.holidays.create') }}" class="inline-flex min-h-11 items-center rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Nuova chiusura') }}</a>
        </div>

        <section class="space-y-3">
            <h2 class="font-semibold text-gray-900">{{ __('Ferie programmate') }}</h2>
            @forelse ($holidays as $holiday)
                <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ $holiday->market?->name ?? __('Tutti i mercati') }}</p>
                        <p class="text-sm text-gray-600">{{ __('Dal :date al', ['date' => $holiday->start_date->isoFormat('L')]) }} {{ $holiday->end_date->isoFormat('L') }}</p>
                        @if ($holiday->reason)
                            <p class="mt-1 text-sm text-gray-500">{{ $holiday->reason }}</p>
                        @endif
                    </div>
                    @if (in_array('holiday', $allowedTypes, true))
                        <form method="POST" action="{{ route('admin.holidays.destroy', $holiday) }}" onsubmit="return confirm(@js(__('Eliminare questo periodo di ferie? Gli ordini già spostati in «Da riprogrammare» non vengono ripristinati.')))">
                            @csrf
                            @method('DELETE')
                            <button class="min-h-11 text-sm font-semibold text-red-600 hover:text-red-800">{{ __('Elimina') }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500">{{ __('Nessun periodo di ferie in programma.') }}</p>
            @endforelse
        </section>

        <section class="space-y-3">
            <h2 class="font-semibold text-gray-900">{{ __('Chiusure straordinarie') }}</h2>
            @forelse ($closures as $closure)
                <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900">{{ $closure->market?->name }}</p>
                        <p class="text-sm text-gray-600">{{ $closure->closure_date->isoFormat('L') }}</p>
                        @if ($closure->reason)
                            <p class="mt-1 text-sm text-gray-500">{{ $closure->reason }}</p>
                        @endif
                    </div>
                    @if (in_array('closure', $allowedTypes, true))
                        <form method="POST" action="{{ route('admin.closures.destroy', $closure) }}" onsubmit="return confirm(@js(__('Eliminare questa chiusura? Gli ordini già spostati in «Da riprogrammare» non vengono ripristinati.')))">
                            @csrf
                            @method('DELETE')
                            <button class="min-h-11 text-sm font-semibold text-red-600 hover:text-red-800">{{ __('Elimina') }}</button>
                        </form>
                    @endif
                </article>
            @empty
                <p class="rounded-xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500">{{ __('Nessuna chiusura straordinaria in programma.') }}</p>
            @endforelse
        </section>
    </div>
@endsection
