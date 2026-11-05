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
                'bg' => '#EAF6F0',
                'text' => '#2E9E6B',
                'border' => '#B9E2CE',
            ],
            EquipmentStatus::CalibrationDue => [
                'bg' => '#FDF6E9',
                'text' => '#B87D1E',
                'border' => '#F3D9A4',
            ],
            EquipmentStatus::Overdue, EquipmentStatus::Unfit => [
                'bg' => '#FDECEC',
                'text' => '#D9534F',
                'border' => '#F3B8B6',
            ],
            EquipmentStatus::UnderTesting => [
                'bg' => '#EBF3FE',
                'text' => '#2563EB',
                'border' => '#BFDBFE',
            ],
            EquipmentStatus::UnderRepair => [
                'bg' => '#F3F4F6',
                'text' => '#4B5563',
                'border' => '#D1D5DB',
            ],
        };

        $validUntilFormatted = 'Belum ada sertifikat';
        if ($equipment->latestValidCalibration?->valid_until) {
            $validUntilFormatted = $equipment->latestValidCalibration->valid_until->translatedFormat('d F Y');
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
            'statusColors' => $statusBadgeColors,
            'isDangerousToUse' => $status->isDangerousToUse(),
            'validUntilFormatted' => $validUntilFormatted,
            'updatedAtFormatted' => $updatedAtFormatted,
        ]);
    }
}
