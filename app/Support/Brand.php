<?php

namespace App\Support;

/**
 * Logo et icônes de LogiMaster. Les adresses portent la date du fichier (?v=…) : quand le logo change,
 * navigateurs, téléphones et cache de l'hébergeur reprennent la nouvelle image au lieu de garder l'ancienne.
 */
class Brand
{
    public static function url(string $path): string
    {
        $path = ltrim($path, '/');
        $time = @filemtime(public_path($path));

        return '/'.$path.($time ? '?v='.$time : '');
    }

    /** Logo vectoriel (net à toutes les tailles). */
    public static function logo(): string
    {
        return self::url('logo.svg');
    }

    public static function favicon(): string
    {
        return self::url('pwa/icon-192.png');
    }
}
