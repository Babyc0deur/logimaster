<?php

/*
| Routeur du serveur de développement PHP (php -S) : sert les fichiers statiques de public/ et envoie le reste à Laravel.
| Utilisé par .claude/launch.json pour pouvoir fixer les limites PHP (taille des envois, dossier temporaire) que
| `php artisan serve` ne permet pas de régler : les photos de factures prises au téléphone dépassent la limite par défaut.
*/

$publicPath = __DIR__.'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

if ($uri !== '/' && is_file($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
