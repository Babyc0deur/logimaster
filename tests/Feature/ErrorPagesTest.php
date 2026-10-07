<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_unknown_page_shows_the_branded_french_404(): void
    {
        $this->get('/cette-page-n-existe-pas')->assertNotFound()
            ->assertSee('LogiMaster')->assertSee('Page introuvable')->assertSee('Erreur 404')->assertSee('Retour à l', false);
    }

    public function test_api_errors_stay_json(): void
    {
        $this->getJson('/api/inconnu')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_every_error_view_renders_with_its_message(): void
    {
        foreach ([401 => 'Connexion requise', 403 => 'Accès refusé', 404 => 'Page introuvable', 419 => 'Session expirée', 429 => 'Trop de requêtes', 500 => 'Erreur interne', 503 => 'Service en maintenance'] as $code => $title) {
            $html = view("errors.{$code}")->render();
            $this->assertStringContainsString($title, $html, "errors.{$code}");
            $this->assertStringContainsString("Erreur {$code}", $html);
        }
        $generic = view('errors.4xx', ['exception' => new \Symfony\Component\HttpKernel\Exception\HttpException(422)])->render();
        $this->assertStringContainsString('Erreur 422', $generic);
    }
}
