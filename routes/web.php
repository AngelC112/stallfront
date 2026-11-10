<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\AddressSuggestionController;
use App\Http\Controllers\Admin\ActivityLogAdminController;
use App\Http\Controllers\Admin\BancoController;
use App\Http\Controllers\Admin\BlockNoticeAdminController;
use App\Http\Controllers\Admin\CategoryAdminController;
use App\Http\Controllers\Admin\CollaboratorAdminController;
use App\Http\Controllers\Admin\CustomerAdminController;
use App\Http\Controllers\Admin\CustomerEmailAdminController;
use App\Http\Controllers\Admin\DashboardAdminController;
use App\Http\Controllers\Admin\EmailLogAdminController;
use App\Http\Controllers\Admin\HolidayAdminController;
use App\Http\Controllers\Admin\LegalPageAdminController;
use App\Http\Controllers\Admin\MarketAdminController;
use App\Http\Controllers\Admin\NotificationAdminController;
use App\Http\Controllers\Admin\OrderAdminController;
use App\Http\Controllers\Admin\ProductAdminController;
use App\Http\Controllers\Admin\SettingsAdminController;
use App\Http\Controllers\Admin\WhatsAppSettingsController;
use App\Http\Controllers\BackInStockSubscriptionController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\EmailChangeRevocationController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PhoneVerificationController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\QrCodeController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\WhereWeAreController;
use App\Models\Setting;
use App\Support\Permission;
use Illuminate\Support\Facades\Route;

// Revoca del cambio email: il link arriva al vecchio indirizzo,
// quindi deve funzionare anche senza essere loggati.
Route::prefix('cambio-email')->name('email-change.')->group(function () {
    Route::get('/revoca/{token}', [EmailChangeRevocationController::class, 'show'])
        ->name('revoke.form');

    Route::post('/revoca/{token}', [EmailChangeRevocationController::class, 'revoke'])
        ->middleware('throttle:10,1')
        ->name('revoke');
});

Route::get('/', [StorefrontController::class, 'index'])->name('home');

// First run: creation of the seller account
Route::get('/setup', [SetupController::class, 'show'])->name('setup.show');
Route::post('/setup', [SetupController::class, 'store'])->middleware('throttle:10,1')->name('setup.store');

// Language selector
Route::post('/locale', [LocaleController::class, 'update'])->middleware('throttle:30,1')->name('locale.update');

// Manifest dell'app: serve per installare il sito nella schermata Home (necessario per le notifiche su iPhone).
Route::get('/manifest.webmanifest', function () {
    $name = Setting::get('store.name', config('app.name', 'Vetrina'));

    return response()->json([
        'name' => $name,
        'short_name' => mb_substr($name, 0, 12),
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#f5f7f6',
        'theme_color' => '#125e5a',
        'lang' => \App\Support\Locales::htmlLang(),
        'icons' => [
            ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ],
    ], 200, ['Content-Type' => 'application/manifest+json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
})->name('manifest');

// Pagine legali
Route::get('/privacy-policy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/cookie-policy', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::get('/termini-di-vendita', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/dove-siamo', WhereWeAreController::class)->name('where-we-are');

// Suggerimenti di indirizzo (OpenStreetMap) per i moduli di registrazione e account.
Route::get('/indirizzi/suggerimenti', AddressSuggestionController::class)->middleware('throttle:40,1')->name('address.suggest');

// / QR Code per ordini
Route::get('/ordine/qr/{orderNumber}', [QrCodeController::class, 'show'])
    ->middleware('auth')
    ->name('qr.show');
Route::get('/ordine/qr/{orderNumber}/image', [QrCodeController::class, 'image'])
    ->middleware('auth')
    ->name('qr.image');
Route::get('/categorie/{category:slug}', [StorefrontController::class, 'category'])->name('storefront.category');
Route::get('/prodotti/{product:slug}', [StorefrontController::class, 'show'])->name('storefront.product');
Route::get('/carrello', [CartController::class, 'index'])->name('cart.index');
Route::post('/carrello/prodotti/{product:slug}', [CartController::class, 'store'])->name('cart.store');
Route::patch('/carrello/{lineKey}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/carrello/{lineKey}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
});
Route::post('/prodotti/{product:slug}/avviso-disponibilita', [BackInStockSubscriptionController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('back-in-stock.store');

// Il dashboard di Breeze non ci serve: dopo l’accesso smistiamo in base al ruolo.
// Venditore e collaboratori vanno al pannello (agli ordini se non possono vedere la dashboard), i clienti ai loro ordini.
Route::get('/dashboard', function () {
    $user = auth()->user();

    if ($user->isSeller() || $user->isCollaborator()) {
        return redirect()->route($user->hasPermission(Permission::DASHBOARD_VIEW) ? 'admin.dashboard' : 'admin.orders.index');
    }

    return redirect()->route('account.orders.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/email-confermata', function () {
    return view('auth.email-confirmed');
})->middleware(['auth', 'verified'])->name('verification.confirmed');

// Area cliente: dati personali e password.
Route::middleware(['auth', 'verified'])->prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'edit'])->name('edit');
    Route::patch('/', [AccountController::class, 'update'])->name('update');
    Route::put('password', [AccountController::class, 'updatePassword'])->name('password.update');
    Route::get('export', [AccountDeletionController::class, 'export'])->name('export');
    Route::post('delete', [AccountDeletionController::class, 'request'])->middleware('throttle:3,10')->name('delete.request');
    Route::get('delete/confirm/{user}', [AccountDeletionController::class, 'confirm'])->middleware('signed')->name('delete.confirm');
    Route::post('delete/confirm/{user}', [AccountDeletionController::class, 'destroy'])->middleware('signed')->name('delete.destroy');
    Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('orders/{order:order_number}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order:order_number}/reorder', [OrderController::class, 'reorder'])->name('orders.reorder');
    Route::post('orders/{order:order_number}/reschedule', [OrderController::class, 'reschedule'])->middleware('throttle:20,1')->name('orders.reschedule');

    // Verifica del cellulare con WhatsApp
    Route::get('phone/verify', [PhoneVerificationController::class, 'show'])->name('phone.verify');
    Route::post('phone/verify', [PhoneVerificationController::class, 'start'])->middleware('throttle:10,10')->name('phone.verify.start');
    Route::get('phone/verify/status', [PhoneVerificationController::class, 'status'])->name('phone.verify.status');
    Route::post('phone/verify/simulate', [PhoneVerificationController::class, 'simulate'])->name('phone.verify.simulate');

    // Preferenze di notifica e notifiche del browser
    Route::get('notifications', [NotificationPreferenceController::class, 'edit'])->name('notifications.edit');
    Route::put('notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.update');
    Route::post('notifications/dismiss-revoked', [NotificationPreferenceController::class, 'dismissRevokedNotice'])->name('notifications.dismiss-revoked');
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->middleware('throttle:20,1')->name('push.store');
    Route::post('push-subscriptions/revoked', [PushSubscriptionController::class, 'revoked'])->middleware('throttle:20,1')->name('push.revoked');
});

// Webhook WhatsApp (Meta) e conferma di ricezione delle notifiche del browser: senza sessione né CSRF.
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])->name('webhooks.whatsapp.verify');
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])->middleware('throttle:120,1')->name('webhooks.whatsapp.receive');
Route::post('/push/ack/{delivery}', [PushSubscriptionController::class, 'acknowledge'])->middleware(['signed', 'throttle:60,1'])->name('push.ack');

// ... altre rotte ...

Route::middleware(['auth', 'verified', 'role:seller,collaborator'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Dashboard
        Route::get('/', DashboardAdminController::class)
            ->middleware('permission:dashboard.view')->name('dashboard');

        // Categorie
        Route::resource('categories', CategoryAdminController::class)
            ->except(['show'])
            ->middleware('permission:categories.manage');

        // Mercati e calendario di ritiro
        Route::resource('markets', MarketAdminController::class)
            ->except(['show'])
            ->middleware('permission:settings.manage');

        // Gestione Ferie e Chiusure Globali
        Route::resource('holidays', HolidayAdminController::class)
            ->only(['index', 'create', 'store', 'destroy']);
        Route::delete('closures/{closure}', [HolidayAdminController::class, 'destroyClosure'])
            ->name('closures.destroy');

        // Prodotti
        Route::resource('products', ProductAdminController::class)
            ->except(['show'])
            ->middleware('permission:products.manage');

        // Ordini
        Route::get('/orders', [OrderAdminController::class, 'index'])->name('orders.index');
        Route::get('/orders/print', [OrderAdminController::class, 'print'])->name('orders.print');
        Route::post('/orders/bulk-status', [OrderAdminController::class, 'bulkStatus'])->name('orders.bulk-status');
        Route::get('/orders/{order}', [OrderAdminController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/notes', [OrderAdminController::class, 'storeNote'])->name('orders.notes.store');
        Route::patch('/orders/{order}/status', [OrderAdminController::class, 'updateStatus'])->name('orders.status');

        // Notifiche ai clienti: eventi e modelli dei messaggi
        Route::get('notifications', [NotificationAdminController::class, 'index'])
            ->middleware('permission:settings.manage')->name('notifications.index');
        Route::put('notifications', [NotificationAdminController::class, 'updateImportance'])
            ->middleware('permission:settings.manage')->name('notifications.importance');
        Route::get('notifications/texts', [NotificationAdminController::class, 'texts'])
            ->middleware('permission:settings.manage')->name('notifications.texts');
        Route::get('notifications/{event}/edit', [NotificationAdminController::class, 'edit'])
            ->middleware('permission:settings.manage')->name('notifications.edit');
        Route::put('notifications/{event}', [NotificationAdminController::class, 'update'])
            ->middleware('permission:settings.manage')->name('notifications.update');

        // Ordini annullati per blocco cliente
        Route::get('/block-notices', [BlockNoticeAdminController::class, 'index'])->name('block-notices.index');
        Route::post('/block-notices/seen-all', [BlockNoticeAdminController::class, 'seenAll'])->name('block-notices.seen-all');
        Route::post('/block-notices/{notice}/seen', [BlockNoticeAdminController::class, 'seen'])->name('block-notices.seen');

        // Modalità Banco
        Route::middleware('permission:counter.use')->group(function () {
            Route::get('/banco', [BancoController::class, 'index'])->name('banco.index');
            Route::get('/banco/search', [BancoController::class, 'search'])->name('banco.search');
            Route::post('/banco/orders/{order}/status', [BancoController::class, 'updateStatus'])->name('banco.status');
        });

        // Clienti
        Route::get('/customers', [CustomerAdminController::class, 'index'])->name('customers.index');
        Route::get('/customers/{customer}', [CustomerAdminController::class, 'show'])->name('customers.show');
        Route::patch('/customers/{customer}/delivery', [CustomerAdminController::class, 'updateDelivery'])->name('customers.delivery');
        Route::post('/customers/{customer}/verify-phone', [CustomerAdminController::class, 'verifyPhone'])->name('customers.verify-phone');
        Route::post('/customers/{customer}/unblock', [CustomerAdminController::class, 'unblock'])->name('customers.unblock');

        // Impostazioni del portale e registro attività: solo venditore
        Route::middleware('role:seller')->group(function () {
            Route::get('/settings', [SettingsAdminController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [SettingsAdminController::class, 'update'])->name('settings.update');
            Route::get('/settings/whatsapp', [WhatsAppSettingsController::class, 'edit'])->name('settings.whatsapp.edit');
            Route::put('/settings/whatsapp', [WhatsAppSettingsController::class, 'update'])->name('settings.whatsapp.update');
            Route::post('/settings/test-email', [SettingsAdminController::class, 'testEmail'])->name('settings.test-email');
            Route::get('/legal', [LegalPageAdminController::class, 'index'])->name('legal.index');
            Route::get('/legal/{page}', [LegalPageAdminController::class, 'edit'])->name('legal.edit');
            Route::put('/legal/{page}', [LegalPageAdminController::class, 'update'])->name('legal.update');
            Route::get('/activity-log', [ActivityLogAdminController::class, 'index'])->name('activity-log.index');
            Route::get('/email-log', [EmailLogAdminController::class, 'index'])->name('email-log.index');
            Route::get('/email-log/{emailLog}', [EmailLogAdminController::class, 'show'])->name('email-log.show');
            Route::get('/email-log/{emailLog}/eml', [EmailLogAdminController::class, 'download'])->name('email-log.download');
            Route::get('/email-log/{emailLog}/attachments/{attachment}', [EmailLogAdminController::class, 'attachment'])
                ->whereNumber('attachment')->name('email-log.attachment');

            // Email scritte a mano ai clienti registrati
            Route::get('/customer-emails/create', [CustomerEmailAdminController::class, 'create'])->name('customer-emails.create');
            Route::get('/customer-emails/recipients', [CustomerEmailAdminController::class, 'recipients'])->name('customer-emails.recipients');
            Route::post('/customer-emails', [CustomerEmailAdminController::class, 'store'])
                ->middleware('throttle:10,1')->name('customer-emails.store');
        });

        // Collaboratori e permessi: solo venditore
        Route::resource('collaborators', CollaboratorAdminController::class)
            ->except(['show'])
            ->middleware('role:seller');
    });

require __DIR__.'/auth.php';
