<?php

/*
| L'application web et l'application convoyeur (/m) appellent l'API depuis le même domaine : aucune autre origine
| n'a besoin d'y accéder. CORS_ALLOWED_ORIGINS (liste séparée par des virgules) permet d'en autoriser d'autres.
*/
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('APP_URL', '')))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];
