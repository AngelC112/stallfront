<?php

namespace App\Http\Controllers;

use App\Models\Market;
use App\Services\MarketClosureService;
use Illuminate\View\View;

/**
 * Pagina pubblica «Dove siamo»: giorno, luogo, orari e prossime date con stato aperto o chiuso.
 */
class WhereWeAreController extends Controller
{
    private const UPCOMING_DATES = 4;

    private const LOOKAHEAD_DAYS = 90;

    public function __invoke(MarketClosureService $closures): View
    {
        $from = now()->startOfDay();
        $closedReason = $closures->reasonLookup($from, $from->copy()->addDays(self::LOOKAHEAD_DAYS));

        $markets = Market::query()->active()->orderBy('weekday')->orderBy('name')->get()->map(function (Market $market) use ($from, $closedReason): array {
            $dates = [];

            for ($offset = 0; $offset <= self::LOOKAHEAD_DAYS && count($dates) < self::UPCOMING_DATES; $offset++) {
                $day = $from->copy()->addDays($offset);

                if ($day->dayOfWeekIso !== $market->weekday) {
                    continue;
                }

                $dates[] = ['date' => $day, 'closed_reason' => $closedReason($market->id, $day->toDateString())];
            }

            return [
                'market' => $market,
                'dates' => $dates,
                'directions_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($market->location),
            ];
        });

        return view('storefront.where-we-are', ['markets' => $markets]);
    }
}
