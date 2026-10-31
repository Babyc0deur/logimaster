<?php

use App\Http\Controllers\DistrictSearchController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

// Page d'accueil : présentation de l'application et accès à l'administration
Route::get('/', LandingController::class);
Route::get('/presentation', fn () => response()->file(public_path('presentation/index.html')));   // présentation animée de l'application

// Sélecteur de district de l'administration : recherche à la frappe (utilisateur connecté, districts accessibles seulement)
Route::get('/ui/districts/search', DistrictSearchController::class)->middleware(['web', 'auth'])->name('districts.search');
