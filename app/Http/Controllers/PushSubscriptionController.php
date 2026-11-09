<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\PushDelivery;
use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Iscrizioni del browser alle notifiche e conferme di ricezione dal service worker.
 */
class PushSubscriptionController extends Controller
{
    public function __construct(private WebPushService $webPush) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'starts_with:https://', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        $user = $request->user();

        // Lo stesso browser può passare da un account all'altro: l'iscrizione segue chi l'ha attivata per ultimo.
        PushSubscription::query()->updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])],
            [
                'user_id' => $user->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'device' => $this->deviceLabel((string) $request->userAgent()),
                'is_active' => true,
                'last_result' => 'subscribed',
            ],
        );

        $user->forceFill(['push_revoked_at' => null])->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Il sito, all'apertura, ha visto che il permesso del browser non c'è più.
     */
    public function revoked(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['nullable', 'string', 'max:2000']]);
        $user = $request->user();

        if (! empty($data['endpoint'])) {
            $subscription = $user->pushSubscriptions()
                ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))
                ->first();

            if ($subscription?->is_active) {
                $this->webPush->deactivate($subscription, 'revoked');
            }
        } elseif (! $user->pushSubscriptions()->active()->exists()) {
            $this->webPush->revertToEmail($user);
        }

        $stillUsesBrowser = $user->notificationPreferences()->where('channel', NotificationPreference::CHANNEL_BROWSER)->exists();

        return response()->json(['revertedToEmail' => ! $stillUsesBrowser && $user->fresh()->push_revoked_at !== null]);
    }

    public function acknowledge(PushDelivery $delivery): Response
    {
        $this->webPush->acknowledge($delivery);

        return response()->noContent();
    }

    private function deviceLabel(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };

        $system = match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'dispositivo',
        };

        return $browser.' su '.$system;
    }
}
