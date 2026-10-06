<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class PayslipManagement extends Component
{
    use WithFileUploads, WithPagination;

    public $fileUpload;
    public $importResult = null;
    public $isImporting = false;

    public $searchYear = '';
    public $searchMonth = '';
    public $search = '';

    public function updatedSearchYear()
    {
        $this->resetPage();
    }

    public function updatedSearchMonth()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function mount()
    {
        // ตั้งค่าเริ่มต้นเป็น ปี และ เดือน ล่าสุดที่มีข้อมูล
        $latest = DB::table('data_salary')
            ->select('yearNO', 'monthNO')
            ->orderBy('yearNO', 'desc')
            ->orderBy('monthNO', 'desc')
            ->first();

        if ($latest) {
            $this->searchYear = $latest->yearNO;
            $this->searchMonth = $latest->monthNO;
        }
    }

    #[Computed]
    public function payslips()
    {
        $query = DB::table('data_salary')
            ->leftJoin('person', 'data_salary.cid', '=', 'person.cid')
            ->select('data_salary.*', 'person.pname', 'person.fname', 'person.lname')
            ->orderBy('data_salary.yearNO', 'desc')
            ->orderBy('data_salary.monthNO', 'desc');

        if ($this->searchYear) {
            $query->where('data_salary.yearNO', $this->searchYear);
        }
        if ($this->searchMonth) {
            $query->where('data_salary.monthNO', str_pad($this->searchMonth, 2, '0', STR_PAD_LEFT));
        }
        if (trim($this->search)) {
            $search = trim($this->search);
            $query->where(function($q) use ($search) {
                $q->where('data_salary.cid', 'like', '%' . $search . '%')
                  ->orWhere('person.fname', 'like', '%' . $search . '%')
                  ->orWhere('person.lname', 'like', '%' . $search . '%')
                  ->orWhereRaw("CONCAT(person.pname, person.fname, ' ', person.lname) LIKE ?", ['%' . str_replace(' ', '%', $search) . '%'])
                  ->orWhereRaw("CONCAT(person.fname, ' ', person.lname) LIKE ?", ['%' . str_replace(' ', '%', $search) . '%']);
            });
        }

        return $query->paginate(20);
    }

        private function formatExcelAmount($amount)
    {
        if ($amount === null || $amount === '') return null;
        $clean = str_replace([',', ' '], '', $amount);
        if (!is_numeric($clean)) return null;
        return number_format((float)$clean, 2, '.', '');
    }

    private function removeLeadingZerosFromNumber($nameString, $numberString)
    {
        if (empty($nameString) || $numberString === '0000000000' || empty($numberString)) {
            return null;
        }

        $zeroLeading = (string)((int)($numberString));
        if (strlen($zeroLeading) <= 2) {
            $zeroLeading = str_pad($zeroLeading, 3, '0', STR_PAD_LEFT);
        }
        $strInt = substr($zeroLeading, 0, -2);
        $strFloat = substr($zeroLeading, -2, 2);

        if ($strInt === '') $strInt = '0';

        return $strInt . '.' . $strFloat;
    }

    private function convertDateFormat($date)
    {
        $date = trim($date);
        if (empty($date)) {
            return null;
        }

        // If it's DDMMYYYY (e.g. 02102567 from TXT)
        if (strlen($date) === 8 && ctype_digit($date)) {
            $varDay = substr($date, 0, 2);
            $varMonth = substr($date, 2, 2);
            $varYear = substr($date, 4, 4);
            $convYear = intval($varYear);
            if ($convYear > 2500) {
                $convYear -= 543;
            }
            return $convYear . '-' . $varMonth . '-' . $varDay;
        }

        // If it's already YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $parts = explode('-', $date);
            if (intval($parts[0]) > 2500) {
                $parts[0] = (string)(intval($parts[0]) - 543);
                return implode('-', $parts);
            }
            return $date;
        }

        // If it's DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $matches)) {
            $day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
            $year = intval($matches[3]);
            if ($year > 2500) {
                $year -= 543;
            }
            return $year . '-' . $month . '-' . $day;
        }

        // If it's numeric (Excel date serial - usually ~ 40000 to 50000)
        if (is_numeric($date)) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

        public function import()
    {
        $this->validate([
            'fileUpload' => 'required|file|mimes:txt,xlsx',
        ]);

        $this->isImporting = true;

        try {
            $extension = strtolower($this->fileUpload->getClientOriginalExtension());
            $success = 0;
            $failed = 0;
            $total = 0;

            DB::beginTransaction();

            if ($extension === 'xlsx') {
                $reader = new Xlsx();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($this->fileUpload->getRealPath());
                $sheetData = $spreadsheet->getActiveSheet()->toArray();

                // Row 1 is header (0-indexed in array is 0)
                // Data starts from index 1
                for ($i = 1; $i < count($sheetData); $i++) {
                    $column = $sheetData[$i];

                    // Check if CID (column 1) is present
                    if (empty($column[1]) || strlen(trim($column[1])) !== 13) {
                        if (!empty($column[1])) $failed++;
                        continue;
                    }

                    $total++;

                    $record = [
                        'pname' => $column[2] ?? null,
                        'fname' => $column[3] ?? null,
                        'lname' => $column[4] ?? null,
                        'ministry' => $column[5] ?? null,
                        'depart' => $column[6] ?? null,
                        'bankName' => $column[9] ?? null,
                        'bankNo' => str_replace('-', '', $column[10] ?? ''),

                        'incomeSalaryAmount' => $this->formatExcelAmount($column[11] ?? ''),
                        'incomeSalaryBackPayAmount' => (!empty($column[12])) ? $this->formatExcelAmount($column[12]) : null,

                        'incomeAdd1Name' => (!empty($column[13])) ? 'ค่าตอบแทน ค.ต.ส.' : null,
                        'incomeAdd1Amount' => (!empty($column[13])) ? $this->formatExcelAmount($column[13]) : null,

                        'incomeOtherAmount' => (!empty($column[14])) ? $this->formatExcelAmount($column[14]) : null,
                        'summaryIncomeAmount' => $this->formatExcelAmount($column[15] ?? ''),

                        'outcomeAdd1Name' => (!empty($column[16])) ? 'ประกันสังคม' : null,
                        'outcomeAdd1Amount' => (!empty($column[16])) ? $this->formatExcelAmount($column[16]) : null,

                        'outcomeTeacherDeadAmount' => (!empty($column[17])) ? $this->formatExcelAmount($column[17]) : null,
                        'outcomeGHBankAmount' => (!empty($column[18])) ? $this->formatExcelAmount($column[18]) : null,

                        'outcomeAdd2Name' => (!empty($column[19])) ? 'เงินกู้(กรุงไทย)' : null,
                        'outcomeAdd2Amount' => (!empty($column[19])) ? $this->formatExcelAmount($column[19]) : null,

                        'outcomeAdd3Name' => (!empty($column[20])) ? 'กยศ.' : null,
                        'outcomeAdd3Amount' => (!empty($column[20])) ? $this->formatExcelAmount($column[20]) : null,

                        'outcomeCoopAmount' => (!empty($column[21])) ? $this->formatExcelAmount($column[21]) : null,

                        'outcomeAdd4Name' => (!empty($column[22])) ? 'เงินกู้(ธ.ออมสิน)' : null,
                        'outcomeAdd4Amount' => (!empty($column[22])) ? $this->formatExcelAmount($column[22]) : null,

                        'outcomeAdd5Name' => (!empty($column[23])) ? 'เงินกู้(ธ.อิสลาม)' : null,
                        'outcomeAdd5Amount' => (!empty($column[23])) ? $this->formatExcelAmount($column[23]) : null,

                        'outcomeAdd6Name' => (!empty($column[24])) ? 'กองทุนสำรองเลี้ยงชีพ' : null,
                        'outcomeAdd6Amount' => (!empty($column[24])) ? $this->formatExcelAmount($column[24]) : null,

                        'outcomeOtherAmount' => (!empty($column[25])) ? $this->formatExcelAmount($column[25]) : null,
                        'summaryOutcomeAmount' => $this->formatExcelAmount($column[26] ?? ''),
                        'summaryTotalAmount' => $this->formatExcelAmount($column[27] ?? ''),

                        'verifyDate' => (!empty($column[28])) ? $this->convertDateFormat($column[28]) : null,
                        'r_update' => now(),
                    ];

                    // Note: table data_salary does not have pname, fname, lname.
                    unset($record['pname']);
                    unset($record['fname']);
                    unset($record['lname']);
                    // If we needed to save pname/fname/lname, we would insert/update person table,
                    // but the original legacy SQL just inserted them into data_salary.
                    // Since data_salary doesn't have these columns, we must unset them.

                    DB::table('data_salary')->updateOrInsert(
                        ['yearNO' => $column[7], 'monthNO' => str_pad($column[8], 2, '0', STR_PAD_LEFT), 'cid' => $column[1]],
                        $record
                    );

                    $success++;
                }

            } else {
                // TXT logic
                $content = file_get_contents($this->fileUpload->getRealPath());

                if (!mb_check_encoding($content, 'UTF-8')) {
                    $content = @iconv('TIS-620', 'UTF-8//IGNORE', $content);
                } else {
                    $converted = @iconv('TIS-620', 'UTF-8//IGNORE', $content);
                    if ($converted) {
                        $content = $converted;
                    }
                }

                $lines = explode("\n", $content);

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $objArr = str_getcsv($line, '$');
                    if (count($objArr) < 78) {
                        $failed++;
                        continue;
                    }

                    $yearData = $objArr[0];
                    $monthData = $objArr[1];
                    $cidData = $objArr[2];

                    if (strlen($cidData) != 13) {
                        $failed++;
                        continue;
                    }

                    $total++;

                    $record = [
                        'ministry' => $objArr[6],
                        'depart' => $objArr[7],
                        'bankId' => $objArr[8],
                        'bankName' => $objArr[9],
                        'bankBranchId' => $objArr[10],
                        'bankBranchName' => $objArr[11],
                        'bankNo' => $objArr[12],

                        'incomeSalaryAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[13]),
                        'incomeSalaryBackPayAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[14]),
                        'incomeManagerMoneyAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[15]),
                        'incomeManagerMoneyBackPayAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[16]),
                        'incomePositionKingAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[17]),
                        'incomeWarAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[18]),
                        'incomeCourseFeeAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[19]),
                        'incomeBreakLawAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[20]),
                        'incomeSpecialPlaceAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[21]),
                        'incomeSouthAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[22]),
                        'incomePositionMoneyAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[23]),
                        'incomePositionMoneyBackPayAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[24]),
                        'incomeLv8Amount' => $this->removeLeadingZerosFromNumber('1', $objArr[25]),
                        'incomeLv8BackPayAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[26]),
                        'incomeLv1to7Amount' => $this->removeLeadingZerosFromNumber('1', $objArr[27]),
                        'incomeSpecialHightAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[28]),
                        'incomeHouseRentAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[29]),
                        'incomeChildFeeAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[30]),
                        'incomeChildEducationFeeAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[31]),
                        'incomeRewardAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[32]),

                        'incomeAdd1Name' => $objArr[33],
                        'incomeAdd1Amount' => $this->removeLeadingZerosFromNumber($objArr[33], $objArr[34]),
                        'incomeAdd2Name' => $objArr[35],
                        'incomeAdd2Amount' => $this->removeLeadingZerosFromNumber($objArr[35], $objArr[36]),
                        'incomeAdd3Name' => $objArr[37],
                        'incomeAdd3Amount' => $this->removeLeadingZerosFromNumber($objArr[37], $objArr[38]),
                        'incomeAdd4Name' => $objArr[39],
                        'incomeAdd4Amount' => $this->removeLeadingZerosFromNumber($objArr[39], $objArr[40]),

                        'incomeOtherAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[41]),
                        'summaryIncomeAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[42]),

                        'outcomeTaxAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[43]),
                        'outcomeHomeLoanAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[44]),
                        'outcomeCoopAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[45]),
                        'outcomeEduLoanAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[46]),
                        'outcomeGPFAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[47]),
                        'outcomeGPFAddAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[48]),
                        'outcomeGHBankAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[49]),
                        'outcomeGSBankAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[50]),
                        'outcomeUniverLoanAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[51]),
                        'outcomeTorPorLoanAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[52]),
                        'outcomeCivilPenaltyAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[53]),
                        'outcomeRestoreAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[54]),
                        'outcomeUtilitieAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[55]),
                        'outcomeClubAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[56]),
                        'outcomeTeacherDeadAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[57]),
                        'outcomeCoopDeadAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[58] ?? ''),

                        'outcomeAdd1Name' => $objArr[59] ?? '',
                        'outcomeAdd1Amount' => $this->removeLeadingZerosFromNumber($objArr[59] ?? '', $objArr[60] ?? ''),
                        'outcomeAdd2Name' => $objArr[61] ?? '',
                        'outcomeAdd2Amount' => $this->removeLeadingZerosFromNumber($objArr[61] ?? '', $objArr[62] ?? ''),
                        'outcomeAdd3Name' => $objArr[63] ?? '',
                        'outcomeAdd3Amount' => $this->removeLeadingZerosFromNumber($objArr[63] ?? '', $objArr[64] ?? ''),
                        'outcomeAdd4Name' => $objArr[65] ?? '',
                        'outcomeAdd4Amount' => $this->removeLeadingZerosFromNumber($objArr[65] ?? '', $objArr[66] ?? ''),
                        'outcomeAdd5Name' => $objArr[67] ?? '',
                        'outcomeAdd5Amount' => $this->removeLeadingZerosFromNumber($objArr[67] ?? '', $objArr[68] ?? ''),
                        'outcomeAdd6Name' => $objArr[69] ?? '',
                        'outcomeAdd6Amount' => $this->removeLeadingZerosFromNumber($objArr[69] ?? '', $objArr[70] ?? ''),
                        'outcomeAdd7Name' => $objArr[71] ?? '',
                        'outcomeAdd7Amount' => $this->removeLeadingZerosFromNumber($objArr[71] ?? '', $objArr[72] ?? ''),
                        'outcomeAdd8Name' => $objArr[73] ?? '',
                        'outcomeAdd8Amount' => $this->removeLeadingZerosFromNumber($objArr[73] ?? '', $objArr[74] ?? ''),

                        'outcomeOtherAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[75] ?? ''),
                        'summaryOutcomeAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[76] ?? ''),
                        'summaryTotalAmount' => $this->removeLeadingZerosFromNumber('1', $objArr[77] ?? ''),

                        'verifyDate' => isset($objArr[78]) ? $this->convertDateFormat($objArr[78]) : null,
                        'r_update' => now(),
                    ];

                    DB::table('data_salary')->updateOrInsert(
                        ['yearNO' => $yearData, 'monthNO' => $monthData, 'cid' => $cidData],
                        $record
                    );

                    $success++;
                }
            }

            DB::commit();

            $this->importResult = [
                'success' => $success,
                'failed' => $failed,
                'total' => $total
            ];

            $this->reset('fileUpload');

            $latest = DB::table('data_salary')
                ->select('yearNO', 'monthNO')
                ->orderBy('yearNO', 'desc')
                ->orderBy('monthNO', 'desc')
                ->first();

            if ($latest) {
                $this->searchYear = $latest->yearNO;
                $this->searchMonth = $latest->monthNO;
            }

            $this->dispatch('toast', message: "นำเข้าข้อมูลสำเร็จ ($success รายการ)");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payslip import failed: ' . $e->getMessage());
            $this->addError('fileUpload', 'เกิดข้อผิดพลาดในการนำเข้าข้อมูล: ' . $e->getMessage());
        }

        $this->isImporting = false;
    }

    public function render()
    {
        return view('livewire.admin.payslip-management')
            ->title('จัดการใบสลิปเงินเดือน');
    }
}
