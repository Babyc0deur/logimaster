<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** Photos privées prises sur le terrain (factures, compteurs, bons de livraison) : affichées dans l'administration sans URL publique. */
final class PrivatePhoto
{
    public static function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && preg_match('/\.(jpe?g|png|webp)$/i', $path) && Storage::disk('local')->exists($path);
    }

    /** Image intégrée à la page (data URL), ou null si absente. */
    public static function dataUri(?string $path): ?string
    {
        return self::exists($path) ? 'data:'.Storage::disk('local')->mimeType($path).';base64,'.base64_encode(Storage::disk('local')->get($path)) : null;
    }
}
