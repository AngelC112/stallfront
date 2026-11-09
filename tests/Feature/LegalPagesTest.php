<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_pages_are_public_and_use_store_data(): void
    {
        Setting::set('store.name', 'Bottega Rossi');
        Setting::set('store.vat', 'IT01234567890');
        Setting::set('store.address', 'Via Roma 1, Milano');
        Setting::set('orders.no_show_block_threshold', 3);

        $this->get(route('legal.privacy'))->assertOk()->assertSee('Bottega Rossi')->assertSee('IT01234567890')->assertSee('impronte cifrate');
        $this->get(route('legal.cookies'))->assertOk()->assertSee('cookie_consent');
        $this->get(route('legal.terms'))->assertOk()->assertSee('3 mancati ritiri')->assertSee('Diritto di recesso');
    }

    public function test_terms_state_that_home_delivery_can_be_charged(): void
    {
        Setting::set('delivery.cost_cents', 350);
        $this->get(route('legal.terms'))->assertOk()->assertSee('può essere a pagamento')->assertSee('Costo attuale per ordine: '.\App\Support\Format::money(350), false);

        Setting::set('delivery.cost_cents', 0);
        $this->get(route('legal.terms'))->assertOk()->assertSee('può essere a pagamento')->assertSee('Costo attuale per ordine: gratuita');
    }

    public function test_footer_shows_vat_and_legal_links_on_the_storefront(): void
    {
        Setting::set('store.name', 'Bottega Rossi');
        Setting::set('store.vat', 'IT01234567890');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('P.IVA IT01234567890')
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('legal.cookies'), false)
            ->assertSee(route('legal.terms'), false);
    }

    public function test_cookie_banner_offers_three_equal_choices(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        foreach (['cookie-reject', 'cookie-customize', 'cookie-accept'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html);
        }

        // Stessa classe grafica per le tre scelte: nessuna è messa in evidenza.
        preg_match_all('/<button type="button" id="cookie-(?:reject|customize|accept)" class="([^"]+)"/', $html, $matches);
        $this->assertCount(3, $matches[1]);
        $this->assertCount(1, array_unique($matches[1]));
    }

    public function test_no_non_technical_cookie_is_set_before_consent(): void
    {
        $response = $this->get(route('home'));

        $names = collect($response->headers->getCookies())->map->getName()->all();

        foreach ($names as $name) {
            $this->assertContains($name, [config('session.cookie'), 'XSRF-TOKEN']);
        }
    }

    public function test_registration_links_to_privacy_and_terms(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee(route('legal.privacy'), false)
            ->assertSee(route('legal.terms'), false);
    }
}
