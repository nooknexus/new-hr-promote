<?php

namespace App\Services;

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
        
        // Convert to Buddhist year (add 543)
        $buddhistYear = (int)$year + 543;
        
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
        $pdf->SetFont('thsarabunnew', 'B', 18);
        $pdf->Cell(0, 10, 'ใบรับรองการจ่ายเงินเดือนและเงินอื่น', 0, 1, 'C');
        $pdf->SetFont('thsarabunnew', 'B', 16);
        $pdf->Cell(0, 10, 'ประจำเดือน ' . $monthName . ' ปี พ.ศ. ' . $yearBE, 0, 1, 'C');
        
        // Employee information
        $pdf->Ln(5);
        $pdf->SetFont('thsarabunnew', '', 14);
        
        $userName = $user->firstname . ' ' . $user->lastname;
        $positionNumber = $payslipData['person_info']['position_number'] ?? '-';
        $employeeType = $payslipData['person_info']['employee_type'] ?? '-';
        $ministry = $payslipData['person_info']['ministry'] ?? '-';
        $department = $payslipData['person_info']['department'] ?? '-';
        $office = $payslipData['person_info']['office'] ?? '-';
        $bankName = $payslipData['person_info']['bank_name'] ?? '-';
        $bankAccount = $payslipData['person_info']['bank_account'] ?? '-';
        
        // Employee info rows in 2 columns
        $pdf->Cell(95, 7, 'ชื่อ-นามสกุล: ' . $userName, 0, 0);
        $pdf->Cell(95, 7, 'เลขที่ตำแหน่ง: ' . $positionNumber, 0, 1);
        
        $pdf->Cell(95, 7, 'ประเภท: ' . $employeeType, 0, 0);
        $pdf->Cell(95, 7, 'กรม: ' . $ministry, 0, 1);
        
        $pdf->Cell(0, 7, 'สำนัก/กอง/ศูนย์: ' . $department, 0, 1);
        $pdf->Cell(0, 7, 'ส่วน: ' . $office, 0, 1);
        
        $pdf->Cell(95, 7, 'โอนเงินเข้า: ' . $bankName, 0, 0);
        $pdf->Cell(95, 7, 'เลขที่บัญชี: ' . $bankAccount, 0, 1);
        
        // Income and Expense section headers
        $pdf->Ln(5);
        $pdf->SetFont('thsarabunnew', 'B', 16);
        $pdf->Cell(90, 10, 'รายรับ', 0, 0);
        $pdf->Cell(90, 10, 'รายจ่าย', 0, 1);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(2);
        
        // Income and Expense details
        $incomeYStart = $pdf->GetY();
        $maxY = $incomeYStart;
        
        // Print income details in the left column
        $pdf->SetFont('thsarabunnew', '', 14);
        foreach ($payslipData['income_details'] as $income) {
            $pdf->Cell(70, 7, $income['name'], 0, 0);
            $pdf->Cell(20, 7, number_format($income['amount'], 2), 0, 1, 'R');
        }
        
        // Income total
        $pdf->SetFont('thsarabunnew', 'B', 14);
        $pdf->Cell(70, 7, 'รวมรายรับทั้งหมด', 0, 0);
        $pdf->Cell(20, 7, number_format($payslipData['total_income'], 2), 0, 1, 'R');
        
        // Save Y position after income section
        $maxY = max($maxY, $pdf->GetY());
        
        // Reset Y position for expense details
        $pdf->SetY($incomeYStart);
        $pdf->SetX(105);
        
        // Print expense details in the right column
        $pdf->SetFont('thsarabunnew', '', 14);
        foreach ($payslipData['expense_details'] as $expense) {
            $pdf->Cell(70, 7, $expense['name'], 0, 0);
            $pdf->Cell(20, 7, number_format($expense['amount'], 2), 0, 1, 'R');
            $pdf->SetX(105);
        }
        
        // Expense total
        $pdf->SetFont('thsarabunnew', 'B', 14);
        $pdf->Cell(70, 7, 'รวมรายจ่ายทั้งหมด', 0, 0);
        $pdf->Cell(20, 7, number_format($payslipData['total_outcome'], 2), 0, 1, 'R');
        
        // Save maximum Y position
        $maxY = max($maxY, $pdf->GetY());
        
        // Set Y to the maximum position for the net amount
        $pdf->SetY($maxY + 10);
        
        // Net total (centered and underlined)
        $pdf->SetFont('thsarabunnew', 'B', 16);
        $pdf->Cell(0, 10, 'รับสุทธิ: ' . number_format($payslipData['net'], 2) . ' บาท', 0, 1, 'C');
        $pdf->Line(80, $pdf->GetY() - 2, 130, $pdf->GetY() - 2);
        
        // Add signature section
        $pdf->Ln(15);
        $pdf->SetFont('thsarabunnew', '', 14);
        
        // Signature line - Right Aligned
        $pdf->Cell(120, 7, '', 0, 0);
        $pdf->Cell(10, 7, 'ลงชื่อ', 0, 0);
        
        // Add signature image if available
        $signPath = public_path('img/sign.png');
        if (file_exists($signPath)) {
            $pdf->Image($signPath, 145, $pdf->GetY() - 2, 15, 8, '', '', '', false, 300, '', false, false, 0);
            $pdf->Cell(15, 7, '', 0, 0);
        } else {
            $pdf->Cell(15, 7, '________________', 0, 0);
        }
        
        $pdf->Cell(15, 7, 'ผู้มีหน้าที่จ่ายเงิน', 0, 1);
        
        // Name and position
        $pdf->Ln(5);
        $pdf->Cell(120, 7, '', 0, 0);
        $pdf->Cell(60, 7, '(นายมนัสศักต์ มากบุญ)', 0, 1, 'C');
        $pdf->Cell(120, 7, '', 0, 0);
        $pdf->Cell(60, 7, 'นักวิชาการสาธารณสุขชำนาญการพิเศษ (ด้านบริการทางวิชาการ)', 0, 1, 'C');
        $pdf->Cell(120, 7, '', 0, 0);
        $pdf->Cell(60, 7, 'รองนายแพทย์สาธารณสุขจังหวัดพิษณุโลก (ด้านบริหาร)', 0, 1, 'C');
        
        // Add date
        $now = Carbon::now();
        $thaiMonths = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 
            4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
            7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน',
            10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
        ];
        
        $day = $now->format('j');
        $month = $thaiMonths[$now->month];
        $year = $now->year + 543;
        $thaiDate = "({$day} {$month} {$year})";
        
        $pdf->Cell(120, 7, '', 0, 0);
        $pdf->Cell(60, 7, $thaiDate, 0, 1, 'C');
        
        // Add footer
        $pdf->SetY(-15);
        $pdf->SetFont('thsarabunnew', '', 10);
        
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
