<?php

namespace App\Services;

use App\Models\User;
use App\Support\AdminAccess;
use App\Support\OfficeScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;
use Throwable;
use ZipArchive;

final class LeaveExcelImportService
{
    public const MAX_ROWS = 5000;

    /** @return array{created:int, skipped:int, out_of_office:int} */
    public function import(string $path, User $actor): array
    {
        abort_unless(AdminAccess::allows($actor, 'leave') && OfficeScope::officeFor($actor) !== '', 403);
        $this->checkArchive($path);
        $book = null;
        try {
            $book = (new Xlsx)->setReadDataOnly(true)->load($path);
            $sheet = $book->getSheet(0);
            if ($sheet->getHighestDataRow() - 1 > self::MAX_ROWS) {
                throw new RuntimeException('ไฟล์มีข้อมูลเกิน 5,000 รายการ กรุณาแบ่งไฟล์ก่อนนำเข้า');
            }
            $rows = [];
            for ($number = 2; $number <= $sheet->getHighestDataRow(); $number++) {
                $values = [];
                $formula = false;
                foreach (range('A', 'F') as $column) {
                    $cell = $sheet->getCell($column.$number);
                    $formula = $formula || $cell->getDataType() === DataType::TYPE_FORMULA;
                    $values[] = is_string($cell->getValue()) ? trim($cell->getValue()) : $cell->getValue();
                }
                if (count(array_filter($values, fn ($value) => $value !== null && $value !== '')) === 0) {
                    continue;
                }
                $rows[] = compact('number', 'values', 'formula');
            }
        } catch (RuntimeException $error) {
            throw $error;
        } catch (Throwable) {
            throw new RuntimeException('ไม่สามารถอ่านไฟล์ Excel ได้ กรุณาใช้เทมเพลท .xlsx');
        } finally {
            $book?->disconnectWorksheets();
        }
        if ($rows === []) {
            throw new RuntimeException('ไฟล์ไม่มีรายการลาสำหรับนำเข้า');
        }
        // Manual save and Excel import lock personnel before leave rows; stable CID order serializes imports.
        usort($rows, fn ($a, $b) => strcmp((string) $a['values'][0], (string) $b['values'][0]) ?: $a['number'] <=> $b['number']);

        try {
            return DB::transaction(function () use ($rows, $actor): array {
                $created = $skipped = $out_of_office = 0;
                $errors = [];
                foreach ($rows as $row) {
                    $cid = (string) ($row['values'][0] ?? '');
                    $fail = function (string $message) use (&$errors, $row): void {
                        $errors[] = 'แถว '.$row['number'].': '.$message;
                    };
                    if (! preg_match('/^\d{13}$/', $cid) || $row['formula']) {
                        $fail('เลขบัตรต้องมี 13 หลัก และไม่อนุญาตให้ใช้สูตรในข้อมูล');

                        continue;
                    }
                    $person = DB::table('person')->where('cid', $cid)->lockForUpdate()->first();
                    if ($person === null) {
                        $fail('ไม่พบบุคลากรในทะเบียน');

                        continue;
                    }
                    $scope = OfficeScope::officeFor($actor);
                    if ($scope !== null && (string) $person->office_id !== $scope) {
                        $skipped++;
                        $out_of_office++;

                        continue;
                    }
                    $record = [
                        'start_date' => $this->date($row['values'][2]),
                        'end_date' => $this->date($row['values'][3]),
                        'duration' => $row['values'][4],
                        'reason' => $row['values'][5] === '' ? null : $row['values'][5],
                    ];
                    $validation = Validator::make($record, [
                        'start_date' => ['required', 'date_format:Y-m-d'],
                        'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
                        'duration' => ['required', 'in:เต็มวัน,ครึ่งวัน'],
                        'reason' => ['nullable', 'string', 'max:1000'],
                    ]);
                    if ($validation->fails()) {
                        $fail('วันที่ต้องเป็น ค.ศ. YYYY-MM-DD หรือวันที่ Excel โดยวันสิ้นสุดไม่ก่อนวันเริ่ม เลือกเต็มวัน/ครึ่งวัน และหมายเหตุไม่เกิน 1,000 ตัวอักษร');

                        continue;
                    }
                    if (! is_string($row['values'][1]) || $row['values'][1] === '') {
                        $fail('ประเภทการลาต้องเป็นชื่อประเภทการลา');

                        continue;
                    }
                    $type = DB::table('leave_types')->where('name', $row['values'][1])->where('is_active', true)->first(['id']);
                    if ($type === null) {
                        $fail('ไม่พบประเภทการลาที่เปิดใช้งาน');

                        continue;
                    }
                    $days = app(ThaiHolidayService::class)->workingDaysBetween($record['start_date'], $record['end_date']);
                    if ($days === 0 || ($record['duration'] === 'ครึ่งวัน' && $days !== 1)) {
                        $fail('ช่วงวันที่ต้องมีวันทำการและไม่เกิน 366 วัน ครึ่งวันใช้ได้เมื่อมีวันทำการเพียงหนึ่งวัน');

                        continue;
                    }
                    $total = $record['duration'] === 'ครึ่งวัน' ? 0.5 : $days;
                    $overlaps = DB::table('leave_requests')->where('cid', $cid)->whereNotIn('status', ['rejected', 'cancelled'])
                        ->where('start_date', '<=', $record['end_date'])->where('end_date', '>=', $record['start_date'])->lockForUpdate()->get();
                    if ($overlaps->count() === 1) {
                        $existing = $overlaps->first();
                        if ((int) $existing->leave_type_id === (int) $type->id && $existing->start_date === $record['start_date'] && $existing->end_date === $record['end_date']
                            && (float) $existing->total_days === (float) $total && trim((string) $existing->reason) === trim((string) $record['reason'])) {
                            $skipped++;

                            continue;
                        }
                    }
                    if ($overlaps->isNotEmpty()) {
                        $fail('มีรายการลาทับช่วงวันที่นี้อยู่แล้ว');

                        continue;
                    }
                    DB::table('leave_requests')->insert([
                        'cid' => $cid, 'external_name' => trim(implode(' ', array_filter([$person->pname, $person->fname, $person->lname]))),
                        'leave_type_id' => $type->id, 'start_date' => $record['start_date'], 'end_date' => $record['end_date'],
                        'total_days' => $total, 'reason' => $record['reason'], 'status' => 'approved', 'step1_status' => 'approved', 'step2_status' => 'approved',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $created++;
                }
                if ($errors !== []) {
                    throw new RuntimeException('ไม่สามารถนำเข้าไฟล์ได้ กรุณาแก้ไขข้อมูลก่อน: '.implode(' | ', array_slice($errors, 0, 10)));
                }

                return compact('created', 'skipped', 'out_of_office');
            });
        } catch (QueryException) {
            throw new RuntimeException('ไม่สามารถบันทึกข้อมูลการลาได้ กรุณาลองใหม่หรือติดต่อผู้ดูแลระบบ');
        }
    }

    private function date(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            try {
                return $value > 0 ? ExcelDate::excelToDateTimeObject($value)->format('Y-m-d') : null;
            } catch (Throwable) {
                return null;
            }
        }

        return is_string($value) ? $value : null;
    }

    private function checkArchive(string $path): void
    {
        if (! is_file($path) || filesize($path) > 5 * 1024 * 1024) {
            throw new RuntimeException('ไฟล์ต้องมีขนาดไม่เกิน 5MB');
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('กรุณาเลือกไฟล์ Excel .xlsx');
        }
        try {
            $size = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $size += $zip->statIndex($index)['size'];
            }
            if ($zip->numFiles > 1000 || $size > 20 * 1024 * 1024) {
                throw new RuntimeException('ไฟล์ Excel มีข้อมูลภายในมากเกินไป กรุณาแบ่งไฟล์');
            }
        } finally {
            $zip->close();
        }
    }
}
