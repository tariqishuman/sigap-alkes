<?php

namespace App\Services;

use App\Models\Equipment;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QrLabelService
{
    public function generateQrDataUri(Equipment $equipment): string
    {
        $url = app(SettingsService::class)->publicEquipmentUrl($equipment->qr_token);

        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'eccLevel' => EccLevel::M,
            'outputBase64' => true,
            'addQuietzone' => true,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($options))->render($url);
    }

    /**
     * @param  Collection<int, Equipment>  $equipmentList
     */
    public function generateLabelsPdf(Collection $equipmentList): \Barryvdh\DomPDF\PDF
    {
        $settings = app(SettingsService::class);

        $items = $equipmentList->map(function (Equipment $equipment) use ($settings): array {
            $equipment->loadMissing(['room', 'equipmentType']);

            return [
                'equipment' => $equipment,
                'qr_data_uri' => $this->generateQrDataUri($equipment),
                'public_url' => $settings->publicEquipmentUrl($equipment->qr_token),
            ];
        });

        return Pdf::loadView('pdf.equipment-qr-labels', [
            'items' => $items,
            'appName' => $settings->appName(),
            'hospitalName' => $settings->hospitalName(),
            'labelFooterText' => (string) $settings->get('qr.label_footer_text', 'Scan QR untuk cek status alat'),
        ])->setPaper('a4', 'portrait');
    }

    /**
     * @param  Collection<int, Equipment>  $equipmentList
     */
    public function downloadLabelsPdf(Collection $equipmentList, string $filename = 'label-qr-alkes.pdf'): StreamedResponse
    {
        $pdfOutput = $this->generateLabelsPdf($equipmentList)->output();

        return response()->streamDownload(
            function () use ($pdfOutput): void {
                echo $pdfOutput;
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
