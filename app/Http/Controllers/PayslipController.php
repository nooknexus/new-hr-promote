<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class PayslipController extends Controller
{

    public function download($id)
    {
        $cid = Auth::user()->pid;
        $payslipData = $this->fetchPayslipData($cid, $id);

        abort_unless($payslipData, 404);

        $pdf = \App\Services\PayslipPdfService::generatePdf($payslipData, $cid);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * Display a printable view of the payslip
     */
    public function printView($id)
    {
        // Get user's personal ID (CID)
        $cid = Auth::user()->pid;
        
        // Call the method to get payslip data
        $payslipData = $this->fetchPayslipData($cid, $id);
        
        if (!$payslipData) {
            return response()->view('errors.custom', [
                'message' => 'ไม่พบข้อมูลใบรับรองการจ่ายค่าตอบแทน'
            ], 404);
        }
        
        // Return a view designed specifically for printing
        return view('payslip.print-view', [
            'payslip' => $payslipData,
            'user' => Auth::user()
        ]);
    }

    /**
     * Fetch payslip data - mimics the functionality in the Livewire component
     */
    protected function fetchPayslipData($cid, $id)
    {
        try {
            Log::info('Fetching payslip data for print view', ['id' => $id, 'cid' => $cid]);
            
            // Fetch detailed payslip data from database
            $detailedData = DB::select("
                SELECT
                    u.no_position as 'position_number',
                    s.ministry as 'ministry',
                    s.depart as 'department',
                    s.bankName as 'bank_name',
                    s.bankNo as 'bank_account',
                    p.type_name as 'employee_type',
                    p.office_name as 'office',
                    
                    s.yearNO, s.monthNO,
                    s.incomeSalaryAmount, s.incomeSalaryBackPayAmount, s.incomeManagerMoneyAmount,
                    s.incomeManagerMoneyBackPayAmount, s.incomePositionKingAmount, s.incomeWarAmount,
                    s.incomeCourseFeeAmount, s.incomeBreakLawAmount, s.incomeSpecialPlaceAmount,
                    s.incomeSouthAmount, s.incomePositionMoneyAmount, s.incomePositionMoneyBackPayAmount,
                    s.incomeLv8Amount, s.incomeLv8BackPayAmount, s.incomeLv1to7Amount,
                    s.incomeSpecialHightAmount, s.incomeHouseRentAmount, s.incomeChildFeeAmount,
                    s.incomeChildEducationFeeAmount, s.incomeRewardAmount,
                    s.incomeAdd1Name, s.incomeAdd1Amount, s.incomeAdd2Name, s.incomeAdd2Amount,
                    s.incomeAdd3Name, s.incomeAdd3Amount, s.incomeAdd4Name, s.incomeAdd4Amount,
                    s.incomeOtherAmount,
                    
                    s.outcomeTaxAmount, s.outcomeHomeLoanAmount, s.outcomeCoopAmount,
                    s.outcomeEduLoanAmount, s.outcomeGPFAmount, s.outcomeGPFAddAmount,
                    s.outcomeGHBankAmount, s.outcomeGSBankAmount, s.outcomeUniverLoanAmount,
                    s.outcomeTorPorLoanAmount, s.outcomeCivilPenaltyAmount, s.outcomeRestoreAmount,
                    s.outcomeUtilitieAmount, s.outcomeClubAmount, s.outcomeTeacherDeadAmount,
                    s.outcomeCoopDeadAmount, s.outcomeAdd1Name, s.outcomeAdd1Amount, s.outcomeAdd2Name, 
                    s.outcomeAdd2Amount, s.outcomeAdd3Name, s.outcomeAdd3Amount, s.outcomeAdd4Name, 
                    s.outcomeAdd4Amount, s.outcomeAdd5Name, s.outcomeAdd5Amount, s.outcomeAdd6Name, 
                    s.outcomeAdd6Amount, s.outcomeAdd7Name, s.outcomeAdd7Amount, s.outcomeAdd8Name, 
                    s.outcomeAdd8Amount, s.outcomeOtherAmount,
                    
                    s.summaryIncomeAmount, s.summaryOutcomeAmount, s.summaryTotalAmount, s.verifyDate
                FROM
                    data_salary s
                LEFT JOIN person p ON p.cid = s.cid
                LEFT JOIN users u ON u.pid = s.cid
                WHERE
                    s.cid = ? AND CONCAT(s.yearNO, s.monthNO) = ?
            ", [$cid, $id]);
            
            // Check if data was found
            if (empty($detailedData)) {
                Log::warning('No detailed payslip data found', ['id' => $id, 'cid' => $cid]);
                return null;
            }
            
            // Get the first (and only) result
            $result = $detailedData[0];
            
            // Thai month names
            $thaiMonths = [
                '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม',
                '04' => 'เมษายน', '05' => 'พฤษภาคม', '06' => 'มิถุนายน',
                '07' => 'กรกฎาคม', '08' => 'สิงหาคม', '09' => 'กันยายน',
                '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
            ];
            
            // Create month display text
            $monthDisplay = $thaiMonths[$result->monthNO] . ' ' . $result->yearNO;
            
            // Add person info to the payslip data
            $personInfo = [
                'position_number' => $result->position_number ?? '',
                'ministry' => $result->ministry ?? '',
                'department' => $result->department ?? '',
                'bank_name' => $result->bank_name ?? '',
                'bank_account' => $result->bank_account ?? '',
                'employee_type' => $result->employee_type ?? '',
                'office' => $result->office ?? '',
            ];
            
            // Process income details
            $incomeDetails = [];
            
            // Standard income items - only add if they have values
            $incomeFields = [
                'incomeSalaryAmount' => 'เงินเดือน',
                'incomeSalaryBackPayAmount' => 'เงินเดือน ตกเบิก',
                'incomeManagerMoneyAmount' => 'เงินตำแหน่ง',
                'incomeManagerMoneyBackPayAmount' => 'เงินตำแหน่ง ตกเบิก',
                'incomePositionKingAmount' => 'เงินเพิ่มมีเหตุพิเศษ',
                'incomeWarAmount' => 'เงินเพิ่มพิเศษสำหรับการสู้รบ',
                'incomeCourseFeeAmount' => 'เงินเพิ่มพิเศษรายเดือนค่าวิชา',
                'incomeBreakLawAmount' => 'เงินเพิ่มปราบปรามผู้กระทำผิด',
                'incomeSpecialPlaceAmount' => 'เงินสวัสดิการพื้นที่พิเศษ',
                'incomeSouthAmount' => 'เงินค่าตอบแทนชายแดนใต้',
                'incomePositionMoneyAmount' => 'เงินค่าตอบแทนประจำตำแหน่ง',
                'incomePositionMoneyBackPayAmount' => 'เงินค่าตอบแทนประจำตำแหน่ง ตกเบิก',
                'incomeLv8Amount' => 'เงินค่าตอบแทนสำหรับข้าราชการระดับ 8',
                'incomeLv8BackPayAmount' => 'เงินค่าตอบแทนสำหรับข้าราชการระดับ 8 ตกเบิก',
                'incomeLv1to7Amount' => 'เงินค่าตอบแทนสำหรับข้าราชการระดับ 1-7/ตกเบิก',
                'incomeSpecialHightAmount' => 'เงินตอบแทนพิเศษผู้ได้รับเงินเดือนถึงขั้นสูง/ตกเบิก',
                'incomeHouseRentAmount' => 'เงินสวัสดิการค่าเช่าบ้าน/ตกเบิก',
                'incomeChildFeeAmount' => 'เงินช่วยเหลือบุตร/ตกเบิก',
                'incomeChildEducationFeeAmount' => 'เงินช่วยเหลือค่าเรียนบุตร/ตกเบิก',
                'incomeRewardAmount' => 'เงินรางวัล',
                'incomeOtherAmount' => 'เงินเพิ่มอื่น ๆ'
            ];
            
            foreach ($incomeFields as $field => $label) {
                if (isset($result->$field) && $result->$field > 0) {
                    $incomeDetails[] = [
                        'name' => $label,
                        'amount' => floatval($result->$field)
                    ];
                }
            }
            
            // Dynamic income fields (additional income items)
            for ($i = 1; $i <= 4; $i++) {
                $nameField = "incomeAdd{$i}Name";
                $amountField = "incomeAdd{$i}Amount";
                
                if (isset($result->$nameField) && !empty($result->$nameField) && isset($result->$amountField) && $result->$amountField > 0) {
                    $incomeDetails[] = [
                        'name' => $result->$nameField,
                        'amount' => floatval($result->$amountField)
                    ];
                }
            }
            
            // Process expense details
            $expenseDetails = [];
            
            // Standard expense items - only add if they have values
            $expenseFields = [
                'outcomeTaxAmount' => 'ภาษี/ตกเบิก',
                'outcomeHomeLoanAmount' => 'เงินกู้เพื่อที่อยู่อาศัย',
                'outcomeCoopAmount' => 'ค่าหุ้น-เงินกู้สหกรณ์',
                'outcomeEduLoanAmount' => 'เงินกู้เพื่อการศึกษา',
                'outcomeGPFAmount' => 'กบข.',
                'outcomeGPFAddAmount' => 'กบข. ออมเพิ่ม',
                'outcomeGHBankAmount' => 'เงินกู้(ธอส.)',
                'outcomeGSBankAmount' => 'เงินกู้(ธ.ออมสิน)',
                'outcomeUniverLoanAmount' => 'เงินกู้(ธ.ไทยพานิชย์)',
                'outcomeTorPorLoanAmount' => 'เงินกู้',
                'outcomeCivilPenaltyAmount' => 'ชดใช้ทางแพ่ง',
                'outcomeRestoreAmount' => 'เงินเรียกคืน',
                'outcomeUtilitieAmount' => 'ค่าสาธารณูปโภค',
                'outcomeClubAmount' => 'เงินสวัสดิการสโมสร',
                'outcomeTeacherDeadAmount' => 'ค่าฌาปนกิจ',
                'outcomeCoopDeadAmount' => 'ค่าฌาปนกิจ(สหกรณ์)',
                'outcomeOtherAmount' => 'เงินลด/หักอื่นๆ'
            ];
            
            foreach ($expenseFields as $field => $label) {
                if (isset($result->$field) && $result->$field > 0) {
                    $expenseDetails[] = [
                        'name' => $label,
                        'amount' => floatval($result->$field)
                    ];
                }
            }
            
            // Dynamic expense fields (additional expense items)
            for ($i = 1; $i <= 8; $i++) {
                $nameField = "outcomeAdd{$i}Name";
                $amountField = "outcomeAdd{$i}Amount";
                
                if (isset($result->$nameField) && !empty($result->$nameField) && isset($result->$amountField) && $result->$amountField > 0) {
                    $expenseDetails[] = [
                        'name' => $result->$nameField,
                        'amount' => floatval($result->$amountField)
                    ];
                }
            }
            
            // Build the payslip object with all required fields
            $payslipData = [
                'id' => $id,
                'month' => $monthDisplay,
                'salary' => floatval($result->incomeSalaryAmount ?? 0),
                'total_income' => floatval($result->summaryIncomeAmount ?? 0),
                'total_outcome' => floatval($result->summaryOutcomeAmount ?? 0),
                'net' => floatval($result->summaryTotalAmount ?? 0),
                'income_details' => $incomeDetails,
                'expense_details' => $expenseDetails,
                'verify_date' => $result->verifyDate,
                'person_info' => $personInfo,
            ];
            
            Log::info('Payslip data prepared successfully for print view', [
                'id' => $id,
                'income_count' => count($incomeDetails),
                'expense_count' => count($expenseDetails)
            ]);
            
            return $payslipData;
            
        } catch (\Exception $e) {
            Log::error('Error preparing payslip data for print view', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $id,
                'cid' => $cid
            ]);
            return null;
        }
    }

   
   
}
