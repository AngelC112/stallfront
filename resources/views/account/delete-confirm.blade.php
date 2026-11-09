<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ __('Elimina account') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="space-y-4 rounded-xl bg-white p-6 border border-gray-200">
                <p class="text-gray-800">{{ __('Stai per eliminare definitivamente il tuo account.') }}</p>
                <ul class="list-inside list-disc text-sm text-gray-600">
                    <li>{{ __('verranno cancellati i tuoi dati personali e tutti i tuoi ordini;') }}</li>
                    <li>{{ __('gli ordini ancora aperti verranno annullati;') }}</li>
                    <li>{{ __('l’operazione non si può annullare.') }}</li>
                </ul>
                <form method="POST" action="{{ request()->fullUrl() }}" class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                    @csrf
                    <a href="{{ route('account.edit') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Annulla') }}</a>
                    <button class="min-h-11 rounded-xl bg-red-600 px-4 text-sm font-semibold text-white hover:bg-red-500">{{ __('Elimina definitivamente') }}</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
