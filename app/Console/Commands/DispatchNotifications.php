<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

class DispatchNotifications extends Command
{
    protected $signature = 'notifications:dispatch {--limit=50 : Maximum number of notifications per run}';

    protected $description = 'Sends the queued notifications, retrying on errors';

    public function handle(NotificationService $notifications): int
    {
        $result = $notifications->dispatchPending((int) $this->option('limit'));

        $this->info("Inviate: {$result['sent']} · Errori: {$result['failed']}");

        return self::SUCCESS;
    }
}
