<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Models\MarketClosure;
use App\Models\MarketHoliday;
use App\Services\ActivityLogger;
use App\Services\MarketClosureService;
use App\Support\Permission;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayAdminController extends Controller
{
    public const TYPE_HOLIDAY = 'holiday';

    public const TYPE_CLOSURE = 'closure';

    public function index(Request $request): View
    {
        abort_unless($this->allowedTypes($request) !== [], 403);

        return view('admin.holidays.index', [
            'holidays' => MarketHoliday::query()
                ->with('market')
                ->whereDate('end_date', '>=', today())
                ->orderBy('start_date')
                ->get(),
            'closures' => MarketClosure::query()
                ->with('market')
                ->whereDate('closure_date', '>=', today())
                ->orderBy('closure_date')
                ->get(),
            'allowedTypes' => $this->allowedTypes($request),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($this->allowedTypes($request) !== [], 403);

        return view('admin.holidays.create', [
            'markets' => Market::query()->orderBy('name')->get(),
            'allowedTypes' => $this->allowedTypes($request),
        ]);
    }

    /**
     * Primo invio: mostra il riepilogo. Secondo invio (confirmed=1): salva e avvisa i clienti.
     */
    public function store(Request $request, MarketClosureService $closures, ActivityLogger $logger): View|RedirectResponse
    {
        $type = $request->input('type');
        abort_unless(in_array($type, $this->allowedTypes($request), true), 403);

        $validated = $request->validate([
            'type' => ['required', 'in:'.self::TYPE_HOLIDAY.','.self::TYPE_CLOSURE],
            'market_id' => [$type === self::TYPE_CLOSURE ? 'required' : 'nullable', 'integer', 'exists:markets,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => [$type === self::TYPE_HOLIDAY ? 'required' : 'nullable', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'market_id.required' => __('Scegli il mercato da chiudere.'),
            'start_date.after_or_equal' => __('La data non può essere nel passato.'),
            'end_date.after_or_equal' => __('La data di fine non può precedere quella di inizio.'),
            'reason.required' => __('Scrivi la motivazione: verrà inviata ai clienti.'),
        ]);

        $market = ! empty($validated['market_id']) ? Market::find($validated['market_id']) : null;
        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = $type === self::TYPE_CLOSURE ? $start->copy() : Carbon::parse($validated['end_date'])->startOfDay();

        if (! $request->boolean('confirmed')) {
            return view('admin.holidays.confirm', [
                'input' => $validated,
                'type' => $type,
                'market' => $market,
                'start' => $start,
                'end' => $end,
                'summary' => $closures->summary($market?->id, $start, $end),
            ]);
        }

        $result = $type === self::TYPE_CLOSURE
            ? $closures->registerClosure($market, $start, $validated['reason'])
            : $closures->registerHoliday($market, $start, $end, $validated['reason']);

        $logger->log($type === self::TYPE_CLOSURE ? 'market.closure_created' : 'market.holiday_created', $market, [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
            'reason' => $validated['reason'],
            'orders' => $result['orders'],
        ]);

        return redirect()
            ->route('admin.holidays.index')
            ->with('success', "Salvato. {$result['orders']} ordini passati in «Da riprogrammare», {$result['customers']} clienti avvisati.");
    }

    public function destroy(Request $request, MarketHoliday $holiday): RedirectResponse
    {
        abort_unless($request->user()->hasPermission(Permission::CLOSURES_HOLIDAYS_MANAGE), 403);

        $holiday->delete();

        return redirect()->route('admin.holidays.index')->with('success', __('Periodo di ferie eliminato.'));
    }

    public function destroyClosure(Request $request, MarketClosure $closure): RedirectResponse
    {
        abort_unless($request->user()->hasPermission(Permission::CLOSURES_EXCEPTION_MANAGE), 403);

        $closure->delete();

        return redirect()->route('admin.holidays.index')->with('success', __('Chiusura straordinaria eliminata.'));
    }

    /**
     * @return array<int, string>
     */
    private function allowedTypes(Request $request): array
    {
        $user = $request->user();
        $types = [];

        if ($user->hasPermission(Permission::CLOSURES_HOLIDAYS_MANAGE)) {
            $types[] = self::TYPE_HOLIDAY;
        }

        if ($user->hasPermission(Permission::CLOSURES_EXCEPTION_MANAGE)) {
            $types[] = self::TYPE_CLOSURE;
        }

        return $types;
    }
}
