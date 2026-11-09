<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TemplatedMail;
use App\Models\Setting;
use App\Models\User;
use App\Rules\StrongPassword;
use App\Services\ActivityLogger;
use App\Support\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Gestione dei collaboratori e dei loro permessi: riservata al venditore.
 */
class CollaboratorAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.collaborators.index', [
            'collaborators' => User::query()->where('role', User::ROLE_COLLABORATOR)->withCount('permissions')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.collaborators.form', [
            'collaborator' => new User,
            'permissions' => Permission::all(),
            'granted' => [],
        ]);
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $existing = User::query()->where('email', $request->input('email'))->first();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255'],
            // Per un cliente già registrato la password resta la sua: quella iniziale serve solo ai nuovi account.
            'password' => [$existing ? 'nullable' : 'required', 'string', new StrongPassword],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(array_keys(Permission::all()))],
        ]);

        if ($existing) {
            return $this->promoteExisting($request, $existing, $validated, $logger);
        }

        $collaborator = new User([
            'role' => User::ROLE_COLLABORATOR,
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        // Il venditore garantisce per il collaboratore: email già confermata.
        $collaborator->forceFill(['email_verified_at' => now()])->save();

        $this->syncPermissions($collaborator, $validated['permissions'] ?? []);
        $logger->log('collaborator.created', $collaborator, ['permissions' => $validated['permissions'] ?? []]);

        return redirect()->route('admin.collaborators.index')->with('success', __('Collaboratore creato.'));
    }

    /**
     * L'email appartiene già a un account: se è un cliente, dopo la conferma del venditore
     * diventa collaboratore con i permessi scelti e mantiene password, dati e ordini.
     *
     * @param  array<string, mixed>  $validated
     */
    private function promoteExisting(Request $request, User $existing, array $validated, ActivityLogger $logger): RedirectResponse
    {
        $refuse = fn (string $message): RedirectResponse => back()->withInput($request->except('password'))->withErrors(['email' => $message]);

        if ($existing->isSeller()) {
            return $refuse(__('Questa email è quella del venditore.'));
        }

        if ($existing->isCollaborator()) {
            return $refuse(__('Questa persona è già un collaboratore: modifica i suoi permessi dall’elenco.'));
        }

        if ($existing->isBlocked()) {
            return $refuse(__('Questa email appartiene a un cliente bloccato: sbloccalo prima di renderlo collaboratore.'));
        }

        // Dare accesso al pannello è un passo importante: si chiede conferma esplicita.
        if (! $request->boolean('confirm_existing')) {
            return back()->withInput($request->except('password'))->with('existing_customer', [
                'name' => $existing->fullName(),
                'email' => $existing->email,
            ]);
        }

        $existing->forceFill([
            'role' => User::ROLE_COLLABORATOR,
            'email_verified_at' => $existing->email_verified_at ?? now(),
        ])->save();

        $this->syncPermissions($existing, $validated['permissions'] ?? []);
        $logger->log('collaborator.promoted', $existing, ['permissions' => $validated['permissions'] ?? []]);

        try {
            \App\Support\Localized::run($existing->preferredLocale(), fn () => Mail::to($existing->email)->locale($existing->preferredLocale())->send(new TemplatedMail(
                __('Ora sei un collaboratore di :store', ['store' => Setting::get('store.name', config('app.name'))]),
                __("Ciao :name,\nil venditore ti ha aggiunto come collaboratore. Accedi con la tua solita password e trovi il pannello nel menu.\n\n:url", ['name' => $existing->fullName(), 'url' => route('login')]),
            )));
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->route('admin.collaborators.index')->with('success', __(':name ora è un collaboratore.', ['name' => $existing->fullName()]));
    }

    public function edit(User $collaborator): View
    {
        abort_unless($collaborator->isCollaborator(), 404);

        return view('admin.collaborators.form', [
            'collaborator' => $collaborator,
            'permissions' => Permission::all(),
            'granted' => $collaborator->permissions()->pluck('permission')->all(),
        ]);
    }

    public function update(Request $request, User $collaborator, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($collaborator->isCollaborator(), 404);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'string', new StrongPassword],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(array_keys(Permission::all()))],
        ]);

        $collaborator->forceFill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
        ]);

        if (! empty($validated['password'])) {
            $collaborator->password = $validated['password'];
        }

        $collaborator->save();

        $this->syncPermissions($collaborator, $validated['permissions'] ?? []);
        $logger->log('collaborator.permissions_changed', $collaborator, ['permissions' => $validated['permissions'] ?? []]);

        return redirect()->route('admin.collaborators.index')->with('success', __('Collaboratore aggiornato.'));
    }

    public function destroy(User $collaborator, ActivityLogger $logger): RedirectResponse
    {
        abort_unless($collaborator->isCollaborator(), 404);

        $logger->log('collaborator.removed', $collaborator, ['email' => $collaborator->email]);
        $collaborator->permissions()->delete();
        $collaborator->delete();

        return redirect()->route('admin.collaborators.index')->with('success', __('Collaboratore rimosso.'));
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function syncPermissions(User $collaborator, array $permissions): void
    {
        $collaborator->permissions()->whereNotIn('permission', $permissions)->delete();

        foreach ($permissions as $permission) {
            $collaborator->permissions()->firstOrCreate(['permission' => $permission]);
        }
    }
}
