<?php

namespace App\Providers;

use App\Models\EmailChangeRequest;
use App\Models\Setting;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\AvailabilityService;
use App\Services\BlocklistService;
use App\Services\ImapSentFolder;
use App\Services\WhatsApp\CloudApiWhatsAppClient;
use App\Services\WhatsApp\LogWhatsAppClient;
use App\Services\WhatsApp\WhatsAppClient;
use App\Support\WhatsAppConfig;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(BlocklistService::class);
        $this->app->scoped(ActivityLogger::class);
        $this->app->scoped(AvailabilityService::class);
        $this->app->bind(ImapSentFolder::class, fn (): ImapSentFolder => ImapSentFolder::fromConfig());

        // Senza token e ID del numero la verifica WhatsApp resta in modalità prova.
        $this->app->bind(WhatsAppClient::class, function (): WhatsAppClient {
            $config = config('services.whatsapp');

            if (empty($config['token']) || empty($config['phone_number_id'])) {
                return new LogWhatsAppClient;
            }

            return new CloudApiWhatsAppClient($config['token'], $config['phone_number_id'], $config['graph_version'] ?? 'v21.0');
        });
    }

    public function boot(): void
    {
        // Added here rather than in bootstrap/app.php, so that installations that mount
        // only the app folders (not bootstrap/) get them too.
        // Language of every page: the user's choice, the visitor's choice or the browser language.
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', \App\Http\Middleware\SetLocale::class);
        $router->pushMiddlewareToGroup('web', \App\Http\Middleware\EnsureSetupCompleted::class);
        $router->pushMiddlewareToGroup('web', \App\Http\Middleware\RunSchedulerFromWeb::class);

        // Chiavi WhatsApp inserite dal pannello del venditore (hanno la precedenza sulle variabili d'ambiente).
        WhatsAppConfig::apply();

        Gate::before(function ($user, string $ability) {
            if ($user instanceof User && $user->isSeller()) {
                return true;
            }

            return null;
        });
        VerifyEmail::toMailUsing(function (object $notifiable) {
            $hours = (int) Setting::get('security.email_verification_ttl_hours', 48);

            $url = URL::temporarySignedRoute(
                'verification.verify',
                now()->addHours($hours),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]
            );

            $fullName = trim(($notifiable->first_name ?? '').' '.($notifiable->last_name ?? ''))
                ?: $notifiable->name;

            $store = [
                'name' => Setting::get('store.name', config('app.name')),
                'address' => Setting::get('store.address', ''),
                'vat' => Setting::get('store.vat', ''),
            ];

            // Se c'è un cambio email in corso, il testo è quello specifico del cambio.
            $pendingChange = EmailChangeRequest::query()
                ->where('user_id', $notifiable->getKey())
                ->pending()
                ->latest()
                ->first();

            if ($pendingChange) {
                return (new MailMessage)
                    ->subject(__('Conferma il tuo nuovo indirizzo email'))
                    ->markdown('emails.verify-email-change', [
                        'url' => $url,
                        'name' => $fullName,
                        'hours' => $hours,
                        'revokeHours' => (int) Setting::get('security.email_change_revoke_hours', 48),
                        'oldEmail' => $pendingChange->old_email,
                        'store' => $store,
                    ]);
            }

            return (new MailMessage)
                ->subject(__('Conferma il tuo indirizzo email'))
                ->markdown('emails.verify-email', [
                    'url' => $url,
                    'name' => $fullName,
                    'hours' => $hours,
                    'store' => $store,
                ]);
        });
        // Scadenza del link di recupero password presa dalle impostazioni (in minuti).
        config([
            'auth.passwords.users.expire' => (int) Setting::get('security.password_reset_ttl_hours', 2) * 60,
        ]);

        // Password reset email in the language of the user.
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $hours = (int) Setting::get('security.password_reset_ttl_hours', 2);

            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForVerification(),
            ]);

            $fullName = trim(($notifiable->first_name ?? '').' '.($notifiable->last_name ?? ''))
                ?: $notifiable->name;

            return (new MailMessage)
                ->subject(__('Cambia la tua password'))
                ->markdown('emails.password-reset', [
                    'url' => $url,
                    'name' => $fullName,
                    'hours' => $hours,
                    'store' => [
                        'name' => Setting::get('store.name', config('app.name')),
                        'address' => Setting::get('store.address', ''),
                        'vat' => Setting::get('store.vat', ''),
                    ],
                ]);
        });
    }
}
