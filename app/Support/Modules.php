<?php

namespace App\Support;

/** Modules optionnels de l'application (config « logimaster.modules »). */
final class Modules
{
    public static function enabled(string $module): bool
    {
        return (bool) config("logimaster.modules.{$module}", true);
    }
}
