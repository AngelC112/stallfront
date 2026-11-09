<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ActivityLogger;
use App\Support\PortalSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\TemplatedMail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsAdminController extends Controller
{
    public function edit(): View
    {
        $values = [];

        foreach (PortalSettings::all() as $key => $definition) {
            $value = Setting::get($key, $definition['default']);

            $values[$key] = $definition['type'] === PortalSettings::TYPE_MONEY
                ? number_format(((int) $value) / 100, 2, '.', '')
                : $value;
        }

        return view('admin.settings.edit', [
            'groups' => PortalSettings::groups(),
            'values' => $values,
        ]);
    }

    public function update(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $rules = [];

        foreach (PortalSettings::all() as $key => $definition) {
            $field = PortalSettings::fieldName($key);
            $rules[$field] = $this->rulesFor($definition);

            // Gli importi si possono scrivere anche con la virgola.
            if ($definition['type'] === PortalSettings::TYPE_MONEY && $request->filled($field)) {
                $request->merge([$field => str_replace(',', '.', (string) $request->input($field))]);
            }
        }

        $validated = $request->validate($rules);

        $changed = [];

        foreach (PortalSettings::all() as $key => $definition) {
            $raw = $validated[PortalSettings::fieldName($key)] ?? null;
            $value = match ($definition['type']) {
                PortalSettings::TYPE_INT, PortalSettings::TYPE_CHOICE => (int) $raw,
                PortalSettings::TYPE_BOOL => $request->boolean(PortalSettings::fieldName($key)),
                PortalSettings::TYPE_MONEY => (int) round(((float) str_replace(',', '.', (string) $raw)) * 100),
                default => (string) ($raw ?? ''),
            };

            if (Setting::get($key, $definition['default']) !== $value) {
                $changed[] = $key;
            }

            Setting::set($key, $value);
        }

        if ($changed !== []) {
            $logger->log('settings.updated', null, ['keys' => $changed]);
        }

        return back()->with('success', __('Impostazioni salvate.'));
    }

    /**
     * Invia un'email di prova per verificare la configurazione SMTP.
     */
    public function testEmail(Request $request): RedirectResponse
    {
        $data = $request->validate(['test_email' => ['required', 'email', 'max:190']]);

        try {
            Mail::to($data['test_email'])->send(new TemplatedMail(
                __('Email di prova dalla vetrina'),
                __("Questa è un'email di prova inviata dal pannello di amministrazione.\nSe la stai leggendo, l'invio delle email funziona correttamente.\n\nInviata il :datetime", ['datetime' => \App\Support\Format::dateTime(now())])
            ));
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->with('test_error', __('Invio non riuscito: :error', ['error' => $e->getMessage()]));
        }

        return back()->with('test_success', __('Email di prova inviata a :email.', ['email' => $data['test_email']]));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<int, mixed>
     */
    private function rulesFor(array $definition): array
    {
        return match ($definition['type']) {
            PortalSettings::TYPE_INT => ['required', 'integer', 'min:'.$definition['min'], 'max:'.$definition['max']],
            PortalSettings::TYPE_BOOL => ['nullable', 'boolean'],
            PortalSettings::TYPE_MONEY => ['required', 'numeric', 'min:0', 'max:100000'],
            PortalSettings::TYPE_TIME => ['required', 'date_format:H:i'],
            PortalSettings::TYPE_CHOICE => ['required', Rule::in(array_keys($definition['choices']))],
            default => array_filter(['nullable', 'string', 'max:'.$definition['max'], ! empty($definition['email']) ? 'email' : null]),
        };
    }
}
