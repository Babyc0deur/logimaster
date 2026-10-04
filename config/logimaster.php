<?php

return [

    /*
    | Période affichée par défaut dans les filtres (tableau de bord, indicateurs, finance, carburant) : les données réelles
    | chargées vont de mai à octobre 2025. Les deux bornes restent modifiables par l'utilisateur dans les filtres.
    | Laisser vide (LOGIMASTER_PERIOD_FROM= / LOGIMASTER_PERIOD_UNTIL=) pour revenir au mois en cours.
    */
    'default_period' => [
        'from' => env('LOGIMASTER_PERIOD_FROM', '2025-06-11') ?: null,
        'until' => env('LOGIMASTER_PERIOD_UNTIL', '2026-10-12') ?: null,
    ],

    /*
    | Adresse que le téléphone ouvre pour installer l'application mobile (QR code). Doit être joignable depuis le téléphone
    | et en HTTPS (installation + notifications). Défaut : APP_URL/m.
    */
    'mobile_url' => env('LOGIMASTER_MOBILE_URL'),

    /*
    | Mode tunnel : l'application est exposée sur Internet (tunnel HTTPS) pour installer la PWA sur les téléphones. Depuis
    | une adresse publique, seuls l'accueil, l'application mobile (/m) et son API (/api/mobile) répondent ; l'administration
    | reste réservée au réseau local. À activer avec LOGIMASTER_TUNNEL_MODE=true.
    */
    'tunnel_mode' => (bool) env('LOGIMASTER_TUNNEL_MODE', false),

    /*
    | Application mobile : tout chef de mission ou passager actif reçoit automatiquement un accès (identifiant + code
    | provisoire). Mettre LOGIMASTER_MOBILE_AUTO_ACCESS=false pour désactiver la création automatique.
    */
    'mobile' => [
        'auto_access' => (bool) env('LOGIMASTER_MOBILE_AUTO_ACCESS', true),
    ],

    /*
    | Modules optionnels. Finance = budgets, factures, tableau de bord financier, rapport financier, alertes de budget.
    | Retiré par défaut : mettre LOGIMASTER_FINANCE=true dans .env pour le réactiver (le code et les données sont conservés).
    */
    'modules' => [
        'finance' => (bool) env('LOGIMASTER_FINANCE', false),
    ],

];
