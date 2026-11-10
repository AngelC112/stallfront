<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AddressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AddressAutocompleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_suggestions_come_from_openstreetmap_and_are_limited_to_real_italian_streets(): void
    {
        $this->fakePhoton();

        $response = $this->getJson(route('address.suggest', ['q' => 'via schiavetti']))->assertOk()->assertJsonPath('available', true);

        $results = $response->json('results');
        $this->assertCount(2, $results);
        $this->assertSame('Via Roma 12, 20121 Milano', $results[0]['label']);
        $this->assertSame('Via Roma, 10100 Torino', $results[1]['label']);
        $this->assertSame('Milano', $results[0]['province']);
        $this->assertNotEmpty($results[0]['token']);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'photon.komoot.io') && $request['q'] === 'via schiavetti');
    }

    public function test_register_and_account_forms_show_the_address_field(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('name="address_search"', false)->assertSee('addressField(', false);

        $user = User::factory()->create(['address' => 'Via Roma 1, 10100 Torino', 'street' => 'Via Roma', 'street_number' => '1', 'city' => 'Torino']);
        $this->actingAs($user)->get(route('account.edit'))->assertOk()->assertSee('Via Roma 1, 10100 Torino');
    }

    public function test_short_queries_do_not_call_the_service_and_an_outage_is_reported(): void
    {
        Http::fake(['photon.komoot.io/*' => Http::response('errore', 503)]);

        $this->getJson(route('address.suggest', ['q' => 'vi']))->assertOk()->assertJsonPath('results', []);
        Http::assertNothingSent();

        $this->getJson(route('address.suggest', ['q' => 'via roma']))->assertOk()->assertJsonPath('available', false);
    }

    public function test_registration_saves_the_chosen_address_with_coordinates(): void
    {
        $address = $this->pickedAddress();

        $this->post(route('register'), $this->registration($address))->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'anna@example.com')->firstOrFail();
        $this->assertSame('Via Roma 12, 20121 Milano', $user->address);
        $this->assertSame('Via Roma', $user->street);
        $this->assertSame('12', $user->street_number);
        $this->assertSame('20121', $user->postal_code);
        $this->assertSame('Milano', $user->city);
        $this->assertEqualsWithDelta(41.1297, (float) $user->latitude, 0.0001);
    }

    public function test_an_address_typed_by_hand_or_tampered_with_is_rejected(): void
    {
        $address = $this->pickedAddress();

        $this->post(route('register'), $this->registration(['address_search' => 'Via Inventata 99, Roma']))
            ->assertSessionHasErrors('address_search');

        // The picked address is in Milano: another city no longer matches the signature.
        $this->post(route('register'), $this->registration(['city' => 'Napoli'] + $address))
            ->assertSessionHasErrors('address_search');
        $this->assertDatabaseMissing('users', ['email' => 'anna@example.com']);
    }

    public function test_street_number_is_required_after_choosing_an_address(): void
    {
        $this->post(route('register'), $this->registration(['street_number' => ''] + $this->pickedAddress()))
            ->assertSessionHasErrors(['address_search' => 'Indica il numero civico (scrivi «snc» se non ce l’ha).']);
    }

    public function test_address_is_optional_and_can_be_cleared_from_the_account(): void
    {
        $this->post(route('register'), $this->registration([]))->assertRedirect(route('verification.notice'));
        $user = User::where('email', 'anna@example.com')->firstOrFail();
        $this->assertNull($user->address);

        $user->forceFill(['email_verified_at' => now(), 'address' => 'Via Roma 1, Torino', 'street' => 'Via Roma', 'street_number' => '1', 'city' => 'Torino', 'latitude' => 45.07, 'longitude' => 7.68])->save();

        $this->actingAs($user)->patch(route('account.update'), $this->account($user, ['address_search' => '', 'street' => '', 'current_password' => 'Password1!']))->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->address);
        $this->assertNull($user->fresh()->street);
    }

    public function test_saved_address_is_kept_when_untouched_and_only_the_number_can_change(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Anna', 'last_name' => 'Bianchi',
            'address' => 'Via Roma 1, 10100 Torino', 'street' => 'Via Roma', 'street_number' => '1',
            'postal_code' => '10100', 'city' => 'Torino', 'province' => 'Torino', 'latitude' => 45.07, 'longitude' => 7.68,
        ]);

        $this->actingAs($user)->patch(route('account.update'), $this->account($user, ['street_number' => '5']))->assertSessionHasNoErrors();

        $this->assertSame('Via Roma 5, 10100 Torino', $user->fresh()->address);
        $this->assertSame('5', $user->fresh()->street_number);
    }

    public function test_old_free_text_address_is_kept_until_the_customer_changes_it(): void
    {
        $user = User::factory()->create(['first_name' => 'Anna', 'last_name' => 'Bianchi', 'address' => 'Via Vecchia 3, Napoli']);

        $this->actingAs($user)->patch(route('account.update'), $this->account($user, ['address_search' => 'Via Vecchia 3, Napoli', 'street' => '']))
            ->assertSessionHasNoErrors();
        $this->assertSame('Via Vecchia 3, Napoli', $user->fresh()->address);

        $this->actingAs($user)->patch(route('account.update'), $this->account($user, ['address_search' => 'Via Nuova 9, Napoli', 'street' => '']))
            ->assertSessionHasErrors('address_search');
        $this->assertSame('Via Vecchia 3, Napoli', $user->fresh()->address);
    }

    /**
     * @return array<string, mixed>
     */
    private function pickedAddress(): array
    {
        $this->fakePhoton();
        $item = $this->getJson(route('address.suggest', ['q' => 'via schiavetti']))->json('results.0');

        return [
            'address_search' => $item['label'], 'street' => $item['street'], 'street_number' => '12', 'postal_code' => $item['postal_code'],
            'city' => $item['city'], 'province' => $item['province'], 'latitude' => $item['latitude'], 'longitude' => $item['longitude'],
            'address_token' => $item['token'],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function registration(array $extra): array
    {
        return $extra + [
            'first_name' => 'Anna', 'last_name' => 'Bianchi', 'email' => 'anna@example.com',
            'password' => 'Password1!', 'password_confirmation' => 'Password1!', 'consent' => '1', 'locale' => 'it', 'form_started_at' => time() - 10,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function account(User $user, array $extra): array
    {
        return $extra + [
            'current_password' => 'password', 'locale' => 'it', 'first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email,
            'address_search' => $user->address, 'street' => $user->street, 'street_number' => $user->street_number, 'postal_code' => $user->postal_code,
            'city' => $user->city, 'province' => $user->province, 'latitude' => $user->latitude, 'longitude' => $user->longitude,
        ];
    }

    private function fakePhoton(): void
    {
        Http::fake(['photon.komoot.io/*' => Http::response(['features' => [
            [
                'geometry' => ['coordinates' => [14.7810, 41.1297]],
                'properties' => ['countrycode' => 'IT', 'street' => 'Via Roma', 'housenumber' => '12', 'postcode' => '20121', 'city' => 'Milano', 'county' => 'Città metropolitana di Milano', 'osm_key' => 'place'],
            ],
            // Stesso indirizzo ripetuto, negozio senza via e indirizzo estero: scartati.
            [
                'geometry' => ['coordinates' => [14.7810, 41.1297]],
                'properties' => ['countrycode' => 'IT', 'street' => 'Via Roma', 'housenumber' => '12', 'postcode' => '20121', 'city' => 'Milano', 'county' => 'Milano'],
            ],
            ['geometry' => ['coordinates' => [14.7, 41.1]], 'properties' => ['countrycode' => 'IT', 'name' => 'Bar Sport', 'city' => 'Milano']],
            ['geometry' => ['coordinates' => [14.7, 41.1]], 'properties' => ['countrycode' => 'IT', 'osm_key' => 'highway', 'osm_value' => 'bus_stop', 'name' => 'Fermata Centro', 'city' => 'Milano']],
            // Risultato come lo dà davvero OpenStreetMap per una via: il nome è nella chiave "name".
            ['geometry' => ['coordinates' => [7.68, 45.07]], 'properties' => ['countrycode' => 'IT', 'osm_key' => 'highway', 'osm_value' => 'residential', 'name' => 'Via Roma', 'postcode' => '10100', 'city' => 'Torino', 'county' => 'Torino']],
            ['geometry' => ['coordinates' => [2.3, 48.8]], 'properties' => ['countrycode' => 'FR', 'street' => 'Rue de Rivoli', 'city' => 'Paris']],
        ]])]);

        $this->assertInstanceOf(AddressService::class, app(AddressService::class));
    }
}
