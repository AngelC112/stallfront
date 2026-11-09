@extends('layouts.admin')

@section('page-title', __('Email inviata'))

@section('content')
    <div class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.email-log.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('← Email inviate') }}</a>
            @if ($message)
                <a href="{{ route('admin.email-log.download', $log) }}" class="inline-flex min-h-11 items-center rounded-xl bg-brand-600 px-4 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Scarica .eml') }}</a>
            @endif
        </div>

        <div class="rounded-xl bg-white p-4 border border-gray-200">
            <dl class="grid grid-cols-1 gap-x-6 gap-y-2 text-sm sm:grid-cols-[auto_1fr]">
                <dt class="font-semibold text-gray-500">{{ __('Oggetto') }}</dt>
                <dd class="text-gray-900">{{ $message?->header('subject') ?? $log->subject }}</dd>
                <dt class="font-semibold text-gray-500">{{ __('Da') }}</dt>
                <dd class="text-gray-900">{{ $message?->header('from') ?? $log->from_address }}</dd>
                <dt class="font-semibold text-gray-500">A</dt>
                <dd class="text-gray-900">{{ $message?->header('to') ?? $log->to_addresses }}</dd>
                @if ($message?->header('cc') ?? $log->cc_addresses)
                    <dt class="font-semibold text-gray-500">{{ __('Cc') }}</dt>
                    <dd class="text-gray-900">{{ $message?->header('cc') ?? $log->cc_addresses }}</dd>
                @endif
                @if ($log->bcc_addresses)
                    <dt class="font-semibold text-gray-500">{{ __('Ccn') }}</dt>
                    <dd class="text-gray-900">{{ $log->bcc_addresses }}</dd>
                @endif
                <dt class="font-semibold text-gray-500">{{ __('Inviata') }}</dt>
                <dd class="text-gray-900">{{ ($log->sent_at ?? $log->created_at)->isoFormat('L LT') }} · {{ $log->statusLabel() }}</dd>
            </dl>
        </div>

        @if ($error)
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $error }}</div>
        @endif

        @if ($message)
            @if ($message->attachments !== [])
                <div class="rounded-xl bg-white p-4 border border-gray-200">
                    <h2 class="mb-2 text-sm font-semibold text-gray-700">{{ __('Allegati') }}</h2>
                    <ul class="space-y-1 text-sm">
                        @foreach ($message->attachments as $index => $attachment)
                            <li>
                                <a href="{{ route('admin.email-log.attachment', [$log, $index]) }}" class="font-medium text-brand-700 underline hover:text-brand-800">{{ $attachment['filename'] }}</a>
                                <span class="text-gray-500">{{ __('· :content_type · :content KB', ['content_type' => $attachment['content_type'], 'content' => number_format(strlen($attachment['content']) / 1024, 1, ',', '.')]) }}{{ $attachment['inline'] ? __(' · immagine nel testo') : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden rounded-xl bg-white border border-gray-200">
                @if ($message->html !== null)
                    {{-- Niente script nell'email (manca allow-scripts); allow-same-origin serve solo a leggerne l'altezza. --}}
                    <iframe sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer" title="{{ __('Contenuto dell\'email') }}"
                            class="block w-full" style="height: 75vh; border: 0;"
                            onload="try { this.style.height = (this.contentDocument.documentElement.scrollHeight + 16) + 'px'; } catch (e) {}"
                            srcdoc="{{ $message->htmlWithInlineImages() }}"></iframe>
                @else
                    <pre class="whitespace-pre-wrap p-4 text-sm text-gray-900">{{ $message->text }}</pre>
                @endif
            </div>
        @endif
    </div>
@endsection
