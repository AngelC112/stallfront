<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\Order;
use App\Services\OrderBoardService;
use App\Services\OrderStatusService;
use App\Support\Permission;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OrderAdminController extends Controller
{
    public function __construct(
        private OrderStatusService $statusService,
        private OrderBoardService $board,
    ) {}

    public function index(Request $request): View
    {
        $this->authorizeBoard($request);

        $viewMode = $this->viewMode($request);
        $board = $this->board->build($request->user(), $this->filters($request), $viewMode);

        return view('admin.orders.index', [
            'viewMode' => $viewMode,
            'groups' => $board['groups'],
            'futureOffSeason' => $board['futureOffSeason'],
            'markets' => Market::query()->orderBy('name')->get(),
            'nextSteps' => $this->nextSteps($request, $board['groups']),
            'canSeeNotes' => $request->user()->hasPermission(Permission::ORDERS_NOTES_MANAGE),
        ]);
    }

    /**
     * Versione stampabile (A4) della vista corrente, da salvare anche in PDF dal browser.
     */
    public function print(Request $request): View
    {
        $this->authorizeBoard($request);

        $viewMode = $this->viewMode($request);
        $board = $this->board->build($request->user(), $this->filters($request), $viewMode);

        return view('admin.orders.print', [
            'viewMode' => $viewMode,
            'groups' => $board['groups'],
            'futureOffSeason' => $board['futureOffSeason'],
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        $this->authorizeFulfillment($request, $order);
        $order->load(['items', 'user', 'statusHistories.user', 'notes.author']);

        $data = [
            'order' => $order,
            'statusOptions' => $this->statusService->staffOptions($request->user(), $order),
            'needsNewDate' => $this->needsNewDate($order),
            'markets' => Market::query()->active()->orderBy('name')->get(),
            'canSeeNotes' => $request->user()->hasPermission(Permission::ORDERS_NOTES_MANAGE),
        ];

        // Il pannello affiancato dell’elenco carica solo il dettaglio, senza la pagina intorno.
        return $request->boolean('partial')
            ? view('admin.orders._detail', $data + ['inPanel' => true])
            : view('admin.orders.show', $data);
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeFulfillment($request, $order);

        $options = $this->statusService->staffOptions($request->user(), $order);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys($options))],
            'market_id' => ['nullable', 'integer', Rule::exists('markets', 'id')->whereNull('deleted_at')->where('is_active', true)],
            'pickup_date' => ['nullable', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = [];

        if ($validated['status'] === Order::STATUS_READY && $this->needsNewDate($order)) {
            $attributes = $this->newPickupAttributes($validated);
        }

        $this->statusService->transition(
            $order,
            $validated['status'],
            OrderStatusService::ACTOR_STAFF,
            $request->user(),
            $validated['reason'] ?? null,
            $attributes,
        );

        return back()->with('success', __('Stato ordine aggiornato.'));
    }

    /**
     * Cambia lo stato di più ordini insieme: ogni ordine segue le proprie regole e genera la propria notifica.
     */
    public function bulkStatus(Request $request): RedirectResponse
    {
        $this->authorizeBoard($request);

        $validated = $request->validate([
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer'],
            'status' => ['required', 'string'],
        ], [
            'order_ids.required' => __('Seleziona almeno un ordine.'),
        ]);

        $updated = 0;
        $skipped = 0;

        foreach (Order::query()->whereIn('id', $validated['order_ids'])->get() as $order) {
            $permission = $order->fulfillment_method === Order::FULFILLMENT_DELIVERY
                ? Permission::ORDERS_DELIVERY_MANAGE
                : Permission::ORDERS_MARKET_MANAGE;

            $options = $this->statusService->staffOptions($request->user(), $order);
            $needsDate = $validated['status'] === Order::STATUS_READY && $this->needsNewDate($order);

            if (! $request->user()->hasPermission($permission) || ! isset($options[$validated['status']]) || $needsDate) {
                $skipped++;

                continue;
            }

            $this->statusService->transition($order, $validated['status'], OrderStatusService::ACTOR_STAFF, $request->user());
            $updated++;
        }

        $message = trans_choice(':count ordine aggiornato.|:count ordini aggiornati.', $updated).($skipped ? ' '.trans_choice(':count saltato: cambio di stato non consentito.|:count saltati: cambio di stato non consentito.', $skipped) : '');

        return back()->with('success', $message);
    }

    public function storeNote(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeFulfillment($request, $order);
        abort_unless($request->user()->hasPermission(Permission::ORDERS_NOTES_MANAGE), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:1000'],
        ], [
            'body.required' => __('Scrivi il testo della nota.'),
        ]);

        $order->notes()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', __('Nota aggiunta.'));
    }

    private function authorizeBoard(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user->hasPermission(Permission::ORDERS_MARKET_MANAGE) || $user->hasPermission(Permission::ORDERS_DELIVERY_MANAGE),
            403,
        );
    }

    private function viewMode(Request $request): string
    {
        return $request->query('view') === OrderBoardService::VIEW_PRODUCT
            ? OrderBoardService::VIEW_PRODUCT
            : OrderBoardService::VIEW_MARKET;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return [
            'market_id' => $request->query('market_id'),
            'fulfillment' => $request->query('fulfillment'),
            'off_season' => $request->boolean('off_season'),
            'status' => $request->query('status'),
        ];
    }

    /**
     * Pulsante "passo successivo" per ogni ordine mostrato.
     *
     * @param  Collection<int, array<string, mixed>>  $groups
     * @return array<int, array{0: string, 1: string}>
     */
    private function nextSteps(Request $request, $groups): array
    {
        $steps = [];

        foreach ($groups as $group) {
            foreach ($group['orders'] as $order) {
                $step = $this->statusService->nextStep($request->user(), $order);

                if ($step) {
                    $steps[$order->id] = $step;
                }
            }
        }

        return $steps;
    }

    /**
     * Un ordine non ritirato torna in Evaso solo con un nuovo mercato e una nuova data.
     */
    private function needsNewDate(Order $order): bool
    {
        return $order->status === Order::STATUS_NOT_COLLECTED;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function newPickupAttributes(array $validated): array
    {
        $market = Market::query()->active()->find($validated['market_id'] ?? null);

        if (! $market || empty($validated['pickup_date'])) {
            throw ValidationException::withMessages([
                'market_id' => __('Scegli il nuovo mercato e la nuova data di ritiro.'),
            ]);
        }

        if (Carbon::parse($validated['pickup_date'])->dayOfWeekIso !== $market->weekday) {
            throw ValidationException::withMessages([
                'pickup_date' => __('La data non cade nel giorno di questo mercato (:weekday).', ['weekday' => $market->weekdayLabel()]),
            ]);
        }

        return [
            'market_id' => $market->id,
            'market_name' => $market->name,
            'market_location' => $market->location,
            'pickup_date' => $validated['pickup_date'],
        ];
    }

    private function authorizeFulfillment(Request $request, Order $order): void
    {
        $permission = $order->fulfillment_method === Order::FULFILLMENT_DELIVERY
            ? Permission::ORDERS_DELIVERY_MANAGE
            : Permission::ORDERS_MARKET_MANAGE;

        abort_unless($request->user()->hasPermission($permission), 403);
    }
}
