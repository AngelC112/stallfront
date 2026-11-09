<?php

namespace App\Services;

use App\Models\EmailLog;
use Illuminate\Support\Facades\Storage;

/**
 * Recupera il messaggio completo di un'email inviata: dalla copia locale se non è ancora
 * stata salvata in "Inviate", altrimenti dalla cartella "Inviate" della casella via IMAP.
 */
class SentEmailRetriever
{
    /**
     * @throws \RuntimeException se la casella non è raggiungibile
     */
    public function raw(EmailLog $log): ?string
    {
        if ($log->raw_path !== null) {
            $local = Storage::disk('local')->get($log->raw_path);

            if ($local !== null) {
                return $local;
            }
        }

        if ($log->message_id === null || ! config('mail.sent_copy.enabled')) {
            return null;
        }

        $folder = app(ImapSentFolder::class);
        $folder->connect();

        try {
            return $folder->fetchByMessageId($log->message_id, $log->sent_at);
        } finally {
            $folder->disconnect();
        }
    }
}
