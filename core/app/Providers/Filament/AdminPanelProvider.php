<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
|--------------------------------------------------------------------------
| Prince Byron — Owner Admin Panel (Filament 5)
|
| Security requirements (SECURITY.md & ASVS 5.0 Level 2):
| - Owner role check enforced server-side via User::canAccessPanel().
| - Admin route is /admin — authentication + role is the gate.
| - CSRF protection included in middleware stack (PreventRequestForgery).
| - Rate limiting enforced on authentication endpoints.
| - Password recovery enabled with secure tokens.
|--------------------------------------------------------------------------
*/
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->passwordReset()
            ->profile()
            ->authGuard('web')

            // Centralized brand palette: sourced directly from config/tokens.php
            ->colors([
                'primary' => config('tokens.filament.primary'),
                'gray'    => Color::Zinc,
                'danger'  => Color::Rose,
                'warning' => Color::Amber,
                'success' => Color::Emerald,
                'info'    => Color::Sky,
            ])
            ->brandName('Prince Byron Backoffice')

            // Auto-discover resources and pages under App\Filament\
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
