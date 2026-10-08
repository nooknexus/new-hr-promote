<?php

namespace App\Http\Controllers;

use App\Services\SalaryPromotionPdfService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalaryPromotionController extends Controller
{
    /** A user may download only their own, non-archived promotion notice. */
    public function download(int $promotion): Response
    {
        $user = Auth::user();
        $pid = $user?->pid;

        abort_unless($pid, 404);

        $record = DB::table('data_promote')
            ->where('id', $promotion)
            ->where('CID', $pid)
            ->whereNull('deleted_at')
            ->first();

        abort_unless($record, 404);

        $person = DB::table('person')->where('cid', $pid)->first();
        $pdf = SalaryPromotionPdfService::generatePdf(
            SalaryPromotionPdfService::fromPromotion($record, (string) $user->name, $person),
        );

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Content-Length' => (string) strlen($pdf['content']),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
