<?php

namespace App\Http\Controllers;

use App\Models\Calibration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CalibrationCertificateController extends Controller
{
    public function show(Request $request, Calibration $calibration): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user && $user->can('view', $calibration), 403);
        abort_unless(filled($calibration->certificate_path) && Storage::disk('local')->exists($calibration->certificate_path), 404);

        $filename = ($calibration->certificate_number
            ? preg_replace('/[^A-Za-z0-9._-]/', '_', $calibration->certificate_number)
            : 'sertifikat-'.$calibration->id).'.pdf';

        return Storage::disk('local')->response($calibration->certificate_path, $filename, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
