<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;

/** Application mobile installable (PWA) du convoyeur : page, manifeste, service worker, QR code d'installation. */
class MobileAppController extends Controller
{
    /** Adresse à ouvrir sur le téléphone (doit être joignable depuis le téléphone, en HTTPS pour l'installation). */
    public static function appUrl(): string
    {
        return rtrim((string) (config('logimaster.mobile_url') ?: url('/m')), '/');
    }

    public function app()
    {
        return view('mobile.app', ['config' => [
            'api' => '/api/mobile',
            'logo' => \App\Support\Brand::logo(),
            'vapidKey' => config('webpush.public_key'),
        ]]);
    }

    public function install()
    {
        return view('mobile.install', ['url' => self::appUrl(), 'secure' => str_starts_with(self::appUrl(), 'https://')]);
    }

    /** QR code (SVG) de l'adresse de l'application : scanner avec l'appareil photo du téléphone. */
    public static function qrSvg(?string $url = null, int $size = 320): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, 2), new SvgImageBackEnd));

        return $writer->writeString($url ?? self::appUrl());
    }

    public function qr(): Response
    {
        return response(self::qrSvg(), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=3600']);
    }

    public function manifest(): Response
    {
        return response(json_encode([
            'name' => 'LogiMaster Convoyeur',
            'short_name' => 'LogiMaster',
            'description' => 'Exécutez vos circuits : livraisons, carburant, notifications.',
            'lang' => 'fr',
            'start_url' => '/m?source=pwa',
            'scope' => '/m',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#f3efe6',
            'theme_color' => '#f3efe6',
            'icons' => [
                ['src' => \App\Support\Brand::url('pwa/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => \App\Support\Brand::url('pwa/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => \App\Support\Brand::url('pwa/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache']);
    }

    /** Service worker servi depuis /m/sw.js : sa portée couvre l'application (/m). */
    public function serviceWorker(): Response
    {
        // version = empreinte de l'application mobile, du service worker et des icônes (change à chaque modification déployée)
        $files = [resource_path('views/mobile/app.blade.php'), resource_path('views/mobile/sw.blade.php'), ...glob(public_path('pwa/*.png'))];   // icônes comprises
        $version = substr(md5(implode('|', array_map(fn ($f) => @filemtime($f).'-'.@filesize($f), $files))), 0, 10);

        return response(view('mobile.sw', ['version' => $version])->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            'Service-Worker-Allowed' => '/m',
        ]);
    }
}
