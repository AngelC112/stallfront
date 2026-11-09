<?php

namespace App\Support;

use App\Models\User;
use App\Services\AddressService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Indirizzo del cliente dai moduli di registrazione e account.
 * Un indirizzo nuovo vale solo se scelto dall'elenco dei suggerimenti di OpenStreetMap
 * (e quindi esiste); il numero civico lo completa il cliente.
 */
final class AddressInput
{
    public const FIELDS = ['street', 'street_number', 'postal_code', 'city', 'province', 'latitude', 'longitude'];

    /**
     * Colonne di users da salvare. Con il campo vuoto restituisce tutte le colonne a null.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public static function resolve(Request $request, ?User $user = null): array
    {
        $search = trim((string) $request->input('address_search'));
        $input = [];

        foreach (self::FIELDS as $field) {
            $input[$field] = trim((string) $request->input($field));
        }

        if ($search === '' && $input['street'] === '') {
            return self::empty();
        }

        // Indirizzo già salvato e non toccato: resta com'è (il civico si può correggere).
        if ($user && self::isUnchanged($user, $input, $search)) {
            return self::keep($user, $input);
        }

        $service = app(AddressService::class);

        if ($input['street'] === '' || ! $service->isSigned($input, (string) $request->input('address_token'))) {
            throw self::error(__('Scegli il tuo indirizzo dall’elenco dei suggerimenti: così controlliamo che esista.'));
        }

        $number = mb_substr($input['street_number'], 0, 20);

        if ($number === '') {
            throw self::error(__('Indica il numero civico (scrivi «snc» se non ce l’ha).'));
        }

        $address = [
            'street' => $input['street'],
            'street_number' => $number,
            'postal_code' => $input['postal_code'],
            'city' => $input['city'],
            'province' => $input['province'],
        ];

        return self::columns($address, (float) $input['latitude'], (float) $input['longitude'], $service->label($address));
    }

    /**
     * @return array<string, mixed>
     */
    private static function empty(): array
    {
        return array_fill_keys(['address', 'street', 'street_number', 'postal_code', 'city', 'province', 'latitude', 'longitude'], null);
    }

    /**
     * @param  array<string, string>  $input
     */
    private static function isUnchanged(User $user, array $input, string $search): bool
    {
        // Indirizzo scritto a mano in passato, prima dell'elenco: lo teniamo finché non lo cambia.
        if ($user->street === null) {
            return $user->address !== null && $search === $user->address && $input['street'] === '';
        }

        return $input['street'] === $user->street
            && $input['postal_code'] === (string) $user->postal_code
            && $input['city'] === $user->city
            && abs((float) $input['latitude'] - (float) $user->latitude) < 0.000001
            && abs((float) $input['longitude'] - (float) $user->longitude) < 0.000001;
    }

    /**
     * @param  array<string, string>  $input
     * @return array<string, mixed>
     */
    private static function keep(User $user, array $input): array
    {
        if ($user->street === null) {
            return ['address' => $user->address];
        }

        $number = mb_substr($input['street_number'], 0, 20);

        if ($number === '') {
            throw self::error(__('Indica il numero civico (scrivi «snc» se non ce l’ha).'));
        }

        $address = [
            'street' => $user->street,
            'street_number' => $number,
            'postal_code' => (string) $user->postal_code,
            'city' => (string) $user->city,
            'province' => (string) $user->province,
        ];

        return self::columns($address, (float) $user->latitude, (float) $user->longitude, app(AddressService::class)->label($address));
    }

    /**
     * @param  array<string, string>  $address
     * @return array<string, mixed>
     */
    private static function columns(array $address, float $latitude, float $longitude, string $label): array
    {
        return [
            'address' => $label,
            'street' => $address['street'],
            'street_number' => $address['street_number'],
            'postal_code' => $address['postal_code'] ?: null,
            'city' => $address['city'],
            'province' => $address['province'] ?: null,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private static function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['address_search' => $message]);
    }
}
