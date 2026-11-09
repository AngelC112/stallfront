<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\WebPushService;
use App\Support\NotificationEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Area personale, sezione Notifiche: per gli eventi "a scelta" il cliente sceglie email o browser.
 * Gli eventi "sempre email" sono mostrati ma non modificabili.
 */
class NotificationPreferenceController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private WebPushService $webPush,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        $channels = $user->notificationPreferences()->pluck('channel', 'event');
        $events = collect($this->eventsFor($user))->map(fn (array $definition, string $event): array => [
            'event' => $event,
            'label' => $definition['label'],
            'choice' => $this->isChoice($definition),
            'channel' => $channels[$event] ?? NotificationPreference::CHANNEL_EMAIL,
        ]);

        return view('account.notifications', [
            'choiceEvents' => $events->where('choice', true)->values(),
            'emailEvents' => $events->where('choice', false)->values(),
            'devices' => $user->pushSubscriptions()->orderByDesc('is_active')->latest('updated_at')->get(),
            'pushConfigured' => $this->webPush->isConfigured(),
            'vapidPublicKey' => (string) config('services.webpush.public_key'),
            'revokedNotice' => $user->push_revoked_at,
            'usesBrowser' => $channels->contains(NotificationPreference::CHANNEL_BROWSER),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $choiceEvents = collect($this->eventsFor($user))->filter(fn (array $definition): bool => $this->isChoice($definition))->keys();

        $data = $request->validate([
            'channels' => ['array'],
            'channels.*' => [Rule::in([NotificationPreference::CHANNEL_EMAIL, NotificationPreference::CHANNEL_BROWSER])],
        ]);

        foreach ($choiceEvents as $event) {
            $channel = $data['channels'][$event] ?? NotificationPreference::CHANNEL_EMAIL;

            if ($channel === NotificationPreference::CHANNEL_BROWSER && ! $this->webPush->isConfigured()) {
                $channel = NotificationPreference::CHANNEL_EMAIL;
            }

            $user->notificationPreferences()->updateOrCreate(['event' => $event], ['channel' => $channel, 'is_active' => true]);
        }

        return redirect()->route('account.notifications.edit')->with('status', 'notifications-updated');
    }

    public function dismissRevokedNotice(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['push_revoked_at' => null])->save();

        return redirect()->route('account.notifications.edit');
    }

    /**
     * Eventi attivi che riguardano l'utente: quelli dei clienti, più gli avvisi del venditore se è il venditore.
     *
     * @return array<string, array<string, mixed>>
     */
    private function eventsFor(User $user): array
    {
        $events = [];

        foreach (array_keys(NotificationEvent::all()) as $event) {
            $definition = $this->notifications->definition($event);
            $isForSeller = $definition['recipient'] === NotificationEvent::RECIPIENT_SELLER;

            if ($definition['is_active'] && (! $isForSeller || $user->isSeller())) {
                $events[$event] = $definition;
            }
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function isChoice(array $definition): bool
    {
        return $definition['importance'] === NotificationEvent::IMPORTANCE_CUSTOMER_CHOICE
            || $definition['recipient'] === NotificationEvent::RECIPIENT_SELLER;
    }
}
