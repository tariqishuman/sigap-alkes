<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\SigapAvatarProvider;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\NotificationPreferences;
use App\Services\SettingsService;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
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
            ->brandLogoHeight('1.75rem')
            ->favicon(fn (): string => $settings->faviconUrl())
            ->login()
            ->darkMode(false)
            ->font('Plus Jakarta Sans')
            ->maxContentWidth(Width::Full)
            ->defaultAvatarProvider(SigapAvatarProvider::class)
            ->sidebarWidth('248px')
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldSuffix(fn (): string => 'Ctrl K')
            ->databaseNotifications()
            ->userMenuItems([
                Action::make('device_notifications')
                    ->label('Notifikasi di perangkat ini')
                    ->icon(Heroicon::OutlinedBellAlert)
                    ->url(fn (): string => NotificationPreferences::getUrl()),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): HtmlString => new HtmlString('<link rel="manifest" href="/manifest.webmanifest"><meta name="theme-color" content="#0E7C7B">'),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn () => view('filament.auth.login-illustration'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn () => view('filament.topbar.quick-create'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_AFTER,
                fn () => view('filament.topbar.user-identity'),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn () => view('filament.sidebar.footer'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('components.watermark-kelompok2'),
            )
            ->colors([
                'primary' => [
                    50 => '#E8F4F3',
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
                NavigationGroup::make('Umum')->collapsible(false),
                NavigationGroup::make('Alat')->collapsible(false),
                NavigationGroup::make('Kegiatan')->collapsible(false),
                NavigationGroup::make('Laporan')->collapsible(false),
                NavigationGroup::make('Data Master')->collapsible(false),
                NavigationGroup::make('Admin')->collapsible(false),
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
                    ->navigationGroup('Admin')
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
