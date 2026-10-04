<?php

namespace App\Domain\Fleet;

use App\Models\Setting;

/** Niveau d'alerte maintenance : 🔴 urgent (< 100 km ou < 7 j), 🟠 attention (< 500 km ou < 30 j), 🟢 OK. */
enum AlertLevel: string
{
    case Urgent = 'urgent';
    case Attention = 'attention';
    case Ok = 'ok';

    public static function forKm(?int $remaining): self
    {
        return match (true) {
            $remaining === null => self::Ok,
            $remaining < Setting::get('seuil_vidange_urgent_km') => self::Urgent,
            $remaining < Setting::get('seuil_vidange_attention_km') => self::Attention,
            default => self::Ok,
        };
    }

    public static function forDays(?int $days): self
    {
        return match (true) {
            $days === null => self::Ok,
            $days < Setting::get('seuil_echeance_urgent_jours') => self::Urgent,
            $days < Setting::get('seuil_echeance_attention_jours') => self::Attention,
            default => self::Ok,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Urgent => 'Urgent',
            self::Attention => 'Attention',
            self::Ok => 'OK',
        };
    }

    /** Couleur Filament (badge). */
    public function color(): string
    {
        return match ($this) {
            self::Urgent => 'danger',
            self::Attention => 'warning',
            self::Ok => 'success',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::Urgent => '🔴',
            self::Attention => '🟠',
            self::Ok => '🟢',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Urgent => 0,
            self::Attention => 1,
            self::Ok => 2,
        };
    }
}
