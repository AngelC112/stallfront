<?php

namespace App\Console\Commands;

use App\Models\EmailLog;
use App\Services\ImapSentFolder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoreSentEmails extends Command
{
    protected $signature = 'mail:store-sent {--limit=50 : Maximum emails to save per run}';

    protected $description = 'Saves the sent emails in the "Sent" folder of the site mailbox';

    public function handle(): int
    {
        $logs = EmailLog::query()->pendingSentCopy()->orderBy('id')->limit((int) $this->option('limit'))->get();

        if ($logs->isEmpty()) {
            return self::SUCCESS;
        }

        $folder = app(ImapSentFolder::class);

        try {
            $folder->connect();
        } catch (\Throwable $e) {
            EmailLog::query()->whereKey($logs->modelKeys())->increment('store_attempts', 1, [
                'store_error' => Str::limit($e->getMessage(), 1000),
            ]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $stored = 0;
        $disk = Storage::disk('local');

        try {
            foreach ($logs as $log) {
                $rawMessage = $disk->get($log->raw_path);

                if ($rawMessage === null) {
                    $log->update([
                        'store_attempts' => EmailLog::MAX_STORE_ATTEMPTS,
                        'store_error' => __('File del messaggio non trovato.'),
                    ]);

                    continue;
                }

                try {
                    $folder->append($rawMessage, $log->sent_at);
                } catch (\Throwable $e) {
                    $log->update([
                        'store_attempts' => $log->store_attempts + 1,
                        'store_error' => Str::limit($e->getMessage(), 1000),
                    ]);

                    // Dopo un errore la connessione può essere in uno stato incerto: si riprova al prossimo giro.
                    break;
                }

                $disk->delete($log->raw_path);
                $log->update([
                    'stored_in_sent_at' => now(),
                    'raw_path' => null,
                    'store_error' => null,
                ]);
                $stored++;
            }
        } finally {
            $folder->disconnect();
        }

        $this->info("Email salvate in Inviate: {$stored}");

        return self::SUCCESS;
    }
}
