<?php

return [

    /*
    | Période affichée par défaut dans les filtres (tableau de bord, indicateurs, finance, carburant) : les données réelles
    | chargées vont de mai à octobre 2025. Les deux bornes restent modifiables par l'utilisateur dans les filtres.
    | Laisser vide (LOGIMASTER_PERIOD_FROM= / LOGIMASTER_PERIOD_UNTIL=) pour revenir au mois en cours.
    */
    // mois de la saison sèche (les autres mois forment la saison pluvieuse)
    'dry_season_months' => array_map('intval', explode(',', env('LOGIMASTER_DRY_SEASON_MONTHS', '11,12,1,2,3'))),

    'default_period' => [
        'from' => env('LOGIMASTER_PERIOD_FROM', '2025-06-11') ?: null,
        'until' => env('LOGIMASTER_PERIOD_UNTIL', '') ?: null   // vide = sans limite haute,
    ],

    // Tableau de bord à l'arrivée : période affichée (les listes gardent la période par défaut ci-dessus)
    'dashboard_period' => [
        'from' => env('LOGIMASTER_DASHBOARD_FROM', '2025-10-01') ?: null,
        'until' => env('LOGIMASTER_DASHBOARD_UNTIL', '2025-10-31') ?: null,
    ],

    // District ouvert après la connexion (s'il fait partie des districts de l'utilisateur ; sinon le premier de sa liste)
    'default_district' => env('LOGIMASTER_DEFAULT_DISTRICT', 'MEAGUI'),

    // Cartographie : chef-lieu des districts (latitude, longitude), centre de la carte et point de départ des véhicules
    'district_centres' => [
        'MEAGUI' => [5.4045, -6.5582],
        'SOUBRE' => [5.7853, -6.6083],
        'GUEYO' => [5.6880, -6.0712],
        'BUYO' => [6.2474, -7.0024],
    ],

    /*
    | Adresse que le téléphone ouvre pour installer l'application mobile (QR code). Doit être joignable depuis le téléphone
    | et en HTTPS (installation de l'application). Défaut : APP_URL/m.
    */
    'mobile_url' => env('LOGIMASTER_MOBILE_URL'),

    /*
    | Sauvegardes : base de données + photos (factures, compteurs, bons de livraison) dans une archive zip, envoyée chaque nuit
    | vers le disque « backups » (stockage externe S3) s'il est configuré, sinon dans storage/app/backups (même serveur : à éviter).
    */
    'backup' => [
        'external' => filled(env('BACKUP_S3_BUCKET')),
        'folder' => env('BACKUP_FOLDER', 'logimaster'),
        'keep_daily' => (int) env('BACKUP_KEEP_DAYS', 30),       // une sauvegarde par jour sur 30 jours
        'keep_monthly' => (int) env('BACKUP_KEEP_MONTHS', 12),   // puis la première de chaque mois sur 12 mois
        'photos' => ['factures', 'compteurs', 'preuves', 'signalements'],
        'restore_if_empty' => (bool) env('BACKUP_RESTORE_IF_EMPTY', true),   // au démarrage, base vide : reprendre la dernière sauvegarde
    ],

    // Vidange déclarée sans prochain kilométrage : échéance suivante = km de la vidange + cet intervalle
    'vidange_interval_km' => (int) env('LOGIMASTER_VIDANGE_INTERVAL_KM', 5000),

    /*
    | Modules optionnels. Finance = budgets, factures, tableau de bord financier, rapport financier, alertes de budget.
    | Retiré par défaut : mettre LOGIMASTER_FINANCE=true dans .env pour le réactiver (le code et les données sont conservés).
    */
    'modules' => [
        'finance' => (bool) env('LOGIMASTER_FINANCE', false),
    ],

];
