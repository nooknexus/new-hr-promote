<?php

namespace App\Livewire\Profile;

use App\Models\DataSalary;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Payslip extends Component
{
    #[Layout('components.layouts.app')]
    
    public $selectedYear;
    public $years = [];
    public $payslips = [];
    public $selectedPayslip = null;

    // Add the property to register Livewire listeners
    protected $listeners = [
        'generate-test-pdf' => 'generateTestPDF'
    ];

    public function mount()
    {
        // Get user's personal ID (CID)
        $cid = Auth::user()->pid;
        
        // Initialize years array
        $this->years = [];
        
        if (!empty($cid)) {
            try {
                // Query database for years that have records for this user
                $yearRecords = DB::select("
                    SELECT DISTINCT yearNO 
                    FROM data_salary 
                    WHERE cid = ? 
                    ORDER BY yearNO DESC
                ", [$cid]);
                
                // Convert BE years to CE years and add to array
                foreach ($yearRecords as $record) {
                    $ceYear = $this->convertToCommonEra($record->yearNO);
                    $this->years[] = $ceYear;
                }
                
                Log::info('Years with records', ['years' => $this->years]);
            } catch (\Exception $e) {
                Log::error('Error fetching years', ['error' => $e->getMessage()]);
            }
        }

        // Without any payslip the page explains why instead of offering an empty year.
        if (empty($this->years)) {
            $this->selectedYear = null;

            return;
        }

        // Set default selected year to the most recent year
        $this->selectedYear = $this->years[0];
        
        // Load payslips for the selected year
        $this->loadPayslips();
    }
    
    public function selectYear($year)
    {
        $this->selectedYear = $year;
        $this->loadPayslips();
    }
    
    public function loadPayslips()
    {
        $this->payslips = [];
        
        try {
            // Get user's personal ID (CID)
            $cid = Auth::user()->pid;
            
            if (empty($cid)) {
                Log::warning('User has no pid value in their profile');
                return;
            }
            
            // Thai month names
            $thaiMonths = [
                '01' => 'มกราคม',
                '02' => 'กุมภาพันธ์',
                '03' => 'มีนาคม',
                '04' => 'เมษายน',
                '05' => 'พฤษภาคม',
                '06' => 'มิถุนายน',
                '07' => 'กรกฎาคม',
                '08' => 'สิงหาคม',
                '09' => 'กันยายน',
                '10' => 'ตุลาคม',
                '11' => 'พฤศจิกายน',
                '12' => 'ธันวาคม'
            ];
            
            // Convert selected year to BE (พ.ศ.) for database query
            $beYear = $this->convertToBuddhistEra($this->selectedYear);
            
            Log::info('Fetching salary data', [
                'cid' => $cid,
                'ce_year' => $this->selectedYear,
                'be_year' => $beYear
            ]);
            
            // Fetch all data for the selected calendar year with direct SQL
            $salaryData = DB::select("
                SELECT yearNO, monthNO, incomeSalaryAmount, summaryIncomeAmount, 
                       summaryOutcomeAmount, summaryTotalAmount 
                FROM data_salary 
                WHERE cid = ? AND yearNO = ? 
                ORDER BY monthNO DESC
            ", [$cid, $beYear]);
            
            Log::info('Query results', ['count' => count($salaryData)]);
            
            // Normalise months ("9" -> "09") so ids, labels and ordering are consistent.
            usort($salaryData, fn ($a, $b) => strcmp(DataSalary::normalizeMonth($b->monthNO), DataSalary::normalizeMonth($a->monthNO)));

            // Process the data
            foreach ($salaryData as $salary) {
                $month = DataSalary::normalizeMonth($salary->monthNO);
                $this->payslips[] = [
                    'id' => $salary->yearNO . $month,
                    'month' => ($thaiMonths[$month] ?? $month) . ' ' . $salary->yearNO, // Already BE year
                    'salary' => floatval($salary->incomeSalaryAmount ?? 0),
                    'total_income' => floatval($salary->summaryIncomeAmount ?? 0),
                    'total_outcome' => floatval($salary->summaryOutcomeAmount ?? 0),
                    'net' => floatval($salary->summaryTotalAmount ?? 0),
                ];
            }
        } catch (\Exception $e) {
            Log::error('Error loading payslip data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    public function viewPayslipDetail($id)
    {
        // Find the selected payslip from the list
        $payslip = collect($this->payslips)->firstWhere('id', $id);
        
        if (!$payslip) {
            Log::warning('Payslip not found', ['id' => $id]);
            return;
        }
        
        try {
            // Get user's personal ID (CID)
            $cid = Auth::user()->pid;
            
            // Fetch detailed payslip data from database
            $detailedData = DB::select("
                SELECT
                    -- รายรับ
                    incomeSalaryAmount, incomeSalaryBackPayAmount, incomeManagerMoneyAmount,
                    incomeManagerMoneyBackPayAmount, incomePositionKingAmount, incomeWarAmount,
                    incomeCourseFeeAmount, incomeBreakLawAmount, incomeSpecialPlaceAmount,
                    incomeSouthAmount, incomePositionMoneyAmount, incomePositionMoneyBackPayAmount,
                    incomeLv8Amount, incomeLv8BackPayAmount, incomeLv1to7Amount,
                    incomeSpecialHightAmount, incomeHouseRentAmount, incomeChildFeeAmount,
                    incomeChildEducationFeeAmount, incomeRewardAmount,
                    incomeAdd1Name, incomeAdd1Amount, incomeAdd2Name, incomeAdd2Amount,
                    incomeAdd3Name, incomeAdd3Amount, incomeAdd4Name, incomeAdd4Amount,
                    incomeOtherAmount,
                    
                    -- รายจ่าย
                    outcomeTaxAmount, outcomeHomeLoanAmount, outcomeCoopAmount,
                    outcomeEduLoanAmount, outcomeGPFAmount, outcomeGPFAddAmount,
                    outcomeGHBankAmount, outcomeGSBankAmount, outcomeUniverLoanAmount,
                    outcomeTorPorLoanAmount, outcomeCivilPenaltyAmount, outcomeRestoreAmount,
                    outcomeUtilitieAmount, outcomeClubAmount, outcomeTeacherDeadAmount,
                    outcomeCoopDeadAmount, outcomeAdd1Name, outcomeAdd1Amount, outcomeAdd2Name, 
                    outcomeAdd2Amount, outcomeAdd3Name, outcomeAdd3Amount, outcomeAdd4Name, 
                    outcomeAdd4Amount, outcomeAdd5Name, outcomeAdd5Amount, outcomeAdd6Name, 
                    outcomeAdd6Amount, outcomeAdd7Name, outcomeAdd7Amount, outcomeAdd8Name, 
                    outcomeAdd8Amount, outcomeOtherAmount,
                    
                    -- สรุป
                    summaryIncomeAmount, summaryOutcomeAmount, summaryTotalAmount, verifyDate
                FROM
                    data_salary
                WHERE
                    cid = ? AND yearNO = ? AND monthNO IN (?, ?)
            ", [$cid, ...DataSalary::periodBindings((string) $id)]);
            
            // Check if data was found
            if (empty($detailedData)) {
                Log::warning('Detailed payslip data not found', ['id' => $id, 'cid' => $cid]);
                
                // Show error message instead of generating dummy data
                session()->flash('error', 'ไม่พบข้อมูลรายละเอียดใบเงินเดือน');
                return;
            }
            
            // Get the first (and only) result
            $result = $detailedData[0];
            
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
                if (!empty($result->$field)) {
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
                
                if (!empty($result->$nameField)) {
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
                if (!empty($result->$field)) {
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
                
                if (!empty($result->$nameField)) {
                    $expenseDetails[] = [
                        'name' => $result->$nameField,
                        'amount' => floatval($result->$amountField)
                    ];
                }
            }
            
            // Create a new payslip object with details
            $this->selectedPayslip = array_merge($payslip, [
                'income_details' => $incomeDetails,
                'expense_details' => $expenseDetails,
                'verify_date' => $result->verifyDate
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error fetching detailed payslip data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Simply notify the user instead of generating dummy data
            session()->flash('error', 'ไม่สามารถดึงข้อมูลรายละเอียดใบเงินเดือนได้');
            return;
        }
        
        // Dispatch event to open modal
        $this->dispatch('show-payslip-detail');
    }
    
    public function downloadPayslip($id)
    {
        // Log the start of the operation
        Log::info('Starting payslip download preparation', ['id' => $id]);
        
        try {
            // Get user's personal ID (CID)
            $cid = Auth::user()->pid;
            
            if (empty($cid)) {
                Log::warning('No CID found for user', ['user_id' => Auth::id()]);
                $this->dispatch('pdf-generation-error', 'ไม่พบรหัสประจำตัวประชาชนของคุณ');
                return;
            }
            
            // Fetch detailed payslip data
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
                    s.cid = ? AND s.yearNO = ? AND s.monthNO IN (?, ?)
            ", [$cid, ...DataSalary::periodBindings((string) $id)]);
            
            // Check if data was found
            if (empty($detailedData)) {
                Log::warning('No detailed payslip data found', ['id' => $id, 'cid' => $cid]);
                $this->dispatch('pdf-generation-error', 'ไม่พบข้อมูลรายละเอียดใบเงินเดือน');
                return;
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
            $month = DataSalary::normalizeMonth($result->monthNO);
            $monthDisplay = ($thaiMonths[$month] ?? $month) . ' ' . $result->yearNO;
            
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
            $this->selectedPayslip = [
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
            
            Log::info('Payslip data prepared successfully', [
                'id' => $id,
                'income_count' => count($incomeDetails),
                'expense_count' => count($expenseDetails)
            ]);
            
            // Dispatch event to open the PDF preview modal
            $this->dispatch('show-pdf-preview');
            
        } catch (\Exception $e) {
            Log::error('Error preparing payslip for download', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->dispatch('pdf-generation-error', 'ไม่สามารถดึงข้อมูลสำหรับดาวน์โหลดได้: ' . $e->getMessage());
        }
    }

    
    #[Title('สลิปเงินเดือน')]
    public function render()
    {
        return view('livewire.profile.payslip');
    }

    /**
     * Convert Common Era (CE) year to Buddhist Era (BE) year
     */
    private function convertToBuddhistEra($year)
    {
        return (string)($year + 543);
    }

    private function convertToCommonEra($year)
    {
        return (int)$year - 543;
    }
}
