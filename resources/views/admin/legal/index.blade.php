@extends('layouts.admin')

@section('page-title', __('Testi legali'))

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">
        <p class="text-sm text-gray-600">
            {{ __('Privacy Policy, Cookie Policy e Termini di vendita hanno un testo predefinito in ogni lingua, compilato con i dati del negozio. Puoi sostituirlo con il tuo, lingua per lingua.') }}
        </p>
        <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">
            {{ __('I testi predefiniti sono un punto di partenza e non sostituiscono una consulenza legale: falli verificare da un professionista prima di pubblicare il sito.') }}
        </p>

        <div class="grid gap-3">
            @foreach ($pages as $page => $info)
                <a href="{{ route('admin.legal.edit', $page) }}" class="block rounded-2xl border border-gray-200 bg-white p-4 hover:border-brand-300">
                    <p class="font-bold text-gray-900">{{ $info['label'] }}</p>
                    <p class="mt-1 text-sm text-gray-500">
                        @if ($info['customized'])
                            {{ __('Testo personalizzato in: :languages', ['languages' => collect($info['customized'])->map(fn ($code) => \App\Support\Locales::all()[$code] ?? $code)->implode(', ')]) }}
                        @else
                            {{ __('Testo predefinito in tutte le lingue') }}
                        @endif
                    </p>
                    <span class="mt-2 inline-flex text-sm font-semibold text-brand-600">{{ __('Modifica') }} ›</span>
                </a>
            @endforeach
        </div>
    </div>
@endsection
