<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\Equipment;
use App\Models\User;
use App\Services\PreventiveMaintenanceService;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QrPreventiveMaintenanceController extends Controller
{
    public function show(string $qr_token): RedirectResponse|Response
    {
        $resolved = $this->resolveEquipmentAndGuard($qr_token);
        if ($resolved instanceof RedirectResponse || $resolved instanceof Response) {
            return $resolved;
        }

        $equipment = $resolved;
        $checklistItems = $this->checklistItemsFor($equipment);

        return response()->view('public.pm-mobile-form', [
            'equipment' => $equipment,
            'checklistItems' => $checklistItems,
            'submitUrl' => route('public.equipment.pm.store', ['qr_token' => $equipment->qr_token]),
            'backUrl' => route('public.equipment.show', ['qr_token' => $equipment->qr_token]),
        ]);
    }

    public function store(Request $request, string $qr_token): RedirectResponse|Response
    {
        $resolved = $this->resolveEquipmentAndGuard($qr_token);
        if ($resolved instanceof RedirectResponse || $resolved instanceof Response) {
            return $resolved;
        }

        $equipment = $resolved;
        $user = $request->user();

        $request->validate([
            'checklist' => ['required', 'array', 'min:1'],
            'checklist.*.item' => ['required', 'string', 'max:255'],
            'checklist.*.status' => ['nullable', 'in:good,needs_attention'],
            'checklist.*.note' => ['nullable', 'string', 'max:500'],
            'checklist.*.photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:3072'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'checklist.*.photo.mimes' => 'Foto harus berformat JPG atau PNG.',
            'checklist.*.photo.max' => 'Ukuran foto maksimal 3 MB.',
        ]);

        $rows = [];
        foreach ($request->input('checklist', []) as $idx => $row) {
            $photoPath = null;
            if ($request->hasFile("checklist.{$idx}.photo")) {
                $photoPath = $request->file("checklist.{$idx}.photo")
                    ->store('pm-photos', 'local');
            }

            $rows[] = [
                'item' => $row['item'] ?? '',
                'status' => $row['status'] ?? null,
                'note' => $row['note'] ?? null,
                'photo' => $photoPath,
            ];
        }

        try {
            $pm = app(PreventiveMaintenanceService::class)->record([
                'equipment_id' => $equipment->id,
                'performed_at' => today()->toDateString(),
                'checklist_results' => $rows,
                'notes' => $request->input('notes'),
                'recorded_via' => 'qr_scan',
            ], $user);
        } catch (ValidationException $e) {
            return redirect()
                ->route('public.equipment.pm.form', ['qr_token' => $equipment->qr_token])
                ->withErrors($e->errors())
                ->withInput($request->except('checklist.*.photo'));
        }

        $damageReportUrl = $pm->hasUnhealthyItems() && DamageReportResource::canCreate()
            ? DamageReportResource::getUrl('create', [
                'equipment_id' => $equipment->id,
                'complaint' => $pm->unhealthyItemsSummary(),
            ])
            : null;

        return response()->view('public.pm-mobile-success', [
            'equipment' => $equipment,
            'pm' => $pm,
            'damageReportUrl' => $damageReportUrl,
            'backUrl' => route('public.equipment.show', ['qr_token' => $equipment->qr_token]),
        ]);
    }

    private function resolveEquipmentAndGuard(string $qr_token): Equipment|RedirectResponse|Response
    {
        if (! Str::isUuid($qr_token)) {
            return response()->view('public.equipment-not-found', [], 404);
        }

        $equipment = Equipment::query()
            ->with(['room', 'equipmentType'])
            ->where('qr_token', $qr_token)
            ->first();

        if (! $equipment) {
            return response()->view('public.equipment-not-found', [], 404);
        }

        if (! Auth::check()) {
            redirect()->setIntendedUrl(route('public.equipment.pm.form', ['qr_token' => $qr_token]));

            return redirect()->to(Filament::getLoginUrl() ?? url('/admin/login'));
        }

        /** @var User $user */
        $user = Auth::user();
        $backUrl = route('public.equipment.show', ['qr_token' => $qr_token]);

        if (! $this->canRecordPm($user)) {
            return response()->view('public.equipment-report-denied', [
                'heading' => 'Tidak dapat mencatat pemeliharaan preventif',
                'message' => 'Hanya Teknisi Elektromedis atau Admin IPSRS yang memiliki izin untuk mencatat pemeliharaan preventif (PM) alat kesehatan.',
                'backUrl' => $backUrl,
            ], 403);
        }

        if (! $equipment->is_active) {
            return response()->view('public.equipment-report-denied', [
                'heading' => 'Tidak dapat mencatat pemeliharaan preventif',
                'message' => 'Alat ini sudah tidak aktif sehingga tidak dapat dicatat pemeliharaannya.',
                'backUrl' => $backUrl,
            ], 403);
        }

        if (! $equipment->equipmentType?->pm_interval_months) {
            return response()->view('public.equipment-report-denied', [
                'heading' => 'Alat tidak memiliki jadwal pemeliharaan',
                'message' => 'Jenis alat ini tidak memiliki interval pemeliharaan preventif berkala.',
                'backUrl' => $backUrl,
            ], 403);
        }

        return $equipment;
    }

    private function canRecordPm(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::AdminIpsrs->value,
            UserRole::Teknisi->value,
        ]) && $user->can('Create:PreventiveMaintenance');
    }

    /**
     * @return array<int, string>
     */
    private function checklistItemsFor(Equipment $equipment): array
    {
        $items = $equipment->equipmentType?->pm_checklist;
        if (is_array($items)) {
            $filtered = array_values(array_filter($items, fn ($v) => is_string($v) && trim($v) !== ''));
            if ($filtered !== []) {
                return $filtered;
            }
        }

        return [
            'Kebersihan & kondisi fisik',
            'Kabel & konektor',
            'Baterai / catu daya',
            'Alarm berfungsi',
            'Uji fungsi dasar',
        ];
    }
}
