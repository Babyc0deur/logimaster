<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Mode tunnel : l'adresse publique n'expose que l'accueil, l'application mobile et son API. */
class TunnelModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_tunnel_mode_nothing_is_restricted(): void
    {
        $this->get('http://abc.lhr.life/')->assertOk()->assertSee('href="/admin"', false);
        $this->get('http://abc.lhr.life/admin/login')->assertOk();
    }

    public function test_public_address_only_reaches_landing_mobile_app_and_mobile_api(): void
    {
        config(['logimaster.tunnel_mode' => true]);
        $host = 'http://abc.lhr.life';

        $this->get("$host/")->assertOk()->assertDontSee('href="/admin"', false)->assertSee('href="/m"', false);
        $this->get("$host/m")->assertOk();
        $this->get("$host/m/manifest.webmanifest")->assertOk();
        $this->get("$host/m/sw.js")->assertOk();
        $this->get("$host/m/installer")->assertOk();
        $this->postJson("$host/api/mobile/login", ['identifiant' => 'x', 'password' => 'y'])->assertStatus(422);   // l'API mobile répond

        // l'administration, Livewire et le reste de l'API sont invisibles de l'extérieur
        foreach (['/admin', '/admin/login', '/livewire/update', '/api/vehicles', '/api/auth/login', '/api/budgets'] as $path) {
            $this->get($host.$path)->assertNotFound();
        }
        $this->postJson("$host/api/auth/login", ['email' => 'pres@logimaster.test', 'password' => 'password'])->assertNotFound();
    }

    public function test_local_addresses_keep_full_access_in_tunnel_mode(): void
    {
        config(['logimaster.tunnel_mode' => true]);
        foreach (['http://localhost', 'http://127.0.0.1', 'http://192.168.1.2:8085', 'http://10.0.0.5'] as $host) {
            $this->get("$host/admin/login")->assertOk();
        }
        $this->get('http://localhost/')->assertSee('href="/admin"', false);
    }

    public function test_https_is_detected_behind_the_tunnel(): void
    {
        $r = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'abc.lhr.life'])->get('http://abc.lhr.life/m');
        $r->assertOk();
        $this->assertStringContainsString('"api":"\/api\/mobile"', $r->getContent());   // adresse relative : jamais de contenu mixte
    }
}
