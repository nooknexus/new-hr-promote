<?php

namespace App\Http\Controllers;

use App\Models\PayslipSignatory;
use App\Services\TaxCertificatePdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaxCertificateController extends Controller
{
    public function download(int $taxYear, string $certificateNo): Response
    {
        $user = Auth::user();
        $pid = $user?->pid;

        abort_unless($pid, 404);

        $record = DB::table('data_tax')
            ->where('CID', $pid)
            ->where('income_year', $taxYear)
            ->where('running_code1', $certificateNo)
            ->first();

        abort_unless($record, 404);

        $signatory = PayslipSignatory::forTaxCertificate($record->date_report ?? null, (int) $record->income_year);
        $certificate = TaxCertificatePdfService::fromTaxRecord($record);
        $certificate['signatureImage'] = $signatory?->signature_image;
        $pdf = TaxCertificatePdfService::generatePdf($certificate);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Content-Length' => (string) strlen($pdf['content']),
            'Content-Transfer-Encoding' => 'binary',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

}
