@extends('layouts.admin')

@section('page-title', $collaborator->exists ? __('Permessi di :name', ['name' => $collaborator->fullName()]) : __('Nuovo collaboratore'))

@section('content')
    <form method="POST" action="{{ $collaborator->exists ? route('admin.collaborators.update', $collaborator) : route('admin.collaborators.store') }}" class="mx-auto max-w-3xl space-y-5">
        @csrf
        @if ($collaborator->exists)
            @method('PUT')
        @endif

        @if (session('existing_customer'))
            <div role="alert" class="space-y-3 rounded-2xl border border-apricot bg-apricot-light p-5 text-sm text-gray-900">
                <p><strong>{{ session('existing_customer')['name'] }}</strong> {{ __('(:email) è già registrato come cliente.', ['email' => session('existing_customer')['email']]) }}</p>
                <p>{{ __('Vuoi renderlo collaboratore con i permessi scelti qui sotto? Potrà entrare nel pannello con la sua solita password; i suoi dati e i suoi ordini restano invariati.') }}</p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="submit" name="confirm_existing" value="1" class="btn-primary">{{ __('Sì, rendilo collaboratore') }}</button>
                    <a href="{{ route('admin.collaborators.create') }}" class="btn-secondary">{{ __('Usa un’altra email') }}</a>
                </div>
            </div>
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

        <section class="grid gap-5 rounded-xl bg-white p-6 border border-gray-200 sm:grid-cols-2 sm:p-8">
            <div>
                <label for="first_name" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Nome') }}</label>
                <input id="first_name" name="first_name" type="text" required maxlength="100" value="{{ old('first_name', $collaborator->first_name) }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
            </div>
            <div>
                <label for="last_name" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Cognome') }}</label>
                <input id="last_name" name="last_name" type="text" required maxlength="100" value="{{ old('last_name', $collaborator->last_name) }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
            </div>
            @unless ($collaborator->exists)
                <div class="sm:col-span-2">
                    <label for="email" class="mb-1 block text-sm font-semibold text-gray-700">{{ __('Email') }}</label>
                    <input id="email" name="email" type="email" required maxlength="255" value="{{ old('email') }}" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                </div>
            @endunless
            <div class="sm:col-span-2">
                <label for="password" class="mb-1 block text-sm font-semibold text-gray-700">{{ $collaborator->exists ? __('Nuova password (lascia vuoto per non cambiarla)') : __('Password iniziale') }}</label>
                <input id="password" name="password" type="password" {{ $collaborator->exists || session('existing_customer') ? '' : 'required' }} autocomplete="new-password" class="block min-h-11 w-full rounded-xl border-gray-300 shadow-sm">
                @unless ($collaborator->exists)
                    <p class="mt-1 text-sm text-gray-500">{{ __('Se l’email è di un cliente già registrato, la password resta la sua.') }}</p>
                @endunless
            </div>
        </section>

        <section class="rounded-xl bg-white p-6 border border-gray-200 sm:p-8">
            <h2 class="mb-4 text-lg font-bold text-gray-900">{{ __('Permessi') }}</h2>
            <p class="mb-4 text-sm text-gray-600">{{ __('Ogni permesso è indipendente. Sblocco clienti, consegna a domicilio, collaboratori e impostazioni del portale restano sempre al venditore.') }}</p>
            <div class="space-y-1">
                @foreach ($permissions as $key => $label)
                    <label class="flex min-h-11 items-center gap-3 text-sm text-gray-800">
                        <input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, old('permissions', $granted), true)) class="h-5 w-5 rounded border-gray-300 text-brand-600">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('admin.collaborators.index') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Annulla') }}</a>
            <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-brand-600 px-6 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Salva') }}</button>
        </div>
    </form>
@endsection
