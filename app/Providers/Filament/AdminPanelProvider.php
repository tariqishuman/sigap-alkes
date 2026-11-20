<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Services\SettingsService;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $settings = app(SettingsService::class);

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName(fn (): string => $settings->appName())
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.25rem')
            ->favicon(fn (): string => $settings->faviconUrl())
            ->login()
            ->darkMode(false)
            ->font('Poppins')
            ->maxContentWidth(Width::Full)
            ->sidebarCollapsibleOnDesktop()
            ->databaseNotifications()
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn () => view('filament.auth.login-illustration'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('components.watermark-kelompok2'),
            )
            ->colors([
                'primary' => [
                    50 => '#EEF6F5',
                    100 => '#D5ECEA',
                    200 => '#ADDAD6',
                    300 => '#7BC2BC',
                    400 => '#4BA69F',
                    500 => '#0E7C7B',
                    600 => '#0B6463',
                    700 => '#09504F',
                    800 => '#083F3E',
                    900 => '#063231',
                    950 => '#031E1E',
                ],
                'success' => Color::hex('#2E9E6B'),
                'warning' => Color::hex('#E0A23B'),
                'danger' => Color::hex('#D9534F'),
                'info' => Color::Blue,
                'gray' => Color::Zinc,
            ])
            ->navigationGroups([
                NavigationGroup::make('Alat'),
                NavigationGroup::make('Kegiatan'),
                NavigationGroup::make('Laporan'),
                NavigationGroup::make('Data Master'),
                NavigationGroup::make('Pengaturan'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->plugins([
                FilamentShieldPlugin::make()
                    ->navigationGroup('Pengaturan')
                    ->navigationLabel('Role')
                    ->modelLabel('Role')
                    ->pluralModelLabel('Role'),
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
