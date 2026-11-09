@extends('layouts.admin')

@section('page-title', __('Collaboratori'))

@section('content')
    <div class="space-y-4">
        @if (session('success'))
            <div class="rounded-xl border border-green-200 bg-green-50 p-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif

        <div class="flex justify-end">
            <a href="{{ route('admin.collaborators.create') }}" class="inline-flex min-h-11 items-center rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Nuovo collaboratore') }}</a>
        </div>

        @forelse ($collaborators as $collaborator)
            <article class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-4">
                <div class="min-w-0">
                    <p class="font-semibold text-gray-900">{{ $collaborator->fullName() }}</p>
                    <p class="text-sm text-gray-500">{{ $collaborator->email }} {{ __('· :permissions_count permessi', ['permissions_count' => $collaborator->permissions_count]) }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('admin.collaborators.edit', $collaborator) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-brand-700 hover:text-brand-900">{{ __('Permessi') }}</a>
                    <form method="POST" action="{{ route('admin.collaborators.destroy', $collaborator) }}" onsubmit="return confirm(@js(__('Rimuovere questo collaboratore?')))">
                        @csrf
                        @method('DELETE')
                        <button class="min-h-11 text-sm font-semibold text-red-600 hover:text-red-800">{{ __('Rimuovi') }}</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="rounded-xl border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-500">{{ __('Nessun collaboratore.') }}</p>
        @endforelse
    </div>
@endsection
