<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\Equipment;
use App\Services\DamageReportService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PublicEquipmentController extends Controller
{
    public function show(string $qr_token): Response
    {
        if (! Str::isUuid($qr_token)) {
            return response()->view('public.equipment-not-found', [], 404);
        }

        $equipment = Equipment::query()
            ->with(['room', 'equipmentType', 'latestValidCalibration'])
            ->where('qr_token', $qr_token)
            ->first();

        if (! $equipment) {
            return response()->view('public.equipment-not-found', [], 404);
        }

        $status = $equipment->status ?? EquipmentStatus::CalibrationDue;

        $statusBadgeColors = match ($status) {
            EquipmentStatus::FitForUse => [
                'bg' => '#E7F5EE',
                'text' => '#22774F',
                'border' => '#B9E2CE',
            ],
            EquipmentStatus::CalibrationDue => [
                'bg' => '#FCF3E2',
                'text' => '#A66F12',
                'border' => '#F3D9A4',
            ],
            EquipmentStatus::Overdue => [
                'bg' => '#FBEAEA',
                'text' => '#B83C38',
                'border' => '#F3C4C2',
            ],
            EquipmentStatus::Unfit => [
                'bg' => '#FBEAEA',
                'text' => '#9A2F2C',
                'border' => '#F3C4C2',
            ],
            EquipmentStatus::UnderTesting => [
                'bg' => '#EAF1FE',
                'text' => '#2563EB',
                'border' => '#BFDBFE',
            ],
            EquipmentStatus::UnderRepair => [
                'bg' => '#EFF2F2',
                'text' => '#55666A',
                'border' => '#D3DEDD',
            ],
        };

        $validUntilFormatted = 'Belum ada sertifikat';
        $certificateStatusLine = 'Belum ada sertifikat kalibrasi';
        if ($equipment->latestValidCalibration?->valid_until) {
            $validUntil = $equipment->latestValidCalibration->valid_until->copy()->startOfDay();
            $validUntilFormatted = $validUntil->translatedFormat('d F Y');
            $daysDiff = (int) today()->diffInDays($validUntil, false);

            if ($status->isDangerousToUse() || $daysDiff < 0) {
                $daysPast = abs($daysDiff);
                $certificateStatusLine = $daysDiff < 0
                    ? "Sertifikat berakhir {$validUntilFormatted} (lewat {$daysPast} hari)"
                    : "Sertifikat berakhir {$validUntilFormatted}";
            } elseif ($daysDiff === 0) {
                $certificateStatusLine = "Sertifikat berlaku s.d. {$validUntilFormatted} (hari ini)";
            } else {
                $certificateStatusLine = "Sertifikat berlaku s.d. {$validUntilFormatted} ({$daysDiff} hari lagi)";
            }
        }

        $updatedAt = $equipment->updated_at;
        $updatedAtFormatted = $updatedAt?->isToday()
            ? 'hari ini'
            : ($updatedAt?->translatedFormat('d F Y') ?? '-');

        $brandModel = trim(($equipment->brand ?? '').' '.($equipment->model ?? ''));

        return response()->view('public.equipment-status', [
            'inventoryCode' => $equipment->inventory_code,
            'equipmentName' => $equipment->name,
            'equipmentTypeName' => $equipment->equipmentType?->name ?? '-',
            'brandModel' => $brandModel !== '' ? $brandModel : '-',
            'roomName' => $equipment->room?->name ?? '-',
            'roomLocation' => collect([$equipment->room?->building, $equipment->room?->floor])->filter()->implode(' • '),
            'statusLabel' => Str::upper($status->getLabel()),
            'statusValue' => $status->value,
            'statusColors' => $statusBadgeColors,
            'isDangerousToUse' => $status->isDangerousToUse(),
            'validUntilFormatted' => $validUntilFormatted,
            'certificateStatusLine' => $certificateStatusLine,
            'updatedAtFormatted' => $updatedAtFormatted,
            'reportUrl' => $equipment->is_active
                ? route('public.equipment.report', ['qr_token' => $equipment->qr_token])
                : null,
        ]);
    }

    public function report(string $qr_token): RedirectResponse|Response
    {
        if (! Str::isUuid($qr_token)) {
            return response()->view('public.equipment-not-found', [], 404);
        }

        $equipment = Equipment::query()
            ->with('room')
            ->where('qr_token', $qr_token)
            ->first();

        if (! $equipment) {
            return response()->view('public.equipment-not-found', [], 404);
        }

        if (! Auth::check()) {
            redirect()->setIntendedUrl(route('public.equipment.report', ['qr_token' => $qr_token]));

            return redirect()->to(Filament::getLoginUrl() ?? url('/admin/login'));
        }

        $user = Auth::user();
        $backUrl = route('public.equipment.show', ['qr_token' => $qr_token]);

        if (! $equipment->is_active) {
            return response()->view('public.equipment-report-denied', [
                'message' => 'Alat ini sudah berstatus tidak aktif sehingga tidak dapat dilaporkan kerusakannya.',
                'backUrl' => $backUrl,
            ], 403);
        }

        if (! app(DamageReportService::class)->canReportEquipment($user, $equipment)) {
            $isRoomHead = $user->hasRole(UserRole::KepalaRuangan->value);

            $message = $isRoomHead
                ? sprintf(
                    'Alat %s (%s) berada di %s. Sebagai Kepala Ruangan, Anda hanya dapat melaporkan kerusakan alat di ruangan Anda sendiri.',
                    $equipment->name,
                    $equipment->inventory_code,
                    $equipment->room?->name ?? 'ruangan lain',
                )
                : 'Akun Anda tidak memiliki izin untuk membuat laporan kerusakan. Silakan hubungi IPSRS atau kepala ruangan terkait.';

            return response()->view('public.equipment-report-denied', [
                'message' => $message,
                'backUrl' => $backUrl,
            ], 403);
        }

        return redirect()->to(DamageReportResource::getUrl('create', [
            'equipment_id' => $equipment->id,
        ]));
    }
}
