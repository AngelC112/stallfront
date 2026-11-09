<?php

namespace App\Services;

use App\Mail\TemplatedMail;
use App\Models\LocalizedText;
use App\Models\NotificationEventSetting;
use App\Models\NotificationPreference;
use App\Models\NotificationQueueItem;
use App\Models\Order;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Models\Setting;
use App\Models\User;
use App\Support\Format;
use App\Support\Localized;
use App\Support\NotificationEvent;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    public const MAX_ATTEMPTS = 3;

    private const STATUS_EVENTS = [
        Order::STATUS_PREPARING => NotificationEvent::ORDER_PREPARING,
        Order::STATUS_READY => NotificationEvent::ORDER_READY,
        Order::STATUS_OUT_FOR_DELIVERY => NotificationEvent::ORDER_OUT_FOR_DELIVERY,
        Order::STATUS_COLLECTED => NotificationEvent::ORDER_COLLECTED,
        Order::STATUS_DELIVERED => NotificationEvent::ORDER_DELIVERED,
        Order::STATUS_NOT_COLLECTED => NotificationEvent::ORDER_NOT_COLLECTED,
        Order::STATUS_NOT_DELIVERED => NotificationEvent::ORDER_NOT_DELIVERED,
        Order::STATUS_RESCHEDULED => NotificationEvent::ORDER_RESCHEDULED,
        Order::STATUS_CANCELLED => NotificationEvent::ORDER_CANCELLED,
    ];

    /**
     * Definizione dell’evento con le personalizzazioni del venditore applicate,
     * con i testi nella lingua indicata (di default quella corrente).
     *
     * @return array{label: string, recipient: string, importance: string, subject: string, body: string, is_active: bool, customized: bool}
     */
    public function definition(string $event, ?string $locale = null): array
    {
        return Localized::run($locale ?? app()->getLocale(), function () use ($event): array {
            $default = NotificationEvent::all()[$event];
            $override = NotificationEventSetting::query()->find($event);
            $text = LocalizedText::find('notification.'.$event, app()->getLocale());

            return [
                'label' => $default['label'],
                'recipient' => $default['recipient'],
                'importance' => $override?->importance ?: $default['importance'],
                'subject' => ($text['title'] ?? null) ?: $default['subject'],
                'body' => ($text['body'] ?? null) ?: $default['body'],
                'is_active' => $override?->is_active ?? true,
                'customized' => $text !== null,
            ];
        });
    }

    /**
     * Lingua di chi riceve la notifica.
     */
    private function localeFor(?int $userId): ?string
    {
        return $userId ? User::query()->find($userId)?->preferredLocale() : null;
    }

    /**
     * Accoda la notifica collegata al cambio di stato di un ordine.
     * Non avvisa il cliente delle azioni che ha fatto lui stesso.
     */
    public function orderStatusChanged(Order $order, string $newStatus, string $actor, ?string $reason = null): void
    {
        $event = self::STATUS_EVENTS[$newStatus] ?? null;

        if ($event === null || $actor === OrderStatusService::ACTOR_CUSTOMER) {
            return;
        }

        if ($newStatus === Order::STATUS_CANCELLED && $reason === NoShowService::BLOCK_REASON) {
            return;
        }

        $this->queueForOrder($event, $order, ['motivazione' => (string) $reason]);
    }

    /**
     * @param  array<string, string>  $vars
     */
    public function queueForOrder(string $event, Order $order, array $vars = []): ?NotificationQueueItem
    {
        $isSeller = NotificationEvent::all()[$event]['recipient'] === NotificationEvent::RECIPIENT_SELLER;
        $seller = $isSeller ? $this->seller() : null;
        $recipient = $isSeller ? $seller?->email : $order->customer_email;
        $userId = $isSeller ? $seller?->id : $order->user_id;

        if (! $recipient) {
            return null;
        }

        return Localized::run($this->localeFor($userId), function () use ($event, $order, $vars, $isSeller, $recipient, $userId): ?NotificationQueueItem {
            $definition = $this->definition($event);

            if (! $definition['is_active']) {
                return null;
            }

            return $this->queue($event, $definition, $recipient, $userId, $order->id, array_merge($this->orderVars($order, $isSeller), $vars));
        });
    }

    /**
     * @param  array<string, string>  $vars
     */
    public function queueForUser(string $event, User $user, array $vars = []): ?NotificationQueueItem
    {
        return Localized::run($user->preferredLocale(), function () use ($event, $user, $vars): ?NotificationQueueItem {
            $definition = $this->definition($event);

            if (! $definition['is_active']) {
                return null;
            }

            return $this->queue($event, $definition, $user->email, $user->id, null, ['nome' => $user->fullName()] + $vars);
        });
    }

    /**
     * @param  array<string, string>  $vars
     */
    public function queueForSeller(string $event, array $vars = []): ?NotificationQueueItem
    {
        $seller = $this->seller();

        if (! $seller) {
            return null;
        }

        return Localized::run($seller->preferredLocale(), function () use ($event, $seller, $vars): ?NotificationQueueItem {
            $definition = $this->definition($event);

            if (! $definition['is_active']) {
                return null;
            }

            return $this->queue($event, $definition, $seller->email, $seller->id, null, $vars);
        });
    }

    /**
     * Canale effettivo per un destinatario: browser solo se l'evento lo consente
     * (a scelta del cliente, oppure avviso al venditore), se l'ha scelto e ha almeno un dispositivo attivo.
     *
     * @param  array<string, mixed>  $definition
     */
    public function channelFor(string $event, array $definition, ?int $userId): string
    {
        $canUseBrowser = $definition['importance'] === NotificationEvent::IMPORTANCE_CUSTOMER_CHOICE
            || $definition['recipient'] === NotificationEvent::RECIPIENT_SELLER;

        if (! $userId || ! $canUseBrowser || ! app(WebPushService::class)->isConfigured()) {
            return NotificationQueueItem::CHANNEL_EMAIL;
        }

        $wantsBrowser = NotificationPreference::query()
            ->where('user_id', $userId)
            ->where('event', $event)
            ->where('channel', NotificationPreference::CHANNEL_BROWSER)
            ->where('is_active', true)
            ->exists();

        if (! $wantsBrowser || ! PushSubscription::query()->active()->where('user_id', $userId)->exists()) {
            return NotificationQueueItem::CHANNEL_EMAIL;
        }

        return NotificationQueueItem::CHANNEL_PUSH;
    }

    /**
     * Invia le notifiche in attesa. Chi fallisce viene ritentato fino a MAX_ATTEMPTS.
     * Le notifiche del browser senza conferma di ricezione entro l'attesa partono per email.
     *
     * @return array{sent: int, failed: int}
     */
    public function dispatchPending(int $limit = 50): array
    {
        $this->sendExpiredPushFallbacks();

        $items = NotificationQueueItem::query()
            ->where('status', NotificationQueueItem::STATUS_PENDING)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($items as $item) {
            if ($item->channel === NotificationQueueItem::CHANNEL_PUSH && $this->sendPush($item)) {
                $sent++;

                continue;
            }

            $this->sendEmail($item) ? $sent++ : $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Invio sul browser. Falso se nessun dispositivo l'ha accettata: allora parte subito l'email.
     */
    private function sendPush(NotificationQueueItem $item): bool
    {
        $webPush = app(WebPushService::class);
        $result = $webPush->send($item);

        if ($result['sent'] > 0) {
            $item->update([
                'status' => NotificationQueueItem::STATUS_AWAITING_ACK,
                'attempts' => $item->attempts + 1,
                'sent_at' => now(),
                'awaiting_ack_until' => now()->addMinutes($webPush->ackMinutes()),
            ]);

            return true;
        }

        // Permesso revocato su tutti i dispositivi: il cliente viene avvisato insieme all'email di riserva.
        if ($result['expired'] > 0 && $item->user && ! $item->user->pushSubscriptions()->active()->exists()) {
            $item->body .= "\n\n—\n".Localized::run($item->user->preferredLocale(), fn (): string => __('Le notifiche del browser risultano disattivate su questo dispositivo, quindi da ora ti scriviamo per email. Puoi riattivarle dalla tua area personale, sezione Notifiche.'));
        }

        return false;
    }

    private function sendEmail(NotificationQueueItem $item): bool
    {
        $isFallback = $item->channel === NotificationQueueItem::CHANNEL_PUSH;

        try {
            Mail::to($item->recipient_email)
                ->locale($this->localeFor($item->user_id) ?? \App\Support\Locales::fallback())
                ->send(new TemplatedMail($item->subject, $item->body));

            $item->update([
                'body' => $item->body,
                'status' => NotificationQueueItem::STATUS_SENT,
                'attempts' => $item->attempts + 1,
                'sent_at' => now(),
                'last_error' => null,
                'delivered_via' => $isFallback ? 'email_fallback' : NotificationQueueItem::CHANNEL_EMAIL,
            ]);

            if ($isFallback) {
                $item->pushDeliveries()->whereNull('received_at')->update(['result' => PushDelivery::RESULT_EMAIL_FALLBACK]);
            }

            return true;
        } catch (Throwable $exception) {
            $attempts = $item->attempts + 1;

            $item->update([
                'attempts' => $attempts,
                'last_error' => mb_substr($exception->getMessage(), 0, 500),
                'status' => $attempts >= self::MAX_ATTEMPTS
                    ? NotificationQueueItem::STATUS_ERROR
                    : NotificationQueueItem::STATUS_PENDING,
            ]);

            return false;
        }
    }

    /**
     * Nessun dispositivo ha confermato la ricezione entro l'attesa: stessa notifica per email.
     */
    private function sendExpiredPushFallbacks(): void
    {
        NotificationQueueItem::query()
            ->where('status', NotificationQueueItem::STATUS_AWAITING_ACK)
            ->where('awaiting_ack_until', '<=', now())
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(function (NotificationQueueItem $item): void {
                $item->attempts = 0;
                $this->sendEmail($item);
            });
    }

    /**
     * @param  array<string, string>  $vars
     */
    public function render(string $template, array $vars): string
    {
        $replacements = [];
        $aliases = NotificationEvent::placeholderAliases();
        $italian = array_flip($aliases);

        // The variables use the Italian names; templates may use the English or the Italian ones.
        foreach ($vars as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;

            if (isset($italian[$key])) {
                $replacements['{'.$italian[$key].'}'] = (string) $value;
            }
        }

        return strtr($template, $replacements);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $vars
     */
    private function queue(string $event, array $definition, string $recipient, ?int $userId, ?int $orderId, array $vars): NotificationQueueItem
    {
        return NotificationQueueItem::create([
            'event' => $event,
            'user_id' => $userId,
            'order_id' => $orderId,
            'recipient_email' => $recipient,
            'channel' => $this->channelFor($event, $definition, $userId),
            'subject' => $this->render($definition['subject'], $vars),
            'body' => $this->render($definition['body'], $vars),
            'url' => $vars['link_ordine'] ?? null,
            'status' => NotificationQueueItem::STATUS_PENDING,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function orderVars(Order $order, bool $forSeller): array
    {
        $isDelivery = $order->fulfillment_method === Order::FULFILLMENT_DELIVERY;

        return [
            'nome' => (string) $order->customer_name,
            'codice_ordine' => $order->order_number,
            'data' => $order->pickup_date ? Format::date($order->pickup_date) : '-',
            'mercato' => (string) ($order->market_name ?? '-'),
            'totale' => Format::money($order->total_cents),
            'dettaglio' => $isDelivery
                ? __('Sarà consegnato a domicilio entro :hours ore.', ['hours' => (int) Setting::get('delivery.time_hours', 24)])
                : __('Puoi ritirarlo il :date presso :market.', [
                    'date' => $order->pickup_date ? Format::date($order->pickup_date) : '-',
                    'market' => $order->market_name ?? __('il mercato'),
                ]),
            'link_ordine' => $forSeller
                ? route('admin.orders.show', $order)
                : route('account.orders.show', ['order' => $order->order_number]),
            'motivazione' => '',
        ];
    }

    private function seller(): ?User
    {
        return User::query()->where('role', User::ROLE_SELLER)->orderBy('id')->first();
    }
}
