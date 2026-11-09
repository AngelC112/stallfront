<?php

namespace App\Listeners;

use App\Models\EmailLog;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;

/**
 * Registra ogni email in uscita e ne prepara la copia per la cartella "Inviate".
 *
 * Un errore del registro non deve mai bloccare l'invio al cliente: viene solo segnalato.
 */
class RecordOutgoingEmail
{
    public function handleSending(MessageSending $event): void
    {
        try {
            $email = $event->message;

            $log = EmailLog::query()->create([
                'mailable' => $event->data['__laravel_mailable'] ?? $event->data['__laravel_notification'] ?? null,
                'from_address' => Str::limit($this->formatAddresses($email->getFrom()), 187),
                'to_addresses' => $this->formatAddresses($email->getTo()),
                'cc_addresses' => $this->formatAddresses($email->getCc()) ?: null,
                'bcc_addresses' => $this->formatAddresses($email->getBcc()) ?: null,
                'subject' => Str::limit((string) $email->getSubject(), 247),
                'status' => EmailLog::STATUS_SENDING,
            ]);

            $email->getHeaders()->remove(EmailLog::HEADER);
            $email->getHeaders()->addTextHeader(EmailLog::HEADER, (string) $log->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function handleSent(MessageSent $event): void
    {
        try {
            $logId = $event->sent->getOriginalMessage()->getHeaders()->get(EmailLog::HEADER)?->getBodyAsString();
            $log = $logId ? EmailLog::query()->find((int) $logId) : null;

            if ($log === null) {
                return;
            }

            $rawPath = null;

            if (config('mail.sent_copy.enabled')) {
                $rawPath = "sent-mail/{$log->id}.eml";
                Storage::disk('local')->put($rawPath, $event->sent->toString());
            }

            $log->update([
                'status' => EmailLog::STATUS_SENT,
                'message_id' => Str::limit((string) $event->sent->getMessageId(), 187),
                'sent_at' => now(),
                'raw_path' => $rawPath,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<int, Address>  $addresses
     */
    private function formatAddresses(array $addresses): string
    {
        return implode(', ', array_map(fn (Address $address): string => $address->toString(), $addresses));
    }
}
