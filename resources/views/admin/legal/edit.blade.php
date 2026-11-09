@extends('layouts.admin')

@section('page-title', $label)

@section('content')
    <form method="POST" action="{{ route('admin.legal.update', $page) }}" class="mx-auto max-w-4xl space-y-5">
        @csrf
        @method('PUT')
        <input type="hidden" name="locale" value="{{ $locale }}">

        <a href="{{ route('admin.legal.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">‹ {{ __('Testi legali') }}</a>

        @if (session('success'))
            <div class="rounded-xl border border-[#9fcfb4] bg-[#dff0e6] p-3 text-sm font-semibold text-[#14532d]" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <x-locale-tabs :current="$locale" :customized="$customizedLocales"
                       :url="fn ($code) => route('admin.legal.edit', ['page' => $page, 'lang' => $code])" />

        <section class="space-y-4 rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
            <p class="text-sm text-gray-600">
                {{ __('Testo in :language, mostrato ai visitatori che usano questa lingua.', ['language' => \App\Support\Locales::all()[$locale]]) }}
                @if ($source['customized'])
                    {{ __('È il tuo testo personalizzato.') }}
                @else
                    {{ __('Ora è in uso il testo predefinito.') }}
                @endif
            </p>

            <div>
                <label for="body" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Testo') }}</label>
                <textarea id="body" name="body" rows="24" maxlength="60000" class="block w-full rounded-xl border-gray-300 font-mono text-sm shadow-sm">{{ old('body', $source['source']) }}</textarea>
                <p class="mt-1 text-xs text-gray-500">{{ __('Formattazione Markdown: # titolo, ## sezione, - elenco, **grassetto**.') }}</p>
            </div>

            <div class="rounded-xl bg-gray-50 p-3 text-xs text-gray-600">
                <p class="mb-1 font-semibold">{{ __('Segnaposto disponibili') }}</p>
                <ul class="space-y-0.5">
                    @foreach ($placeholders as $placeholder => $description)
                        <li><code>{{ $placeholder }}</code> · {{ $description }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('legal.'.$page) }}" target="_blank" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Vedi la pagina') }}</a>
            @if ($source['customized'])
                <button type="submit" name="reset" value="1" formnovalidate
                        onclick="return confirm(@js(__('Tornare al testo predefinito per questa lingua?')))"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Ripristina il testo predefinito') }}</button>
            @endif
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-600 px-6 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Salva') }}</button>
        </div>
    </form>
@endsection
