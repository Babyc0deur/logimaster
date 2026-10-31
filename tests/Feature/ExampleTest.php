<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_root_shows_the_landing_page_with_admin_access_and_no_mobile_app(): void
    {
        $page = $this->get('/')->assertOk();
        $page->assertSee('Du chronogramme aux livraisons', false);
        $page->assertSee('href="/admin"', false)->assertSee("Accéder à l'administration", false);   // accès à l'espace d'administration

        // plus d'application mobile : ni lien, ni QR code d'installation, ni section « terrain »
        $page->assertDontSee('href="/m"', false)->assertDontSee('/m/installer', false)->assertDontSee('qrcard', false)
            ->assertDontSee('Application mobile')->assertDontSee('Application terrain')->assertDontSee('Suivi en temps réel');
        foreach (['/m', '/m/installer', '/m/manifest.webmanifest', '/m/sw.js', '/api/mobile/login'] as $gone) {
            $this->get($gone)->assertNotFound();
        }
    }

    public function test_admin_still_redirects_unauthenticated_visitors_to_the_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }
}
