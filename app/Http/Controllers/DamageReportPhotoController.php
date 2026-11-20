<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Models\DamageReportNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DamageReportPhotoController extends Controller
{
    public function show(Request $request, DamageReport $damageReport, int $index): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user && $user->can('view', $damageReport), 403);

        $photos = $damageReport->photos ?? [];

        if ($noteId = $request->integer('note')) {
            $note = DamageReportNote::query()
                ->where('damage_report_id', $damageReport->id)
                ->whereKey($noteId)
                ->firstOrFail();

            $photos = $note->photos ?? [];
        }

        $path = $photos[$index] ?? null;

        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
