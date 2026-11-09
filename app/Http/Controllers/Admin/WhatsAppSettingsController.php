<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Support\WhatsAppConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Chiavi della WhatsApp Business Platform inseribili dal pannello del venditore.
 * Token e App Secret sono salvati cifrati e non vengono mai mostrati di nuovo.
 */
class WhatsAppSettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.whatsapp', [
            'values' => [
                'business_number' => WhatsAppConfig::stored('business_number'),
                'phone_number_id' => WhatsAppConfig::stored('phone_number_id'),
                'verify_token' => WhatsAppConfig::stored('verify_token'),
            ],
            'hasToken' => WhatsAppConfig::stored('token') !== '',
            'hasAppSecret' => WhatsAppConfig::stored('app_secret') !== '',
            'active' => filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id')),
            'webhookUrl' => route('webhooks.whatsapp.receive'),
            'fromEnv' => filled(env('WHATSAPP_TOKEN')),
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $request->merge(['business_number' => preg_replace('/\D+/', '', (string) $request->input('business_number'))]);

        $data = $request->validate([
            'business_number' => ['nullable', 'string', 'digits_between:8,15'],
            'phone_number_id' => ['nullable', 'string', 'regex:/^\d{5,30}$/'],
            'verify_token' => ['nullable', 'string', 'max:100'],
            'token' => ['nullable', 'string', 'max:1000'],
            'app_secret' => ['nullable', 'string', 'max:200'],
        ], [
            'business_number.digits_between' => __('Il numero deve avere da 8 a 15 cifre, con prefisso internazionale (es. 39333...).'),
            'phone_number_id.regex' => __('Il Phone Number ID contiene solo cifre.'),
        ]);

        $changed = [];

        foreach (['business_number', 'phone_number_id', 'verify_token'] as $field) {
            $new = trim((string) ($data[$field] ?? ''));
            if ($new !== WhatsAppConfig::stored($field)) {
                WhatsAppConfig::store($field, $new);
                $changed[] = $field;
            }
        }

        foreach (['token', 'app_secret'] as $field) {
            $new = trim((string) ($data[$field] ?? ''));

            if ($request->boolean('clear_'.$field)) {
                WhatsAppConfig::store($field, '');
                $changed[] = $field;
            } elseif ($new !== '') {
                WhatsAppConfig::store($field, $new);
                $changed[] = $field;
            }
        }

        if ($changed !== []) {
            // Nel registro finiscono solo i nomi dei campi, mai i valori.
            $logger->log('settings.whatsapp_updated', null, ['keys' => $changed]);
        }

        return back()->with('success', $changed === [] ? __('Nessuna modifica.') : __('Impostazioni WhatsApp salvate.'));
    }
}
