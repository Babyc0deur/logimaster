<?php

use App\Http\Controllers\LandingController;
use App\Http\Controllers\MobileAppController;
use Illuminate\Support\Facades\Route;

// Page d'accueil : présentation de l'application, QR code d'installation mobile, accès à l'administration
Route::get('/', LandingController::class);
Route::get('/presentation', fn () => response()->file(public_path('presentation/index.html')));   // présentation animée de l'application

// Application mobile installable (PWA) du convoyeur
Route::get('/m', [MobileAppController::class, 'app']);
Route::get('/m/installer', [MobileAppController::class, 'install']);
Route::get('/m/qr.svg', [MobileAppController::class, 'qr']);
Route::get('/m/manifest.webmanifest', [MobileAppController::class, 'manifest']);
Route::get('/m/sw.js', [MobileAppController::class, 'serviceWorker']);
