<?php

namespace App\Services;

use App\Mail\PasswordChangedMail;
use App\Mail\TemplatedMail;
use App\Models\EmailChangeRequest;
use App\Models\NotificationQueueItem;
use App\Models\Order;
use App\Models\PhoneVerification;
use App\Models\Setting;
use App\Models\User;
use App\Models\VerificationToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class AccountSecurityService
{
    /**
     * Chiude tutte le sessioni dell’utente tranne quella corrente e invalida il “ricordami”.
     */
    public function terminateOtherSessions(User $user, ?string $currentSessionId = null): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->when($currentSessionId, fn ($query) => $query->where('id', '!=', $currentSessionId))
            ->delete();

        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    /**
     * Dopo un cambio password: chiude le altre sessioni e avvisa via email (sempre, non disattivabile).
     */
    public function passwordChanged(User $user, ?string $currentSessionId = null): void
    {
        $this->terminateOtherSessions($user, $currentSessionId);

        try {
            Mail::to($user->email)->locale($user->preferredLocale())->send(new PasswordChangedMail($user));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Dati personali e ordini dell’utente, per l’esportazione su richiesta (GDPR).
     *
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'esportato_il' => now()->toIso8601String(),
            'profilo' => [
                'nome' => $user->first_name,
                'cognome' => $user->last_name,
                'email' => $user->email,
                'email_verificata_il' => $user->email_verified_at?->toIso8601String(),
                'cellulare' => $user->phone,
                'cellulare_verificato_il' => $user->phone_verified_at?->toIso8601String(),
                'indirizzo' => $user->address,
                'consenso_privacy_il' => $user->privacy_accepted_at?->toIso8601String(),
                'consenso_termini_il' => $user->terms_accepted_at?->toIso8601String(),
                'mancati_ritiri' => $user->no_show_count,
                'registrato_il' => $user->created_at?->toIso8601String(),
            ],
            'ordini' => $user->orders()->with(['items', 'statusHistories'])->orderBy('placed_at')->get()->map(fn (Order $order): array => [
                'codice' => $order->order_number,
                'effettuato_il' => $order->placed_at?->toIso8601String(),
                'stato' => $order->statusLabel(),
                'modalita' => $order->fulfillmentMethodLabel(),
                'mercato' => $order->market_name,
                'data_ritiro' => $order->pickup_date?->toDateString(),
                'indirizzo_consegna' => $order->delivery_address,
                'nota' => $order->customer_note,
                'totale_euro' => $order->total_cents / 100,
                'prodotti' => $order->items->map(fn ($item): array => [
                    'nome' => $item->product_name,
                    'variante' => $item->variant_label,
                    'quantita' => $item->quantity,
                    'prezzo_unitario_euro' => $item->unit_price_cents / 100,
                ])->all(),
                'storico_stati' => $order->statusHistories->map(fn ($entry): array => [
                    'stato' => Order::labelFor($entry->to_status, $order->fulfillment_method),
                    'il' => $entry->created_at?->toIso8601String(),
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * Elimina account, dati personali e ordini. Gli ordini aperti vengono prima annullati,
     * così la giacenza torna disponibile. Le impronte cifrate dei clienti bloccati restano.
     */
    public function deleteAccount(User $user): void
    {
        $email = $user->email;
        $name = $user->fullName();
        $statusService = app(OrderStatusService::class);

        DB::transaction(function () use ($user, $statusService): void {
            foreach ($user->orders()->get() as $order) {
                if ($statusService->canTransition($order, Order::STATUS_CANCELLED, OrderStatusService::ACTOR_SYSTEM)) {
                    $statusService->transition($order, Order::STATUS_CANCELLED, OrderStatusService::ACTOR_SYSTEM, null, __('Account eliminato dal cliente'));
                }
            }

            // Ordini, righe, note interne, storico e notifiche collegate si eliminano a cascata.
            $user->orders()->delete();
            NotificationQueueItem::query()->where('user_id', $user->id)->delete();
            EmailChangeRequest::query()->where('user_id', $user->id)->delete();
            PhoneVerification::query()->where('user_id', $user->id)->delete();
            VerificationToken::query()->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();

            app(ActivityLogger::class)->log('account.deleted', null, ['user_id' => $user->id]);

            $user->delete();
        });

        try {
            Mail::to($email)->send(new TemplatedMail(
                __('Il tuo account è stato eliminato'),
                __("Ciao :name,\nil tuo account presso :store è stato eliminato insieme ai tuoi dati e ai tuoi ordini.", ['name' => $name, 'store' => Setting::get('store.name', config('app.name'))]),
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
