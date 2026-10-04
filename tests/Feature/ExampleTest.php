<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_shows_the_landing_page_with_admin_access_and_the_install_qr_code(): void
    {
        config(['logimaster.mobile_url' => 'https://logimaster.exemple.ci/m']);

        $page = $this->get('/')->assertOk();
        $page->assertSee('Du chronogramme aux livraisons', false)->assertSee('en temps réel', false);
        $page->assertSee('href="/admin"', false)->assertSee("Accéder à l'administration", false);   // accès à l'espace d'administration
        $page->assertSee('<svg', false)->assertSee('https://logimaster.exemple.ci/m');                // vrai QR code vers l'application
        $page->assertSee('href="/m"', false)->assertSee('/m/installer', false);
    }

    public function test_admin_still_redirects_unauthenticated_visitors_to_the_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }
}
