<?php

namespace App\Providers\Filament;

use App\Models\District;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Blue,
                'danger'  => Color::Red,
                'success' => Color::Green,
                'warning' => Color::Orange,
                'info'    => Color::Sky,
            ])
            ->brandName('LogiMaster Pro')
            ->renderHook(\Filament\View\PanelsRenderHook::BODY_START, fn () => view('filament.preloader'))   // préchargeur au chargement des pages
            ->tenant(District::class, ownershipRelationship: 'districts')
            ->databaseNotifications()
            // Menu du district (en haut à gauche) : réglages et organisation, sous la recherche de district
            ->tenantMenuItems([
                \Filament\Actions\Action::make('espc')->label('Centres de santé')->icon('heroicon-o-building-office-2')
                    ->url(fn () => \App\Filament\Resources\Espcs\EspcResource::getUrl())
                    ->visible(fn () => \App\Filament\Resources\Espcs\EspcResource::canAccess()),
                \Filament\Actions\Action::make('personnel')->label('Personnel')->icon('heroicon-o-user-group')
                    ->url(fn () => \App\Filament\Resources\Personnels\PersonnelResource::getUrl())
                    ->visible(fn () => \App\Filament\Resources\Personnels\PersonnelResource::canAccess()),
                \Filament\Actions\Action::make('districts')->label('Districts')->icon('heroicon-o-building-office')
                    ->url(fn () => \App\Filament\Resources\Districts\DistrictResource::getUrl())
                    ->visible(fn () => \App\Filament\Resources\Districts\DistrictResource::canAccess()),
                \Filament\Actions\Action::make('regions')->label('Régions')->icon('heroicon-o-map')
                    ->url(fn () => \App\Filament\Resources\Regions\RegionResource::getUrl())
                    ->visible(fn () => \App\Filament\Resources\Regions\RegionResource::canAccess()),
                \Filament\Actions\Action::make('pres')->label('PRES')->icon('heroicon-o-globe-alt')
                    ->url(fn () => \App\Filament\Resources\Pres\PresResource::getUrl())
                    ->visible(fn () => \App\Filament\Resources\Pres\PresResource::canAccess()),
                \Filament\Actions\Action::make('utilisateurs')->label('Utilisateurs')->icon('heroicon-o-users')
                    ->url(fn () => \App\Filament\Resources\Users\UserResource::getUrl())
                    ->visible(fn () => \App\Filament\Resources\Users\UserResource::canAccess()),
                \Filament\Actions\Action::make('roles')->label('Rôles')->icon('heroicon-o-shield-check')
                    ->url(fn () => \App\Filament\Resources\Roles\RoleResource::getUrl())
                    ->visible(fn () => \App\Filament\Resources\Roles\RoleResource::canAccess()),
            ])
            // Menu du profil (en haut à droite) : outils techniques
            ->userMenuItems([
                \Filament\Actions\Action::make('sauvegardes')->label('Sauvegardes')->icon('heroicon-o-circle-stack')
                    ->url(fn () => \App\Filament\Pages\Backups::getUrl())
                    ->visible(fn () => \App\Filament\Pages\Backups::canAccess()),
                \Filament\Actions\Action::make('sentryTest')->label('Tester le suivi des erreurs')->icon('heroicon-o-bug-ant')
                    ->visible(fn () => (bool) auth()->user()?->isNational())
                    ->requiresConfirmation()
                    ->modalHeading('Envoyer une erreur de test à Sentry ?')
                    ->modalDescription("Une erreur volontaire est enregistrée sur le serveur et transmise à Sentry. L'application continue de fonctionner normalement.")
                    ->modalSubmitActionLabel('Envoyer le test')
                    ->action(function () {
                        $r = \App\Support\SentryCheck::send();
                        $n = \Filament\Notifications\Notification::make()->title($r['ok'] ? 'Test envoyé' : 'Test non envoyé')->body($r['message'])->persistent();
                        ($r['ok'] ? $n->success() : $n->warning())->send();
                    }),
            ])
            ->navigationGroups(['Finance', 'Carburant', 'Maintenance', 'Données', 'Administration'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
