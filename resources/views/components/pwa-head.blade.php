{{-- Manifest dell'app (installazione su iPhone/Android) e configurazione delle notifiche del browser. --}}
<link rel="manifest" href="{{ route('manifest') }}">
<meta name="theme-color" content="#125e5a">
<x-favicon />
@auth
    <meta name="push-config" content="{{ json_encode([
        'loggedIn' => true,
        'vapidKey' => (string) config('services.webpush.public_key'),
        'storeUrl' => route('account.push.store'),
        'revokedUrl' => route('account.push.revoked'),
        'csrf' => csrf_token(),
    ]) }}">
@endauth
