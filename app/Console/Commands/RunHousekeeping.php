<?php

namespace App\Console\Commands;

use App\Models\EmailChangeRequest;
use App\Models\NotificationQueueItem;
use App\Models\PhoneVerification;
use App\Models\PushSubscription;
use App\Models\VerificationToken;
use App\Services\StockReservationService;
use Illuminate\Console\Command;

class RunHousekeeping extends Command
{
    protected $signature = 'housekeeping:run';

    protected $description = 'Deletes expired tokens, codes and sent notifications';

    public function handle(): int
    {
        $tokens = VerificationToken::query()->where('expires_at', '<', now()->subDays(7))->delete();
        $codes = PhoneVerification::query()->where('expires_at', '<', now()->subDays(7))->delete();
        $emailChanges = EmailChangeRequest::query()->where('expires_at', '<', now()->subDays(30))->delete();
        $reservations = app(StockReservationService::class)->pruneExpired();
        $notifications = NotificationQueueItem::query()
            ->where('status', NotificationQueueItem::STATUS_SENT)
            ->where('sent_at', '<', now()->subDays(90))
            ->delete();

        // Iscrizioni push disattivate da più di 30 giorni, o mai usate da 6 mesi.
        $pushSubscriptions = PushSubscription::query()
            ->where(fn ($query) => $query
                ->where(fn ($inactive) => $inactive->where('is_active', false)->where('updated_at', '<', now()->subDays(30)))
                ->orWhere(fn ($unused) => $unused->whereNull('last_used_at')->where('created_at', '<', now()->subMonths(6)))
                ->orWhere('last_used_at', '<', now()->subMonths(6)))
            ->delete();

        $this->info("Token: {$tokens} · Codici: {$codes} · Cambi email: {$emailChanges} · Riserve: {$reservations} · Notifiche: {$notifications} · Iscrizioni push: {$pushSubscriptions}");

        return self::SUCCESS;
    }
}
