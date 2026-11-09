<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\NotificationQueueItem;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Invio delle notifiche del browser a tutti i dispositivi attivi del destinatario.
 * Ogni invio ha un link firmato con cui il service worker conferma la ricezione.
 */
class WebPushService
{
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    public function ackMinutes(): int
    {
        return max(1, (int) Setting::get('notifications.push_ack_minutes', 10));
    }

    /**
     * @return array{sent: int, expired: int, failed: int}
     */
    public function send(NotificationQueueItem $item): array
    {
        $result = ['sent' => 0, 'expired' => 0, 'failed' => 0];
        $subscriptions = PushSubscription::query()->active()->where('user_id', $item->user_id)->get();

        if ($subscriptions->isEmpty() || ! $this->isConfigured()) {
            return $result;
        }

        $webPush = $this->client();

        foreach ($subscriptions as $subscription) {
            $delivery = PushDelivery::create([
                'notification_queue_id' => $item->id,
                'push_subscription_id' => $subscription->id,
                'result' => PushDelivery::RESULT_SENT,
            ]);

            try {
                $report = $webPush->sendOneNotification(
                    Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->public_key,
                        'authToken' => $subscription->auth_token,
                        'contentEncoding' => $subscription->content_encoding,
                    ]),
                    json_encode($this->payload($item, $delivery), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    // La notifica scade quando parte l'email di riserva: un dispositivo spento non la mostra dopo.
                    ['TTL' => $this->ackMinutes() * 60, 'urgency' => 'normal'],
                );
            } catch (Throwable $exception) {
                $delivery->update(['result' => PushDelivery::RESULT_ERROR, 'error' => mb_substr($exception->getMessage(), 0, 500)]);
                $subscription->update(['last_result' => 'error']);
                $result['failed']++;

                continue;
            }

            if ($report->isSuccess()) {
                $delivery->update(['sent_at' => now()]);
                $subscription->update(['last_result' => 'sent', 'last_used_at' => now()]);
                $result['sent']++;
            } elseif ($report->isSubscriptionExpired()) {
                $delivery->update(['result' => PushDelivery::RESULT_EXPIRED, 'error' => mb_substr($report->getReason(), 0, 500)]);
                $this->deactivate($subscription, 'expired');
                $result['expired']++;
            } else {
                $delivery->update(['result' => PushDelivery::RESULT_ERROR, 'error' => mb_substr($report->getReason(), 0, 500)]);
                $subscription->update(['last_result' => 'error']);
                $result['failed']++;
            }
        }

        return $result;
    }

    /**
     * Disattiva un'iscrizione. Se il cliente resta senza dispositivi attivi, le sue preferenze
     * tornano su email e l'area personale mostra un avviso.
     */
    public function deactivate(PushSubscription $subscription, string $reason): void
    {
        $subscription->update(['is_active' => false, 'last_result' => $reason]);

        $user = $subscription->user;

        if ($user && ! $user->pushSubscriptions()->active()->exists()) {
            $this->revertToEmail($user);
        }
    }

    public function revertToEmail(User $user): void
    {
        $switched = $user->notificationPreferences()
            ->where('channel', NotificationPreference::CHANNEL_BROWSER)
            ->update(['channel' => NotificationPreference::CHANNEL_EMAIL]);

        if ($switched > 0) {
            $user->forceFill(['push_revoked_at' => now()])->save();
        }
    }

    /**
     * Conferma di ricezione inviata dal service worker.
     */
    public function acknowledge(PushDelivery $delivery): void
    {
        if ($delivery->received_at) {
            return;
        }

        $delivery->update(['received_at' => now(), 'result' => PushDelivery::RESULT_RECEIVED]);

        $notification = $delivery->notification;

        if ($notification && $notification->status === NotificationQueueItem::STATUS_AWAITING_ACK) {
            $notification->update([
                'status' => NotificationQueueItem::STATUS_SENT,
                'delivered_via' => NotificationQueueItem::CHANNEL_PUSH,
            ]);
        }
    }

    /**
     * Contenuto breve e senza dati sensibili: titolo e link alla pagina.
     *
     * @return array<string, string>
     */
    private function payload(NotificationQueueItem $item, PushDelivery $delivery): array
    {
        return [
            'title' => $item->subject,
            'body' => __('Tocca per vedere i dettagli.'),
            'url' => $item->url ?: route('home'),
            'tag' => 'notifica-'.$item->id,
            'ack' => URL::temporarySignedRoute('push.ack', now()->addMinutes($this->ackMinutes() + 30), ['delivery' => $delivery->id]),
        ];
    }

    private function client(): WebPush
    {
        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => config('services.webpush.public_key'),
                'privateKey' => config('services.webpush.private_key'),
            ],
        ]);

        $webPush->setReuseVAPIDHeaders(true);

        return $webPush;
    }
}
