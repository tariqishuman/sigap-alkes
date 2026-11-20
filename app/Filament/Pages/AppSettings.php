<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * @property-read Schema $form
 */
class AppSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Cog6Tooth;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Pengaturan Aplikasi';

    protected static ?string $title = 'Pengaturan Aplikasi';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'pengaturan-aplikasi';

    protected string $view = 'filament.pages.app-settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(UserRole::SuperAdmin->value) ?? false;
    }

    public function mount(SettingsService $settings): void
    {
        $days = $settings->get('reminder.days', [30, 7, 0]);
        $daysText = is_array($days) ? implode(', ', $days) : (string) $days;

        $this->form->fill([
            'app_name' => $settings->get('general.app_name'),
            'hospital_name' => $settings->get('general.hospital_name'),
            'hospital_address' => $settings->get('general.hospital_address'),
            'hospital_phone' => $settings->get('general.hospital_phone'),
            'logo_path' => $settings->get('general.logo_path'),
            'favicon_path' => $settings->get('general.favicon_path'),
            'timezone' => $settings->get('general.timezone'),

            'reminder_days' => $daysText,
            'reminder_send_time' => $settings->get('reminder.send_time'),
            'reminder_email_enabled' => (bool) $settings->get('reminder.email_enabled', true),

            'replace_threshold_percent' => $settings->replaceThresholdPercent(),
            'uptime_target_percent' => $settings->uptimeTargetPercent(),

            'qr_public_base_url' => $settings->get('qr.public_base_url'),
            'qr_label_footer_text' => $settings->get('qr.label_footer_text'),

            'satusehat_enabled' => (bool) $settings->get('satusehat.enabled', false),
            'satusehat_environment' => $settings->get('satusehat.environment', 'sandbox'),
            'satusehat_auth_url' => $settings->get('satusehat.auth_url'),
            'satusehat_fhir_base_url' => $settings->get('satusehat.fhir_base_url'),
            'satusehat_organization_id' => $settings->get('satusehat.organization_id'),
            'satusehat_client_id' => $settings->get('satusehat.client_id'),
            // Never load or expose the encrypted secret back to the browser.
            'satusehat_client_secret' => '',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $hasSecret = app(SettingsService::class)->hasEncryptedValue('satusehat.client_secret');

        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Pengaturan')
                    ->tabs([
                        Tab::make('Umum')
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->columns(2)
                            ->schema([
                                TextInput::make('app_name')
                                    ->label('Nama Aplikasi')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('hospital_name')
                                    ->label('Nama Rumah Sakit')
                                    ->required()
                                    ->maxLength(150),
                                Textarea::make('hospital_address')
                                    ->label('Alamat Rumah Sakit')
                                    ->rows(2)
                                    ->maxLength(500)
                                    ->columnSpanFull(),
                                TextInput::make('hospital_phone')
                                    ->label('Telepon Rumah Sakit')
                                    ->maxLength(50),
                                Select::make('timezone')
                                    ->label('Zona Waktu')
                                    ->options([
                                        'Asia/Jakarta' => 'WIB (Asia/Jakarta)',
                                        'Asia/Makassar' => 'WITA (Asia/Makassar)',
                                        'Asia/Jayapura' => 'WIT (Asia/Jayapura)',
                                    ])
                                    ->required(),
                                FileUpload::make('logo_path')
                                    ->label('Logo Aplikasi')
                                    ->image()
                                    ->disk('public')
                                    ->directory('settings')
                                    ->visibility('public')
                                    ->helperText('Kosongkan untuk memakai logo bawaan.'),
                                FileUpload::make('favicon_path')
                                    ->label('Favicon')
                                    ->disk('public')
                                    ->directory('settings')
                                    ->visibility('public')
                                    ->acceptedFileTypes(['image/svg+xml', 'image/png', 'image/x-icon'])
                                    ->helperText('Kosongkan untuk memakai favicon bawaan.'),
                            ]),

                        Tab::make('Pengingat')
                            ->icon(Heroicon::OutlinedBellAlert)
                            ->columns(2)
                            ->schema([
                                TextInput::make('reminder_days')
                                    ->label('Hari Pengingat Kalibrasi')
                                    ->placeholder('30, 7, 0')
                                    ->helperText('Pisahkan dengan koma, contoh: 30, 7, 0.')
                                    ->required(),
                                TextInput::make('reminder_send_time')
                                    ->label('Jam Kirim Pengingat')
                                    ->placeholder('07:00')
                                    ->regex('/^([01]\d|2[0-3]):[0-5]\d$/')
                                    ->helperText('Format 24 jam, contoh: 07:00.')
                                    ->required(),
                                Toggle::make('reminder_email_enabled')
                                    ->label('Aktifkan pengiriman pengingat via email')
                                    ->helperText('Notifikasi dalam aplikasi tetap aktif.')
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Nilai Aset')
                            ->icon(Heroicon::OutlinedBanknotes)
                            ->columns(2)
                            ->schema([
                                TextInput::make('replace_threshold_percent')
                                    ->label('Ambang "Pertimbangkan ganti"')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->helperText('Alat ditandai bila biaya perbaikan mencapai persentase ini dari nilai buku.')
                                    ->required(),
                                TextInput::make('uptime_target_percent')
                                    ->label('Target Uptime Bulanan')
                                    ->integer()
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->helperText('Batas minimal uptime untuk penanda target di dashboard.')
                                    ->required(),
                            ]),

                        Tab::make('Label QR & Publik')
                            ->icon(Heroicon::OutlinedQrCode)
                            ->columns(2)
                            ->schema([
                                TextInput::make('qr_public_base_url')
                                    ->label('URL Publik untuk QR')
                                    ->url()
                                    ->placeholder('https://alkes.rs-contoh.go.id')
                                    ->helperText('Kosongkan untuk memakai alamat aplikasi bawaan.')
                                    ->nullable()
                                    ->columnSpanFull(),
                                TextInput::make('qr_label_footer_text')
                                    ->label('Teks Bawah Label QR')
                                    ->maxLength(120)
                                    ->required()
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Integrasi SatuSehat')
                            ->icon(Heroicon::OutlinedGlobeAlt)
                            ->columns(2)
                            ->schema([
                                Toggle::make('satusehat_enabled')
                                    ->label('Aktifkan integrasi SatuSehat')
                                    ->columnSpanFull(),
                                Select::make('satusehat_environment')
                                    ->label('Environment')
                                    ->options([
                                        'sandbox' => 'Sandbox (Uji Coba)',
                                        'production' => 'Production',
                                    ])
                                    ->required(),
                                TextInput::make('satusehat_organization_id')
                                    ->label('Organization ID')
                                    ->maxLength(150)
                                    ->nullable(),
                                TextInput::make('satusehat_auth_url')
                                    ->label('Auth URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->nullable(),
                                TextInput::make('satusehat_fhir_base_url')
                                    ->label('FHIR Base URL')
                                    ->url()
                                    ->maxLength(255)
                                    ->nullable(),
                                TextInput::make('satusehat_client_id')
                                    ->label('Client ID')
                                    ->maxLength(255)
                                    ->nullable(),
                                TextInput::make('satusehat_client_secret')
                                    ->label('Client Secret')
                                    ->password()
                                    ->placeholder($hasSecret ? '•••• (tersimpan)' : 'Masukkan Client Secret')
                                    ->autocomplete('new-password')
                                    ->nullable(),
                            ]),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_satusehat')
                ->label('Tes koneksi')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->action(function (): void {
                    $result = app(SettingsService::class)->testSatuSehatConnection();

                    $notification = Notification::make()->title($result['message']);
                    $result['ok'] ? $notification->success() : $notification->danger();
                    $notification->send();
                }),
        ];
    }

    public function save(SettingsService $settings): void
    {
        $state = $this->form->getState();

        $days = collect(explode(',', (string) ($state['reminder_days'] ?? '30, 7, 0')))
            ->map(fn (string $v): int => (int) trim($v))
            ->filter(fn (int $v): bool => $v >= 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $settings->setMany([
            'general.app_name' => $state['app_name'],
            'general.hospital_name' => $state['hospital_name'],
            'general.hospital_address' => $state['hospital_address'] ?? null,
            'general.hospital_phone' => $state['hospital_phone'] ?? null,
            'general.logo_path' => $state['logo_path'] ?? null,
            'general.favicon_path' => $state['favicon_path'] ?? null,
            'general.timezone' => $state['timezone'] ?? 'Asia/Jakarta',

            'reminder.days' => ! empty($days) ? $days : [30, 7, 0],
            'reminder.send_time' => $state['reminder_send_time'] ?? '07:00',
            'reminder.email_enabled' => (bool) ($state['reminder_email_enabled'] ?? true),

            'asset.replace_threshold_percent' => (int) ($state['replace_threshold_percent'] ?? 50),
            'asset.uptime_target_percent' => max(1, min(100, (int) ($state['uptime_target_percent'] ?? 95))),

            'qr.public_base_url' => filled($state['qr_public_base_url'] ?? null) ? trim((string) $state['qr_public_base_url']) : null,
            'qr.label_footer_text' => $state['qr_label_footer_text'] ?? 'Scan QR untuk cek status alat',

            'satusehat.enabled' => (bool) ($state['satusehat_enabled'] ?? false),
            'satusehat.environment' => $state['satusehat_environment'] ?? 'sandbox',
            'satusehat.auth_url' => $state['satusehat_auth_url'] ?? null,
            'satusehat.fhir_base_url' => $state['satusehat_fhir_base_url'] ?? null,
            'satusehat.organization_id' => $state['satusehat_organization_id'] ?? null,
            'satusehat.client_id' => $state['satusehat_client_id'] ?? null,
            'satusehat.client_secret' => $state['satusehat_client_secret'] ?? null,
        ]);

        // Clear the secret input in Livewire state so it never stays in memory/HTML.
        $this->data['satusehat_client_secret'] = '';

        Notification::make()
            ->title('Pengaturan aplikasi berhasil disimpan')
            ->success()
            ->send();
    }
}
