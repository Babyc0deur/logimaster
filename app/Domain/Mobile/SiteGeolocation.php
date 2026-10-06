<?php

namespace App\Domain\Mobile;

use App\Models\LivraisonEspc;
use App\Support\Geo;

/**
 * Position des centres de santé relevée par le téléphone du convoyeur au moment de la livraison :
 * - si le centre n'a pas encore de coordonnées et que la position est précise, elle devient sa position (source « livraison ») ;
 * - si le centre a déjà des coordonnées, l'écart est enregistré sur la livraison : un grand écart signale une livraison
 *   déclarée ailleurs que sur le site (ou des coordonnées de centre à corriger).
 */
class SiteGeolocation
{
    /** Précision maximale (mètres) pour qu'une position serve à placer un centre. */
    public static function maxPrecision(): int
    {
        return (int) config('logimaster.mobile.gps_precision_max', 150);
    }

    /** Écart (mètres) au-delà duquel une livraison « sur site » est signalée. */
    public static function alertDistance(): int
    {
        return (int) config('logimaster.mobile.gps_ecart_alerte', 1000);
    }

    public function record(LivraisonEspc $livraison, ?float $lat, ?float $lon, ?float $precision): void
    {
        $livraison->forceFill(['gps_precision_m' => $precision !== null ? (int) round($precision) : null, 'gps_ecart_m' => null]);
        $espc = $livraison->espc;
        $onSite = $livraison->statut === 'livre' && $livraison->lieu_livraison === 'site';

        if ($lat === null || $lon === null || ! $espc || ! $onSite) {
            $livraison->save();

            return;
        }

        if ($espc->hasGps()) {
            $livraison->gps_ecart_m = (int) round(Geo::haversineKm($espc->gps_lat, $espc->gps_lon, $lat, $lon) * 1000);
        } elseif ($precision !== null && $precision <= self::maxPrecision()) {
            $espc->forceFill(['gps_lat' => $lat, 'gps_lon' => $lon, 'gps_source' => 'livraison', 'gps_releve_at' => now()])->save();
            $livraison->gps_ecart_m = 0;
        }
        $livraison->save();
    }

    public static function isSuspicious(LivraisonEspc $livraison): bool
    {
        return $livraison->gps_ecart_m !== null && $livraison->gps_ecart_m > self::alertDistance();
    }
}
