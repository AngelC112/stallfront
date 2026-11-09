<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Suggerimenti di indirizzo da OpenStreetMap, tramite Photon (photon.komoot.io).
 * Il servizio è gratuito e senza chiavi; per questo le richieste passano dal nostro server,
 * si limitano all'Italia e le risposte si tengono in cache.
 *
 * Ogni suggerimento porta una firma: così il server sa che l'indirizzo salvato viene
 * davvero dall'elenco e non è stato scritto a mano.
 */
class AddressService
{
    private const ITALY_BBOX = '6.6,35.4,18.8,47.1';

    private const CACHE_MINUTES = 60 * 24;

    private const MIN_QUERY_LENGTH = 4;

    /** Elementi "highway" di OpenStreetMap che non sono vie: fermate, attraversamenti, svincoli. */
    private const NOT_ROADS = ['bus_stop', 'platform', 'crossing', 'traffic_signals', 'street_lamp', 'services', 'rest_area', 'milestone', 'give_way', 'stop', 'speed_camera', 'turning_circle', 'motorway_junction', 'elevator'];

    /**
     * @return array{available: bool, results: list<array<string, mixed>>}
     */
    public function suggest(string $query): array
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));

        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return ['available' => true, 'results' => []];
        }

        $features = Cache::remember('address-suggest:'.sha1(mb_strtolower($query)), now()->addMinutes(self::CACHE_MINUTES), fn () => $this->fetch($query));

        if ($features === null) {
            Cache::forget('address-suggest:'.sha1(mb_strtolower($query)));

            return ['available' => false, 'results' => []];
        }

        return ['available' => true, 'results' => $this->normalize($features)];
    }

    /**
     * Firma dei dati dell'indirizzo che il cliente non può scegliere a mano.
     *
     * @param  array<string, mixed>  $address
     */
    public function sign(array $address): string
    {
        return hash_hmac('sha256', $this->signedPayload($address), (string) config('app.key'));
    }

    /**
     * @param  array<string, mixed>  $address
     */
    public function isSigned(array $address, ?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->sign($address), $token);
    }

    /**
     * @return array<int, array<string, mixed>>|null null se il servizio non risponde
     */
    private function fetch(string $query): ?array
    {
        try {
            $response = Http::timeout(6)
                ->withHeaders(['User-Agent' => config('app.name').' (address lookup)'])
                ->get('https://photon.komoot.io/api/', [
                    'q' => $query,
                    'limit' => 10,
                    'bbox' => self::ITALY_BBOX,
                ]);

            if (! $response->successful()) {
                return null;
            }

            return (array) $response->json('features', []);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $features
     * @return list<array<string, mixed>>
     */
    private function normalize(array $features): array
    {
        $results = [];

        foreach ($features as $feature) {
            $properties = (array) ($feature['properties'] ?? []);
            [$longitude, $latitude] = array_pad((array) ($feature['geometry']['coordinates'] ?? []), 2, null);

            if (($properties['countrycode'] ?? null) !== 'IT' || $latitude === null || $longitude === null) {
                continue;
            }

            // Serve almeno una via (o una piazza) in un comune: niente "solo comune" né negozi.
            $isRoad = ($properties['osm_key'] ?? null) === 'highway' && ! in_array($properties['osm_value'] ?? null, self::NOT_ROADS, true);
            $street = $properties['street'] ?? ($isRoad ? ($properties['name'] ?? null) : null);
            $city = $properties['city'] ?? $properties['town'] ?? $properties['village'] ?? $properties['locality'] ?? $properties['district'] ?? null;

            if (! $street || ! $city) {
                continue;
            }

            $address = [
                'street' => (string) $street,
                'street_number' => isset($properties['housenumber']) ? (string) $properties['housenumber'] : '',
                'postal_code' => (string) ($properties['postcode'] ?? ''),
                'city' => (string) $city,
                'province' => $this->provinceName($properties),
                'latitude' => round((float) $latitude, 7),
                'longitude' => round((float) $longitude, 7),
            ];

            $key = mb_strtolower($address['street'].'|'.$address['street_number'].'|'.$address['postal_code'].'|'.$address['city']);

            if (isset($results[$key])) {
                continue;
            }

            $results[$key] = $address + [
                'label' => $this->label($address),
                'token' => $this->sign($address),
            ];
        }

        return array_values(array_slice($results, 0, 6));
    }

    /**
     * Photon dà il nome della provincia in "county" ("Provincia di Bergamo", "Città metropolitana di Milano").
     *
     * @param  array<string, mixed>  $properties
     */
    private function provinceName(array $properties): string
    {
        $county = (string) ($properties['county'] ?? '');

        return trim(preg_replace('/^(Provincia|Città metropolitana|Libero consorzio comunale)\s+(di|del|della|dell’|dell\')\s*/iu', '', $county));
    }

    /**
     * @param  array<string, mixed>  $address
     */
    public function label(array $address): string
    {
        $line = trim($address['street'].' '.($address['street_number'] ?? ''));
        $place = trim(($address['postal_code'] ?? '').' '.$address['city']);

        return $line.', '.$place.($address['province'] && $address['province'] !== $address['city'] ? ' ('.$address['province'].')' : '');
    }

    /**
     * Il numero civico non entra nella firma: il cliente lo completa o lo corregge.
     *
     * @param  array<string, mixed>  $address
     */
    private function signedPayload(array $address): string
    {
        return json_encode([
            (string) ($address['street'] ?? ''),
            (string) ($address['postal_code'] ?? ''),
            (string) ($address['city'] ?? ''),
            (string) ($address['province'] ?? ''),
            number_format((float) ($address['latitude'] ?? 0), 7, '.', ''),
            number_format((float) ($address['longitude'] ?? 0), 7, '.', ''),
        ], JSON_UNESCAPED_UNICODE);
    }
}
