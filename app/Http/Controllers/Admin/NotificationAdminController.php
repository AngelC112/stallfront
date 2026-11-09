<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LocalizedText;
use App\Models\NotificationEventSetting;
use App\Models\NotificationPreference;
use App\Models\Setting;
use App\Services\NotificationService;
use App\Support\Locales;
use App\Support\NotificationEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Notifiche ai clienti": importanza di ogni evento (sempre email o a scelta del cliente)
 * e, in una pagina a parte, i testi dei messaggi.
 */
class NotificationAdminController extends Controller
{
    public function index(Request $request, NotificationService $notifications): View
    {
        $events = $this->events($notifications);
        $sellerChannels = $request->user()->notificationPreferences()->pluck('channel', 'event');

        return view('admin.notifications.index', [
            'customerEvents' => $events->where('recipient', NotificationEvent::RECIPIENT_CUSTOMER),
            'sellerEvents' => $events->where('recipient', NotificationEvent::RECIPIENT_SELLER)
                ->map(fn (array $definition, string $event): array => $definition + [
                    'uses_browser' => ($sellerChannels[$event] ?? null) === NotificationPreference::CHANNEL_BROWSER,
                ]),
            'ackMinutes' => (int) Setting::get('notifications.push_ack_minutes', 10),
        ]);
    }

    /**
     * Salva l'importanza degli eventi per il cliente.
     */
    public function updateImportance(Request $request, NotificationService $notifications): RedirectResponse
    {
        $customerEvents = $this->events($notifications)->where('recipient', NotificationEvent::RECIPIENT_CUSTOMER);

        $validated = $request->validate([
            'importance' => ['required', 'array'],
            'importance.*' => [Rule::in([NotificationEvent::IMPORTANCE_ALWAYS_EMAIL, NotificationEvent::IMPORTANCE_CUSTOMER_CHOICE])],
        ]);

        foreach ($customerEvents as $event => $definition) {
            if (! isset($validated['importance'][$event])) {
                continue;
            }

            NotificationEventSetting::query()->updateOrCreate(['event' => $event], [
                'importance' => $validated['importance'][$event],
                'is_active' => $definition['is_active'],
            ]);
        }

        return redirect()->route('admin.notifications.index')->with('success', __('Notifiche salvate.'));
    }

    /**
     * Elenco dei messaggi con il loro testo, per scegliere quale modificare.
     */
    public function texts(NotificationService $notifications): View
    {
        return view('admin.notifications.texts', ['events' => $this->events($notifications)]);
    }

    /**
     * Testo di un messaggio in una lingua (?lang=xx, di default quella del pannello).
     */
    public function edit(Request $request, string $event, NotificationService $notifications): View
    {
        abort_unless(array_key_exists($event, NotificationEvent::all()), 404);

        $locale = Locales::normalize($request->query('lang')) ?? app()->getLocale();

        return view('admin.notifications.edit', [
            'event' => $event,
            'locale' => $locale,
            'definition' => $notifications->definition($event),
            'text' => $notifications->definition($event, $locale),
            'customizedLocales' => LocalizedText::localesFor('notification.'.$event),
            'placeholders' => NotificationEvent::placeholders(),
        ]);
    }

    public function update(Request $request, string $event, NotificationService $notifications): RedirectResponse
    {
        $default = NotificationEvent::all()[$event] ?? abort(404);

        $validated = $request->validate([
            'locale' => ['required', Rule::in(array_keys(Locales::all()))],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'importance' => ['nullable', Rule::in([NotificationEvent::IMPORTANCE_ALWAYS_EMAIL, NotificationEvent::IMPORTANCE_CUSTOMER_CHOICE])],
        ]);

        NotificationEventSetting::updateOrCreate(['event' => $event], [
            'is_active' => $request->boolean('is_active'),
            'importance' => $default['recipient'] === NotificationEvent::RECIPIENT_CUSTOMER
                ? ($validated['importance'] ?? $notifications->definition($event)['importance'])
                : $default['importance'],
        ]);

        $key = 'notification.'.$event;
        $builtIn = $notifications->definition($event, $validated['locale']);

        if ($request->boolean('reset')) {
            LocalizedText::remove($key, $validated['locale']);
        } elseif (! $builtIn['customized'] && $builtIn['subject'] === $validated['subject'] && $builtIn['body'] === str_replace("\r\n", "\n", $validated['body'])) {
            // Text unchanged: keep following the built-in text (and its future improvements).
        } else {
            LocalizedText::put($key, $validated['locale'], $validated['subject'], str_replace("\r\n", "\n", $validated['body']));
        }

        return redirect()
            ->route('admin.notifications.edit', ['event' => $event, 'lang' => $validated['locale']])
            ->with('success', __('Messaggio aggiornato.'));
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    private function events(NotificationService $notifications): Collection
    {
        return collect(NotificationEvent::all())
            ->keys()
            ->mapWithKeys(fn (string $event): array => [$event => $notifications->definition($event)]);
    }
}
