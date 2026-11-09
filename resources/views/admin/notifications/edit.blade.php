@extends('layouts.admin')

@section('page-title', $definition['label'])

@section('content')
    <form method="POST" action="{{ route('admin.notifications.update', $event) }}" class="mx-auto max-w-3xl space-y-5">
        @csrf
        @method('PUT')
        <input type="hidden" name="locale" value="{{ $locale }}">

        @if (session('success'))
            <div class="rounded-xl border border-[#9fcfb4] bg-[#dff0e6] p-3 text-sm font-semibold text-[#14532d]" role="status">{{ session('success') }}</div>
        @endif

        <x-locale-tabs :current="$locale" :customized="$customizedLocales"
                       :url="fn ($code) => route('admin.notifications.edit', ['event' => $event, 'lang' => $code])" />

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="space-y-5 rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
            <label class="flex items-center gap-3">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $definition['is_active'])) class="h-5 w-5 rounded border-gray-300 text-brand-600">
                <span class="text-sm font-semibold text-gray-900">{{ __('Invia questa notifica') }}</span>
            </label>


            <p class="text-sm text-gray-600">
                {{ __('Testo in :language. I clienti che hanno scelto questa lingua ricevono questo messaggio.', ['language' => \App\Support\Locales::all()[$locale]]) }}
                @if (! $text['customized'])
                    {{ __('Ora è in uso il testo predefinito.') }}
                @endif
            </p>

            <div>
                <label for="subject" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Oggetto') }}</label>
                <input id="subject" type="text" name="subject" value="{{ old('subject', $text['subject']) }}" required maxlength="255" class="block w-full rounded-xl border-gray-300 shadow-sm">
            </div>

            <div>
                <label for="body" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Testo') }}</label>
                <textarea id="body" name="body" rows="9" required maxlength="5000" class="block w-full rounded-xl border-gray-300 shadow-sm">{{ old('body', $text['body']) }}</textarea>
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
            <a href="{{ route('admin.notifications.texts') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Annulla') }}</a>
            @if ($text['customized'])
                <button type="submit" name="reset" value="1" formnovalidate
                        onclick="return confirm(@js(__('Tornare al testo predefinito per questa lingua?')))"
                        class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Ripristina il testo predefinito') }}</button>
            @endif
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-600 px-6 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Salva') }}</button>
        </div>
    </form>
@endsection
