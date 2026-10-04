<?php

return [

    /*
    | Clés VAPID du serveur d'envoi Web Push (notifications sur le téléphone du convoyeur).
    | Générer une paire : php artisan webpush:vapid  puis copier les valeurs dans .env.
    */
    'public_key' => env('VAPID_PUBLIC_KEY'),
    'private_key' => env('VAPID_PRIVATE_KEY'),
    'subject' => env('VAPID_SUBJECT', 'mailto:admin@logimaster.test'),

    // Windows : chemin d'un openssl.cnf valide si PHP ne trouve pas le sien (erreur « Unable to create the key »).
    'openssl_conf' => env('WEBPUSH_OPENSSL_CONF'),

];
