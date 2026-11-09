<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CustomerMessageMail;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\ReturnUrl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Scrittura di un'email ai clienti registrati (solo indirizzi verificati), con allegati.
 * Ogni cliente riceve un'email separata: nessuno vede gli indirizzi degli altri.
 */
class CustomerEmailAdminController extends Controller
{
    public const MAX_RECIPIENTS = 50;

    public const MAX_ATTACHMENTS = 10;

    /** Dimensione massima di ogni allegato, in KB. */
    public const MAX_ATTACHMENT_KB = 10240;

    /** Dimensione massima complessiva degli allegati, in byte. */
    public const MAX_TOTAL_ATTACHMENTS_BYTES = 20 * 1024 * 1024;

    public function create(Request $request): View
    {
        $selectedIds = array_map('intval', (array) $request->old('recipients', $request->query('to', [])));

        $selected = $selectedIds === []
            ? collect()
            : $this->recipientsQuery()->whereKey($selectedIds)->get();

        return view('admin.customer-emails.create', [
            'backUrl' => ReturnUrl::from($request->old(ReturnUrl::PARAMETER, $request->query(ReturnUrl::PARAMETER))),
            'selected' => $selected->map(fn (User $customer): array => $this->recipientPayload($customer))->values(),
            'maxRecipients' => self::MAX_RECIPIENTS,
            'maxAttachments' => self::MAX_ATTACHMENTS,
        ]);
    }

    public function recipients(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $customers = $this->recipientsQuery()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name', 'LIKE', "%{$term}%")
                ->orWhere('first_name', 'LIKE', "%{$term}%")
                ->orWhere('last_name', 'LIKE', "%{$term}%")
                ->orWhere('email', 'LIKE', "%{$term}%")))
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json($customers->map(fn (User $customer): array => $this->recipientPayload($customer))->values());
    }

    public function store(Request $request, ActivityLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'recipients' => ['required', 'array', 'min:1', 'max:'.self::MAX_RECIPIENTS],
            'recipients.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')
                    ->where('role', User::ROLE_CUSTOMER)
                    ->whereNotNull('email_verified_at'),
            ],
            'subject' => ['required', 'string', 'max:190'],
            'body' => ['required', 'string', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:'.self::MAX_ATTACHMENTS],
            'attachments.*' => ['file', 'max:'.self::MAX_ATTACHMENT_KB],
        ], [
            'recipients.required' => __('Scegli almeno un cliente.'),
            'recipients.*.exists' => __('Puoi scrivere solo a clienti registrati con email verificata.'),
        ], [
            'recipients' => 'destinatari',
            'subject' => 'oggetto',
            'body' => 'testo',
            'attachments' => 'allegati',
            'attachments.*' => 'allegato',
        ]);

        /** @var list<UploadedFile> $uploads */
        $uploads = $request->file('attachments', []);
        $totalBytes = array_sum(array_map(fn (UploadedFile $file): int => (int) $file->getSize(), $uploads));

        if ($totalBytes > self::MAX_TOTAL_ATTACHMENTS_BYTES) {
            return back()->withInput()->withErrors(['attachments' => __('Gli allegati superano in totale i 20 MB.')]);
        }

        $files = array_map(fn (UploadedFile $file): array => [
            'path' => (string) $file->getRealPath(),
            'name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType() ?: null,
        ], $uploads);

        // Ogni invio passa dalla stessa connessione SMTP, ma con molti destinatari serve più tempo del solito.
        set_time_limit(300);

        $sent = [];
        $failed = [];

        foreach ($this->recipientsQuery()->whereKey($data['recipients'])->get() as $customer) {
            try {
                Mail::to($customer->email, $customer->fullName())
                    ->send(new CustomerMessageMail($data['subject'], $data['body'], $files));
                $sent[] = $customer->email;
            } catch (\Throwable $e) {
                report($e);
                $failed[] = $customer->email;
            }
        }

        $logger->log('customer_email.sent', null, [
            'subject' => $data['subject'],
            'sent' => count($sent),
            'failed' => $failed,
            'attachments' => array_column($files, 'name'),
        ]);

        if ($failed !== [] && $sent === []) {
            return back()->withInput()->withErrors(['recipients' => __('Invio non riuscito a: :emails.', ['emails' => implode(', ', $failed)])]);
        }

        $message = trans_choice('Email inviata a :count cliente.|Email inviata a :count clienti.', count($sent));

        if ($failed !== []) {
            $message .= ' '.__('Non inviata a: :emails.', ['emails' => implode(', ', $failed)]);
        }

        $backUrl = ReturnUrl::from($request->input(ReturnUrl::PARAMETER));

        return ($backUrl ? redirect()->to($backUrl) : redirect()->route('admin.email-log.index'))->with('success', $message);
    }

    /**
     * @return Builder<User>
     */
    private function recipientsQuery(): Builder
    {
        return User::query()
            ->where('role', User::ROLE_CUSTOMER)
            ->whereNotNull('email_verified_at');
    }

    /**
     * @return array{id: int, name: string, email: string}
     */
    private function recipientPayload(User $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->fullName(),
            'email' => $customer->email,
        ];
    }
}
