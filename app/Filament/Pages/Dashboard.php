<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\AssetValueByRoomWidget;
use App\Filament\Widgets\CalibrationBodyDashboardWidget;
use App\Filament\Widgets\DamagesBreakdownChart;
use App\Filament\Widgets\ExecutiveTopMetricsWidget;
use App\Filament\Widgets\HeadNurseDashboardWidget;
use App\Filament\Widgets\InternDashboardWidget;
use App\Filament\Widgets\MonthlyUptimeChart;
use App\Filament\Widgets\TechnicianDashboardWidget;
use App\Filament\Widgets\UpcomingCalibrationsWidget;
use App\Models\Room;
use App\Services\DashboardMetricsService;
use App\Services\SettingsService;
use BackedEnum;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::Squares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Umum';

    protected static ?string $navigationLabel = 'Ringkasan';

    protected static ?string $title = 'Ringkasan';

    protected static ?int $navigationSort = 1;

    public function getSubheading(): ?string
    {
        $hospital = app(SettingsService::class)->hospitalName();
        $today = now()->translatedFormat('d F Y');

        return "Kondisi alat kesehatan {$hospital} per {$today}";
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 12,
            'xl' => 12,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        if (! $this->isExecutiveRole()) {
            return $schema->components([]);
        }

        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'sm' => 3,
                ])
                ->extraAttributes(['class' => 'sigap-dashboard-filters'])
                ->schema([
                    DatePicker::make('date')
                        ->label('Tanggal acuan')
                        ->default(now()->toDateString())
                        ->native(false)
                        ->displayFormat('d M Y')
                        ->live(),
                    Select::make('period')
                        ->label('Periode')
                        ->options([
                            'monthly' => 'Bulanan',
                            'weekly' => 'Mingguan',
                            'daily' => 'Harian',
                        ])
                        ->default('monthly')
                        ->selectablePlaceholder(false)
                        ->live(),
                    Select::make('room_id')
                        ->label('Ruangan')
                        ->placeholder('Semua ruangan')
                        ->options(fn (): array => Room::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                ]),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        if (! $this->isExecutiveRole()) {
            return [];
        }

        return [
            Action::make('export_pdf')
                ->label('Ekspor')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->downloadDashboardPdf()),
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            ExecutiveTopMetricsWidget::class,
            DamagesBreakdownChart::class,
            UpcomingCalibrationsWidget::class,
            MonthlyUptimeChart::class,
            AssetValueByRoomWidget::class,
            TechnicianDashboardWidget::class,
            HeadNurseDashboardWidget::class,
            CalibrationBodyDashboardWidget::class,
            InternDashboardWidget::class,
        ];
    }

    private function isExecutiveRole(): bool
    {
        return Auth::user()?->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Manajemen->value,
        ]) ?? false;
    }

    private function downloadDashboardPdf(): StreamedResponse
    {
        $period = (string) ($this->filters['period'] ?? 'monthly');
        $date = is_string($this->filters['date'] ?? null) ? $this->filters['date'] : null;
        $roomId = filled($this->filters['room_id'] ?? null) ? (int) $this->filters['room_id'] : null;

        $service = app(DashboardMetricsService::class);
        $settings = app(SettingsService::class);
        $roomName = $roomId ? (Room::find($roomId)?->name ?? 'Ruangan') : 'Semua ruangan';

        $pdf = Pdf::loadView('pdf.dashboard-summary', [
            'appName' => $settings->appName(),
            'hospitalName' => $settings->hospitalName(),
            'roomName' => $roomName,
            'generatedAt' => now()->translatedFormat('d F Y, H:i'),
            'top' => $service->executiveTopMetrics($period, $date, $roomId),
            'upcoming' => $service->upcomingCalibrations($roomId, 6),
            'bookValue' => $service->bookValueByRoom($roomId),
        ])->setPaper('a4', 'portrait');

        $filename = 'ringkasan-sigap-alkes-'.now()->format('Ymd-His').'.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
