@extends('layouts.admin')

@section('page-title', __('Impostazioni del portale'))

@section('content')
    <div class="mx-auto mb-4 max-w-3xl text-sm">
        <a href="{{ route('admin.settings.whatsapp.edit') }}" class="inline-flex min-h-11 items-center font-semibold text-brand-700 hover:text-brand-900">{{ __('Collegamento WhatsApp Business →') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="mx-auto max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-semibold">{{ __('Controlla i dati inseriti:') }}</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @foreach ($groups as $groupLabel => $definitions)
            <section class="rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
                <h2 class="mb-5 text-lg font-bold text-gray-900">{{ $groupLabel }}</h2>
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($definitions as $key => $definition)
                        @php($field = \App\Support\PortalSettings::fieldName($key))
                        @php($current = old($field, $values[$key]))
                        <div class="{{ $definition['type'] === 'text' && ($definition['max'] ?? 0) > 120 ? 'sm:col-span-2' : '' }}">
                            @if ($definition['type'] === 'bool')
                                <label class="flex min-h-11 items-center gap-3 text-sm font-semibold text-gray-700">
                                    <input type="checkbox" name="{{ $field }}" value="1" @checked($current) class="h-5 w-5 rounded border-gray-300 text-brand-600">
                                    {{ $definition['label'] }}
                                </label>
                            @else
                                <label for="{{ $field }}" class="mb-1 block text-sm font-semibold text-gray-700">{{ $definition['label'] }}</label>
                                <div class="flex items-center gap-2">
                                    @if ($definition['type'] === 'choice')
                                        <select id="{{ $field }}" name="{{ $field }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                                            @foreach ($definition['choices'] as $value => $label)
                                                <option value="{{ $value }}" @selected((string) $current === (string) $value)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    @elseif ($definition['type'] === 'time')
                                        <input id="{{ $field }}" type="time" name="{{ $field }}" value="{{ $current }}" required class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                                    @elseif ($definition['type'] === 'int')
                                        <input id="{{ $field }}" type="number" name="{{ $field }}" value="{{ $current }}" min="{{ $definition['min'] }}" max="{{ $definition['max'] }}" required class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                                    @elseif ($definition['type'] === 'money')
                                        <input id="{{ $field }}" type="number" step="0.01" min="0" name="{{ $field }}" value="{{ $current }}" required class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                                    @else
                                        <input id="{{ $field }}" type="text" name="{{ $field }}" value="{{ $current }}" maxlength="{{ $definition['max'] }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                                    @endif
                                    @isset($definition['unit'])
                                        <span class="shrink-0 text-sm text-gray-500">{{ $definition['unit'] }}</span>
                                    @endisset
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-600 px-6 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Salva impostazioni') }}</button>
        </div>
    </form>

    <section class="mx-auto mt-6 max-w-3xl rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
        <h2 class="mb-2 text-lg font-bold text-gray-900">{{ __('Prova invio email') }}</h2>
        <p class="mb-4 text-sm text-gray-600">{{ __('Invia un\'email di prova per verificare che il server di posta funzioni.') }}</p>

        @if (session('test_success'))
            <div class="mb-4 rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('test_success') }}</div>
        @endif
        @if (session('test_error'))
            <div role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ session('test_error') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.settings.test-email') }}" class="flex flex-col gap-3 sm:flex-row">
            @csrf
            <div class="flex-1">
                <label for="test_email" class="sr-only">{{ __('Indirizzo email di prova') }}</label>
                <input id="test_email" type="email" name="test_email" value="{{ old('test_email') }}" required placeholder="{{ __('nome@esempio.it') }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
            </div>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-gray-800 px-6 py-2 text-sm font-semibold text-white hover:bg-gray-900">{{ __('Invia email di prova') }}</button>
        </form>
    </section>
@endsection
