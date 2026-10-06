<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Indicateurs DDKM : jamais calculés à la volée, uniquement par snapshots planifiés.
// Quotidien : mois courant.
Schedule::command('indicators:compute --queue')->dailyAt('01:00')->withoutOverlapping();
// Rapports : envois planifiés chaque matin ; le 1er du mois, rapport mensuel DDKM automatique aux superviseurs.
Schedule::command('reports:send')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('reports:send --monthly')->monthlyOn(1, '03:30')->name('reports-monthly-ddkm')->withoutOverlapping();
// Alertes maintenance (échéances J-30/15/7, vidanges, immobilisations) : notifications in-app chaque matin.
Schedule::command('fleet:alerts')->dailyAt('07:00')->withoutOverlapping();
// Clôture mensuelle : le 1er de chaque mois, on fige le mois qui vient de se terminer.
Schedule::call(fn () => Artisan::call('indicators:compute', [
    '--period' => now()->subMonthNoOverflow()->format('Y-m'),
    '--queue' => true,
]))->monthlyOn(1, '02:00')->name('indicators-month-close')->withoutOverlapping();
// Sauvegardes (base + photos) vers le stockage externe : toutes les heures, mais seulement si la dernière a plus de 20 h
// (le serveur peut être en veille à heure fixe) ; test de restauration chaque dimanche.
Schedule::command('backup:run --if-due')->hourly()->withoutOverlapping();
Schedule::command('backup:verify')->weeklyOn(0, '04:30')->withoutOverlapping();
