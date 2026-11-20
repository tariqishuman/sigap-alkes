<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use Illuminate\Http\Response;
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
        ]);
    }
}
