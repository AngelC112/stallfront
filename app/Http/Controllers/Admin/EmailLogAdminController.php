<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Services\SentEmailRetriever;
use App\Support\MimeMessage;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmailLogAdminController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        $logs = EmailLog::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match
                ->where('to_addresses', 'LIKE', '%'.$search.'%')
                ->orWhere('subject', 'LIKE', '%'.$search.'%')))
            ->when($request->query('status') === 'sent', fn ($query) => $query->where('status', EmailLog::STATUS_SENT))
            ->when($request->query('status') === 'failed', fn ($query) => $query
                ->where('status', EmailLog::STATUS_SENDING)
                ->where('created_at', '<', now()->subMinutes(EmailLog::STALE_AFTER_MINUTES)))
            ->when($request->query('status') === 'not_stored', fn ($query) => $query
                ->where('status', EmailLog::STATUS_SENT)
                ->whereNull('stored_in_sent_at'))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.email-log.index', ['logs' => $logs]);
    }

    public function show(EmailLog $emailLog, SentEmailRetriever $retriever): View
    {
        $error = null;
        $message = null;

        try {
            $raw = $retriever->raw($emailLog);
            $message = $raw !== null ? MimeMessage::parse($raw) : null;
            $error = $raw === null ? __('Email non trovata nella cartella "Inviate" della casella.') : null;
        } catch (\Throwable $e) {
            report($e);
            $error = __('Impossibile recuperare l\'email dalla casella: :error', ['error' => $e->getMessage()]);
        }

        return view('admin.email-log.show', [
            'log' => $emailLog,
            'message' => $message,
            'error' => $error,
        ]);
    }

    public function download(EmailLog $emailLog, SentEmailRetriever $retriever): StreamedResponse
    {
        $raw = $retriever->raw($emailLog);
        abort_if($raw === null, 404);

        return response()->streamDownload(function () use ($raw): void {
            echo $raw;
        }, 'email-'.$emailLog->id.'.eml', [
            'Content-Type' => 'message/rfc822',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function attachment(EmailLog $emailLog, int $attachment, SentEmailRetriever $retriever): StreamedResponse
    {
        $raw = $retriever->raw($emailLog);
        abort_if($raw === null, 404);

        $file = MimeMessage::parse($raw)->attachments[$attachment] ?? null;
        abort_if($file === null, 404);

        return response()->streamDownload(function () use ($file): void {
            echo $file['content'];
        }, $file['filename'], [
            'Content-Type' => $file['content_type'] ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
