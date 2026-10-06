<?php

namespace App\Services;

use App\Models\PayslipSignatory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use TCPDF;
use Carbon\Carbon;

class PayslipPdfService
{
    /**
     * Generate PDF file for a payslip
     * 
     * @param array $payslipData Payslip data
     * @param string $cid User's CID/personal ID
     * @return array [content, filename, size]
     */
    public static function generatePdf($payslipData, $cid = null)
    {
        if (!$cid) {
            $cid = Auth::user()->pid ?? time();
        }
        
        // Generate filename using the standard format
        $filename = self::generatePayslipFilename($payslipData['id'], $cid);
        
        Log::info('Creating PDF with PayslipPdfService', ['filename' => $filename]);
        
        // Create new TCPDF instance
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('HR Promote System');
        $pdf->SetAuthor('กระทรวงสาธารณสุข');
        $pdf->SetTitle('ใบรับรองการจ่ายค่าตอบแทน');
        $pdf->SetSubject('ใบรับรองการจ่ายค่าตอบแทน');
        
        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        // This document is deliberately a single A4 sheet. Keep TCPDF from
        // inserting a second page when content approaches the footer area.
        $pdf->SetAutoPageBreak(false, 0);
        
        // Add a page
        $pdf->AddPage();
        
        Log::info('Setting up PDF content');
        
        // Set Thai font
        self::setThaiFont($pdf);
        
        // Add watermark and logos (if needed)
        Log::info('Added watermark');
        Log::info('Added ministry logo');
        
        // Generate PDF content
        self::generatePdfContent($pdf, $payslipData);
        
        Log::info('Generating final PDF file');
        
        // Get PDF content as string
        $pdfContent = $pdf->Output('', 'S');
        $pdfSize = strlen($pdfContent);
        
        Log::info('PDF file created successfully', [
            'filename' => $filename,
            'size' => $pdfSize
        ]);
        
        return [
            'content' => $pdfContent,
            'filename' => $filename,
            'size' => $pdfSize,
        ];
    }
    
    /**
     * Generate standardized PDF filename
     */
    public static function generatePayslipFilename($payslipId, $cid = null)
    {
        // Extract year and month from the ID (format: YYYYMM)
        $year = substr($payslipId, 0, 4);
        $month = substr($payslipId, 4, 2);
        
        // Salary records use Buddhist years, while older callers may still
        // pass a Gregorian period id. Preserve either format in the label.
        $buddhistYear = (int) $year >= 2400 ? (int) $year : (int) $year + 543;
        
        // The filename is only a download label; it must not expose a personal identifier.
        $filename = "payslip_{$buddhistYear}_{$month}.pdf";
        
        Log::info('Generated standardized PDF filename', [
            'filename' => $filename,
            'payslip_id' => $payslipId,
            'year' => $year,
            'month' => $month,
            'buddhist_year' => $buddhistYear,
        ]);
        
        return $filename;
    }
    
    /**
     * Set Thai font for PDF
     */
    protected static function setThaiFont($pdf)
    {
        try {
            // Check if converted font files exist
            $tcpdfFontsPath = base_path('vendor/tecnickcom/tcpdf/fonts');
            $fontPhpFile = $tcpdfFontsPath . '/thsarabunnew.php';
            $fontZFile = $tcpdfFontsPath . '/thsarabunnew.z';

            if (file_exists($fontPhpFile) && file_exists($fontZFile)) {
                $pdf->SetFont('thsarabunnew', '', 14);
                Log::info('Using converted THSarabunNew font');
            } else {
                throw new \Exception('Converted font files not found');
            }
        } catch (\Exception $e) {
            Log::warning('THSarabunNew font failed: ' . $e->getMessage());
            try {
                $pdf->SetFont('freeserif', '', 14);
                Log::info('Using freeserif font as fallback');
            } catch (\Exception $e2) {
                $pdf->SetFont('dejavusans', '', 12);
                Log::info('Using dejavusans font as final fallback');
            }
        }
    }
    
    /**
     * Print a bold "label: " immediately followed by a regular-weight value,
     * so a field label can render bold without hard-coding it into a single
     * string. $totalWidth is the combined width of label + value (0 extends
     * the value to the right margin, matching TCPDF's own Cell(0, ...)).
     */
    protected static function printLabeledField($pdf, $label, $value, $totalWidth, $height, $ln, $fontSize)
    {
        $pdf->SetFont('thsarabunnew', 'B', $fontSize);
        $labelWidth = $pdf->GetStringWidth($label);
        $pdf->Cell($labelWidth, $height, $label, 0, 0);
        $pdf->SetFont('thsarabunnew', '', $fontSize);
        $valueWidth = $totalWidth > 0 ? max($totalWidth - $labelWidth, 0) : 0;
        $pdf->Cell($valueWidth, $height, $value, 0, $ln);
    }

    /**
     * Set the active font to the largest size at or below $baseSize (regular
     * weight) that still fits $text within $maxWidth. Long, one-off strings
     * like a signer's position title can otherwise overflow the fixed-width
     * signature block.
     */
    protected static function fitFontToWidth($pdf, $text, $maxWidth, $baseSize)
    {
        $size = $baseSize;
        $pdf->SetFont('thsarabunnew', '', $size);
        while ($size > 8 && $pdf->GetStringWidth($text) > $maxWidth) {
            $size -= 0.5;
            $pdf->SetFont('thsarabunnew', '', $size);
        }
        return $size;
    }

    /**
     * Generate PDF content with enhanced design
     */
    protected static function generatePdfContent($pdf, $payslipData)
    {
        $user = Auth::user();
        
        // Extract month and year
        $parts = explode(' ', $payslipData['month']);
        $monthName = $parts[0] ?? '';
        $yearBE = $parts[1] ?? '';
        
        // Create watermark with ministry logo (transparent)
        $logoWatermarkPath = public_path('img/head_logo.png');
        if (file_exists($logoWatermarkPath)) {
            $pdf->SetAlpha(0.07);
            $pdf->Image($logoWatermarkPath, 50, 100, 100, 0, '', '', '', false, 300, '', false, false, 0);
            $pdf->SetAlpha(1);
            Log::info('Added watermark');
        }
        
        // Add ministry logo at the top
        $logoPath = public_path('img/logoMOPH.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 95, 15, 20, 0, '', '', '', false, 300, '', false, false, 0);
            Log::info('Added ministry logo');
        }
        
        // Header text
        $pdf->Ln(20);
        $pdf->SetFont('thsarabunnew', 'B', 22);
        $pdf->Cell(0, 10, 'ใบรับรองการจ่ายเงินเดือนและเงินอื่น', 0, 1, 'C');
        $pdf->SetFont('thsarabunnew', 'B', 19);
        $pdf->Cell(0, 10, 'ประจำเดือน ' . $monthName . ' ปี พ.ศ. ' . $yearBE, 0, 1, 'C');

        // Employee information
        $pdf->Ln(5);
        $pdf->SetFont('thsarabunnew', '', 19);

        // The preview uses the account display name, so the downloaded PDF
        // must prefer that value too. Older user records may only have split
        // first/last name fields, hence the fallback.
        $userName = trim((string) ($user->name ?? ''));
        if ($userName === '') {
            $userName = trim(implode(' ', array_filter([
                $user->firstname ?? null,
                $user->lastname ?? null,
            ])));
        }
        $userName = $userName !== '' ? $userName : '-';
        $positionNumber = $payslipData['person_info']['position_number'] ?? '-';
        $employeeType = $payslipData['person_info']['employee_type'] ?? '-';
        $ministry = $payslipData['person_info']['ministry'] ?? '-';
        $department = $payslipData['person_info']['department'] ?? '-';
        $office = $payslipData['person_info']['office'] ?? '-';
        $bankName = $payslipData['person_info']['bank_name'] ?? '-';
        $bankAccount = $payslipData['person_info']['bank_account'] ?? '-';
        
        // Employee info rows in 2 columns. Field labels render bold, values
        // render in the regular weight, while keeping each column's total
        // width identical to before so the second column still lines up.
        self::printLabeledField($pdf, 'ชื่อ-นามสกุล: ', $userName, 95, 8, 0, 19);
        self::printLabeledField($pdf, 'เลขที่ตำแหน่ง: ', $positionNumber, 95, 8, 1, 19);

        self::printLabeledField($pdf, 'ประเภท: ', $employeeType, 95, 8, 0, 19);
        self::printLabeledField($pdf, 'กรม: ', $ministry, 95, 8, 1, 19);

        self::printLabeledField($pdf, 'สำนัก/กอง/ศูนย์: ', $department, 0, 8, 1, 19);
        self::printLabeledField($pdf, 'ส่วน: ', $office, 0, 8, 1, 19);

        self::printLabeledField($pdf, 'โอนเงินเข้า: ', $bankName, 95, 8, 0, 19);
        self::printLabeledField($pdf, 'เลขที่บัญชี: ', $bankAccount, 95, 8, 1, 19);
        
        // Income and Expense section headers
        $pdf->Ln(5);
        $pdf->SetFont('thsarabunnew', 'B', 19);
        $pdf->Cell(90, 10, 'รายรับ', 0, 0);
        $pdf->Cell(90, 10, 'รายจ่าย', 0, 1);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(2);
        
        // Income and Expense details. Reserve the lower part of the page for
        // the summary, signature, and the fixed footer.
        $incomeYStart = $pdf->GetY();
        $maxY = $incomeYStart;
        $detailRowCount = max(count($payslipData['income_details']), count($payslipData['expense_details'])) + 1;
        $detailRowHeight = max(3.8, min(7, 95 / max($detailRowCount, 1)));
        // TCPDF will not render a 14pt line into the compact rows needed by
        // unusually detailed payslips. Reduce only those dense tables so the
        // signature and fixed footer retain their own space on the A4 sheet.
        $detailFontSize = match (true) {
            $detailRowCount > 18 => 12,
            $detailRowCount > 14 => 14,
            default => 16,
        };

        // Print income details in the left column
        $pdf->SetFont('thsarabunnew', '', $detailFontSize);
        foreach ($payslipData['income_details'] as $income) {
            $pdf->Cell(70, $detailRowHeight, $income['name'], 0, 0);
            $pdf->Cell(20, $detailRowHeight, number_format($income['amount'], 2), 0, 1, 'R');
        }

        // Income total
        $pdf->SetFont('thsarabunnew', 'B', $detailRowCount > 14 ? $detailFontSize : 19);
        $totalRowHeight = $detailRowCount > 14 ? $detailRowHeight : 8;
        $pdf->Cell(70, $totalRowHeight, 'รวมรายรับทั้งหมด', 0, 0);
        $pdf->Cell(20, $totalRowHeight, number_format($payslipData['total_income'], 2), 0, 1, 'R');
        
        // Save Y position after income section
        $maxY = max($maxY, $pdf->GetY());
        
        // Reset Y position for expense details
        $pdf->SetY($incomeYStart);
        $pdf->SetX(105);
        
        // Print expense details in the right column
        $pdf->SetFont('thsarabunnew', '', $detailFontSize);
        foreach ($payslipData['expense_details'] as $expense) {
            $pdf->Cell(70, $detailRowHeight, $expense['name'], 0, 0);
            $pdf->Cell(20, $detailRowHeight, number_format($expense['amount'], 2), 0, 1, 'R');
            $pdf->SetX(105);
        }

        // Expense total
        $pdf->SetFont('thsarabunnew', 'B', $detailRowCount > 14 ? $detailFontSize : 19);
        $pdf->Cell(70, $totalRowHeight, 'รวมรายจ่ายทั้งหมด', 0, 0);
        $pdf->Cell(20, $totalRowHeight, number_format($payslipData['total_outcome'], 2), 0, 1, 'R');
        
        // Save maximum Y position
        $maxY = max($maxY, $pdf->GetY());
        
        // Set Y to the maximum position for the net amount
        $pdf->SetY($maxY + 10);
        
        // Net total (centered and underlined)
        $pdf->SetFont('thsarabunnew', 'B', 19);
        $pdf->Cell(0, 10, 'รับสุทธิ: ' . number_format($payslipData['net'], 2) . ' บาท', 0, 1, 'C');
        $pdf->Line(80, $pdf->GetY() - 2, 130, $pdf->GetY() - 2);

        // The signer changes by fiscal year and term of office, so look up
        // who was on record for the fiscal year this pay period falls in.
        $signatory = PayslipSignatory::forPeriodId($payslipData['id']);

        // Add signature section without ever entering the footer zone.
        $pdf->SetY(min($pdf->GetY() + 15, 230));
        $pdf->SetFont('thsarabunnew', '', 16);

        // Keep the signature group right aligned while centering every line
        // within the same 85mm block as the browser preview.
        $signatureBlockStart = 110;
        $signatureBlockWidth = 85;
        $signatureGap = 3;
        $signatureLineStart = $signatureBlockStart + 20;
        $signatureImageStart = $signatureLineStart + 10 + $signatureGap;
        $signatureLabelStart = $signatureImageStart + 12 + $signatureGap;
        $pdf->Cell($signatureLineStart - 15, 7, '', 0, 0);
        $pdf->Cell(10, 7, 'ลงชื่อ', 0, 0);

        // Add signature image: the fiscal year's signatory record when one is
        // on file, otherwise the static placeholder image.
        if ($signatory && $signatory->signature_image) {
            // Supplying a width only makes TCPDF preserve the image's natural
            // proportions instead of flattening the signature into a fixed box.
            $pdf->Image('@' . $signatory->signature_image, $signatureImageStart, $pdf->GetY() - 1, 12, 0, '', '', '', false, 300, '', false, false, 0);
            $pdf->SetX($signatureLabelStart);
        } else {
            $signPath = public_path('img/sign.png');
            if (file_exists($signPath)) {
                $pdf->Image($signPath, $signatureImageStart, $pdf->GetY() - 1, 12, 0, '', '', '', false, 300, '', false, false, 0);
                $pdf->SetX($signatureLabelStart);
            } else {
                $pdf->Cell(15, 7, '________________', 0, 0);
            }
        }
        
        $pdf->Cell($signatureBlockStart + $signatureBlockWidth - $signatureLabelStart, 7, 'ผู้มีหน้าที่จ่ายเงิน', 0, 1);
        
        // Name and position. Each line shrinks from the normal 16pt only as
        // far as needed to stay inside the fixed 85mm signature block, since
        // some position titles are too long to fit at full size.
        $pdf->Ln(5);
        $signatureLines = $signatory
            ? array_filter([$signatory->display_name, $signatory->position_line1, $signatory->position_line2])
            : [
                '(นายมนัสศักต์ มากบุญ)',
                'นักวิชาการสาธารณสุขชำนาญการพิเศษ (ด้านบริการทางวิชาการ)',
                'รองนายแพทย์สาธารณสุขจังหวัดพิษณุโลก (ด้านบริหาร)',
            ];
        foreach ($signatureLines as $signatureLine) {
            self::fitFontToWidth($pdf, $signatureLine, $signatureBlockWidth, 16);
            $pdf->Cell($signatureBlockStart - 15, 7, '', 0, 0);
            $pdf->Cell($signatureBlockWidth, 7, $signatureLine, 0, 1, 'C');
        }
        $pdf->SetFont('thsarabunnew', '', 16);

        // The date under the signature is when the document was verified,
        // not when it happens to be printed/downloaded — those can differ by
        // a lot, since a payslip can be re-downloaded long after signing.
        $now = Carbon::now();
        $thaiMonths = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม',
            4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
            7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
            10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
        ];

        $verifyDate = !empty($payslipData['verify_date']) ? Carbon::parse($payslipData['verify_date']) : $now;
        $verifyDay = $verifyDate->format('j');
        $verifyMonth = $thaiMonths[$verifyDate->month];
        $verifyYear = $verifyDate->year + 543;
        $thaiDate = "({$verifyDay} {$verifyMonth} {$verifyYear})";

        $pdf->Cell($signatureBlockStart - 15, 7, '', 0, 0);
        $pdf->Cell($signatureBlockWidth, 7, $thaiDate, 0, 1, 'C');

        // Add footer — this one is always "now": when the PDF was generated.
        $pdf->SetY(-15);
        $pdf->SetFont('thsarabunnew', '', 13);

        $day = $now->format('j');
        $month = $thaiMonths[$now->month];
        $year = $now->year + 543;
        $hours = $now->format('H');
        $minutes = $now->format('i');
        $timestamp = "{$day} {$month} {$year} เวลา {$hours}:{$minutes}น.";
        
        $pdf->Cell(90, 5, 'วันที่พิมพ์ใบรับรอง: ' . $timestamp, 0, 0, 'L');
        $pdf->Cell(90, 5, 'เอกสารนี้จัดทำโดยระบบอิเล็กทรอนิกส์', 0, 1, 'R');
    }
    
    /**
     * Convert TTF font to TCPDF format
     */
    public static function convertFont()
    {
        try {
            $fontPath = storage_path('fonts/THSarabunNew.ttf');
            
            if (!file_exists($fontPath)) {
                return [
                    'success' => false,
                    'message' => 'Font file not found at: ' . $fontPath
                ];
            }
            
            // Find TCPDF tools and fonts directories
            $tcpdfToolsPath = base_path('vendor/tecnickcom/tcpdf/tools');
            $tcpdfFontsPath = base_path('vendor/tecnickcom/tcpdf/fonts');
            $addFontPath = $tcpdfToolsPath . '/tcpdf_addfont.php';
            
            Log::info('Font conversion paths', [
                'font_path' => $fontPath,
                'tools_path' => $tcpdfToolsPath,
                'fonts_path' => $tcpdfFontsPath,
                'addfont_path' => $addFontPath,
                'tools_exists' => is_dir($tcpdfToolsPath),
                'fonts_exists' => is_dir($tcpdfFontsPath),
                'addfont_exists' => file_exists($addFontPath),
                'fonts_writable' => is_writable($tcpdfFontsPath)
            ]);
            
            if (!file_exists($addFontPath)) {
                return [
                    'success' => false,
                    'message' => 'TCPDF tcpdf_addfont.php not found at: ' . $addFontPath
                ];
            }
            
            if (!is_writable($tcpdfFontsPath)) {
                return [
                    'success' => false,
                    'message' => 'TCPDF fonts directory is not writable: ' . $tcpdfFontsPath
                ];
            }
            
            // Change to tools directory and run tcpdf_addfont command
            $oldCwd = getcwd();
            chdir($tcpdfToolsPath);
            
            // Run tcpdf_addfont.php command
            $command = "php tcpdf_addfont.php -i " . escapeshellarg($fontPath) . " -o " . escapeshellarg($tcpdfFontsPath);
            $output = [];
            $returnVar = 0;
            
            exec($command . ' 2>&1', $output, $returnVar);
            
            // Restore original working directory
            chdir($oldCwd);
            
            // Check for generated files
            $possibleGeneratedFiles = [
                $tcpdfFontsPath . '/thsarabunnew.php',
                $tcpdfFontsPath . '/thsarabunnew.z',
                $tcpdfFontsPath . '/THSarabunNew.php',
                $tcpdfFontsPath . '/THSarabunNew.z',
            ];
            
            $foundFiles = [];
            foreach ($possibleGeneratedFiles as $file) {
                if (file_exists($file)) {
                    $foundFiles[] = $file;
                }
            }
            
            if (empty($foundFiles)) {
                $allFontFiles = array_merge(
                    glob($tcpdfFontsPath . '/*.php'),
                    glob($tcpdfFontsPath . '/*.z')
                );
                
                return [
                    'success' => false,
                    'message' => 'Font conversion failed. No expected files found.',
                    'output' => $output,
                    'return_code' => $returnVar,
                    'all_font_files' => array_map('basename', $allFontFiles),
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Font converted successfully using tcpdf_addfont.php',
                'generated_files' => array_map('basename', $foundFiles),
                'output' => $output,
                'return_code' => $returnVar
            ];
            
        } catch (\Exception $e) {
            Log::error('Font conversion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Font conversion failed: ' . $e->getMessage()
            ];
        }
    }
    
    
}
