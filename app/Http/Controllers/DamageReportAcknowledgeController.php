<?php

namespace App\Http\Controllers;

use App\Filament\Resources\DamageReports\DamageReportResource;
use App\Models\DamageReport;
use App\Models\User;
use App\Services\DamageReportService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class DamageReportAcknowledgeController extends Controller
{
    public function confirm(Request $request, DamageReport $damageReport, User $user): Response
    {
        $actor = $request->user();

        abort_unless(
            $actor
            && (int) $actor->id === (int) $user->id
            && app(DamageReportService::class)->canManageReportVerification($actor),
            403,
        );

        $damageReport->loadMissing(['equipment.room', 'reportedByUser', 'acknowledgedByUser']);

        $storeUrl = URL::temporarySignedRoute(
            'damage-reports.acknowledge.store',
            now()->addMinutes(30),
            ['damageReport' => $damageReport->id, 'user' => $actor->id],
        );

        return response()->view('filament.damage-reports.acknowledge-confirm', [
            'report' => $damageReport,
            'storeUrl' => $storeUrl,
            'indexUrl' => DamageReportResource::getUrl('index'),
        ]);
    }

    public function store(Request $request, DamageReport $damageReport, User $user): RedirectResponse
    {
        $actor = $request->user();

        abort_unless(
            $actor
            && (int) $actor->id === (int) $user->id
            && app(DamageReportService::class)->canManageReportVerification($actor),
            403,
        );

        $result = app(DamageReportService::class)->acknowledge($damageReport, $actor);

        if ($result['acknowledged']) {
            Notification::make()
                ->success()
                ->title('Anda tercatat menangani laporan ini')
                ->body("Laporan #{$damageReport->id} ditandai sedang ditangani oleh {$actor->name}.")
                ->send();
        } else {
            $owner = $result['report']->acknowledgedByUser?->name ?? 'teknisi lain';
            Notification::make()
                ->info()
                ->title("Sudah ditangani oleh {$owner}")
                ->body("Laporan #{$damageReport->id} sudah lebih dulu diambil oleh {$owner}.")
                ->send();
        }

        return redirect()->to(DamageReportResource::getUrl('index'));
    }
}
