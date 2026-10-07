<?php

namespace App\Services;

use App\Models\LeaveSyncRun;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class LeaveSheetSyncService
{
    private const REQUIRED_HEADERS = [
        'เลขประจำตัวประชาชน',
        'ชื่อ + นามสกุล',
        'ประเภทการลา',
        'วันที่(เริ่ม)',
        'วันที่(สิ้นสุด)',
        'จำนวนวัน',
        'สถานะวันลา',
    ];

    private const OPTIONAL_HEADERS = [
        'หมายเหตุ',
    ];

    private const MAX_REPORTED_ERRORS = 100;

    private const LOCK_NAME = 'new-hrpromote-leave-sheet-sync';

    /**
     * Fetch, validate and upsert the provided sheet. Row-level errors are
     * collected so one malformed row does not prevent the remaining rows.
     *
     * The optional progress callback receives (stage, processed, total) where
     * stage is one of: downloading, processing.
     *
     * @param  (callable(string, int, int): void)|null  $onProgress
     * @return array<string, mixed>
     */
    public function sync(int $adminUserId, string $sheetUrl, ?callable $onProgress = null): array
    {
        $report = static function (string $stage, int $processed = 0, int $total = 0) use ($onProgress): void {
            if ($onProgress !== null) {
                $onProgress($stage, $processed, $total);
            }
        };

        $run = LeaveSyncRun::create([
            'admin_user_id' => $adminUserId,
            'status' => 'running',
            'started_at' => now(),
        ]);
        $lockAcquired = false;

        try {
            $lockAcquired = $this->acquireLock();
            if (!$lockAcquired) {
                throw new LeaveSheetSyncException('มีการซิงค์ข้อมูลการลาอยู่ กรุณาลองใหม่ภายหลัง');
            }

            $report('downloading');
            $records = $this->parseCsv($this->downloadCsv($sheetUrl));
            $total = count($records);
            $report('processing', 0, $total);
            $summary = [
                'fetched_count' => count($records),
                'created_count' => 0,
                'updated_count' => 0,
                'skipped_count' => 0,
                'failed_count' => 0,
            ];
            $errors = [];

            foreach ($records as $index => $record) {
                $rowNumber = $record['_row_number'];

                try {
                    $normalized = $this->normalizeRecord($record);
                    if ($normalized === null) {
                        $summary['skipped_count']++;
                        continue;
                    }

                    if ($this->isExcludedLeaveType($normalized['leave_type'])) {
                        $summary['skipped_count']++;
                        continue;
                    }

                    $created = DB::transaction(fn () => $this->upsertLeave($normalized));
                    $summary[$created ? 'created_count' : 'updated_count']++;
                } catch (InvalidArgumentException $exception) {
                    $summary['failed_count']++;
                    $this->appendError($errors, $rowNumber, $exception->getMessage());
                } catch (Throwable) {
                    $summary['failed_count']++;
                    $this->appendError($errors, $rowNumber, 'บันทึกข้อมูลแถวนี้ไม่สำเร็จ');
                } finally {
                    $report('processing', $index + 1, $total);
                }
            }

            $run->update([
                ...$summary,
                'status' => 'completed',
                'errors' => $errors,
                'finished_at' => now(),
            ]);

            return [
                'run_id' => $run->id,
                ...$summary,
                'errors' => $errors,
                'errors_truncated' => $summary['failed_count'] > count($errors),
            ];
        } catch (Throwable $exception) {
            $safeMessage = $exception instanceof LeaveSheetSyncException
                ? $exception->getMessage()
                : 'เกิดข้อผิดพลาดระหว่างซิงค์ข้อมูล กรุณาตรวจสอบการตั้งค่าและฐานข้อมูล';

            $run->update([
                'status' => 'failed',
                'error_summary' => $safeMessage,
                'finished_at' => now(),
            ]);

            throw new RuntimeException($safeMessage);
        } finally {
            if ($lockAcquired) {
                $this->releaseLock();
            }
        }
    }

    private function acquireLock(): bool
    {
        $lock = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK_NAME]);

        return (int) ($lock->acquired ?? 0) === 1;
    }

    private function releaseLock(): void
    {
        try {
            DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_NAME]);
        } catch (Throwable) {
            // Connection teardown releases a MySQL advisory lock as a fallback.
        }
    }

    private function downloadCsv(string $sheetUrl): string
    {
        $url = $this->normalizeSheetUrl($sheetUrl);
        $maxBytes = (int) config('services.leave_sheet.max_csv_bytes', 5 * 1024 * 1024);
        if ($maxBytes < 1024 || $maxBytes > 20 * 1024 * 1024) {
            $maxBytes = 5 * 1024 * 1024;
        }

        try {
            $response = Http::accept('text/csv')
                ->connectTimeout(5)
                ->timeout(30)
                ->withOptions([
                    'stream' => true,
                    'read_timeout' => 10,
                    'allow_redirects' => ['max' => 3, 'protocols' => ['https']],
                ])
                ->get($url);
        } catch (Throwable) {
            throw new LeaveSheetSyncException('เชื่อมต่อ Google Sheet ไม่สำเร็จ');
        }

        if (!$response->successful()) {
            throw new LeaveSheetSyncException("ดาวน์โหลด Google Sheet ไม่สำเร็จ (HTTP {$response->status()})");
        }

        $effectiveUri = $response->transferStats?->getEffectiveUri();
        if ($effectiveUri) {
            $effectiveHost = strtolower($effectiveUri->getHost());
            if ($effectiveHost !== 'docs.google.com' && !str_ends_with($effectiveHost, '.googleusercontent.com')) {
                throw new LeaveSheetSyncException('Google Sheet เปลี่ยนเส้นทางไปยัง host ที่ไม่อนุญาต');
            }
        }

        $contentLength = (int) $response->header('Content-Length');
        if ($contentLength > $maxBytes) {
            throw new LeaveSheetSyncException('ไฟล์ CSV มีขนาดใหญ่เกินกำหนด');
        }

        return $this->readLimitedBody($response, $maxBytes);
    }

    public function normalizeSheetUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2048) {
            throw new LeaveSheetSyncException('กรุณาระบุ URL ของ Google Sheets ที่ถูกต้อง');
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new LeaveSheetSyncException('กรุณาระบุ URL ของ Google Sheets ที่ถูกต้อง');
        }

        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        $validHost = strtolower($parts['host'] ?? '') === 'docs.google.com';
        $validPath = preg_match('#^/spreadsheets/d/([A-Za-z0-9_-]+)(?:/(export|pub|edit|view))?/?$#', $path, $matches) === 1;
        $suffix = $matches[2] ?? null;
        $validCsv = ($suffix === 'export' && ($query['format'] ?? null) === 'csv')
            || ($suffix === 'pub' && ($query['output'] ?? null) === 'csv')
            || in_array($suffix, ['edit', 'view', null], true);

        $validPort = !isset($parts['port']) || $parts['port'] === 443;
        $noCredentials = !isset($parts['user']) && !isset($parts['pass']);

        if (($parts['scheme'] ?? '') !== 'https' || !$validHost || !$validPath || !$validCsv || !$validPort || !$noCredentials) {
            throw new LeaveSheetSyncException('URL ต้องเป็น HTTPS link ของ Google Sheets หรือ CSV export URL ที่รองรับ');
        }

        if ($suffix === 'pub') {
            return $url;
        }

        $gid = $query['gid'] ?? null;
        if ($gid === null && isset($parts['fragment']) && preg_match('/(?:^|&)gid=(\d+)(?:&|$)/', $parts['fragment'], $fragmentMatch)) {
            $gid = $fragmentMatch[1];
        }
        if ($gid !== null && (!is_scalar($gid) || !preg_match('/^\d{1,20}$/', (string) $gid))) {
            throw new LeaveSheetSyncException('URL มีรหัสแท็บ Google Sheets ไม่ถูกต้อง');
        }

        $exportUrl = 'https://docs.google.com/spreadsheets/d/' . $matches[1] . '/export?format=csv';

        return $exportUrl . '&gid=' . ($gid ?? '0');
    }

    private function readLimitedBody(Response $response, int $maxBytes): string
    {
        $stream = $response->toPsrResponse()->getBody();
        $csv = '';

        try {
            while (!$stream->eof()) {
                $csv .= $stream->read(64 * 1024);
                if (strlen($csv) > $maxBytes) {
                    throw new LeaveSheetSyncException('ไฟล์ CSV มีขนาดใหญ่เกินกำหนด');
                }
            }
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new LeaveSheetSyncException('อ่านข้อมูล CSV จาก Google Sheet ไม่สำเร็จ');
        }

        if (trim($csv) === '') {
            throw new LeaveSheetSyncException('Google Sheet ไม่มีข้อมูล CSV');
        }

        return $csv;
    }

    /** @return array<int, array<string, string>> */
    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');
        if ($stream === false) {
            throw new LeaveSheetSyncException('เตรียมพื้นที่อ่าน CSV ไม่สำเร็จ');
        }

        try {
            fwrite($stream, $csv);
            rewind($stream);
            $headers = fgetcsv($stream, 0, ',', '"', '');

            if (!is_array($headers)) {
                throw new LeaveSheetSyncException('อ่านหัวตาราง Google Sheet ไม่สำเร็จ');
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($headers[0] ?? ''));
            $headers = array_map(static fn ($header) => trim((string) $header), $headers);
            $missingHeaders = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
            if ($missingHeaders !== []) {
                throw new LeaveSheetSyncException('หัวตาราง Google Sheet ไม่ครบ: ' . implode(', ', $missingHeaders));
            }

            $headerIndexes = [];
            foreach ($headers as $index => $header) {
                if (!array_key_exists($header, $headerIndexes)) {
                    $headerIndexes[$header] = $index;
                }
            }
            $records = [];
            $rowNumber = 1;
            while (($columns = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $rowNumber++;
                if (count($columns) === 1 && trim((string) $columns[0]) === '') {
                    continue;
                }

                $record = [];
                foreach (self::REQUIRED_HEADERS as $header) {
                    $record[$header] = trim((string) ($columns[$headerIndexes[$header]] ?? ''));
                }
                foreach (self::OPTIONAL_HEADERS as $header) {
                    $record[$header] = isset($headerIndexes[$header])
                        ? trim((string) ($columns[$headerIndexes[$header]] ?? ''))
                        : '';
                }
                $record['_row_number'] = $rowNumber;
                $records[] = $record;
            }

            return $records;
        } finally {
            fclose($stream);
        }
    }

    /** @param array<string, string> $record
     *  @return array<string, mixed>|null
     */
    private function normalizeRecord(array $record): ?array
    {
        $name = preg_replace('/\s+/u', ' ', trim($record['ชื่อ + นามสกุล']));
        $rawCid = trim($record['เลขประจำตัวประชาชน']);

        if ($name === '' || $name === '#REF!' || $rawCid === '#REF!') {
            return null;
        }
        if (mb_strlen($name) > 200) {
            throw new InvalidArgumentException('ชื่อผู้ลายาวเกินขนาดที่ระบบรองรับ');
        }

        $cid = preg_replace('/[^0-9]/', '', $rawCid);
        if (!$this->isValidThaiCid($cid)) {
            throw new InvalidArgumentException('เลขประจำตัวประชาชนไม่ถูกต้อง');
        }

        $leaveType = preg_replace('/\s+/u', ' ', trim($record['ประเภทการลา'])) ?: 'อื่นๆ';
        if (mb_strlen($leaveType) > 100) {
            throw new InvalidArgumentException('ประเภทการลายาวเกินขนาดที่ระบบรองรับ');
        }

        $startDate = $this->parseThaiDate($record['วันที่(เริ่ม)']);
        $endDate = $this->parseThaiDate($record['วันที่(สิ้นสุด)']);
        if ($startDate > $endDate) {
            throw new InvalidArgumentException('วันที่เริ่มลาต้องไม่อยู่หลังวันที่สิ้นสุด');
        }

        $rawDays = str_replace([',', ' '], '', trim($record['จำนวนวัน']));
        if (!preg_match('/^\d+(?:\.\d)?$/', $rawDays) || (float) $rawDays <= 0 || (float) $rawDays > 9999.9) {
            throw new InvalidArgumentException('จำนวนวันลาไม่ถูกต้อง');
        }

        $reason = trim($record['หมายเหตุ']);
        if ($reason === '#REF!') {
            $reason = '';
        }
        if (strlen($reason) > 60000) {
            throw new InvalidArgumentException('หมายเหตุยาวเกินขนาดที่ระบบรองรับ');
        }

        return [
            'cid' => $cid,
            'name' => $name,
            'leave_type' => $leaveType,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => number_format((float) $rawDays, 1, '.', ''),
            'status' => $this->mapStatus($record['สถานะวันลา']),
            'reason' => $reason !== '' ? $reason : 'ซิงค์จาก Google Sheet',
        ];
    }

    private function isValidThaiCid(string $cid): bool
    {
        if (!preg_match('/^\d{13}$/', $cid)) {
            return false;
        }

        $sum = 0;
        for ($index = 0; $index < 12; $index++) {
            $sum += ((int) $cid[$index]) * (13 - $index);
        }

        return ((11 - ($sum % 11)) % 10) === (int) $cid[12];
    }

    private function parseThaiDate(string $rawDate): string
    {
        $rawDate = trim($rawDate);
        if (!preg_match('/^(\d{1,4})[\/\-.](\d{1,2})[\/\-.](\d{1,4})$/', $rawDate, $matches)) {
            throw new InvalidArgumentException('รูปแบบวันที่ไม่ถูกต้อง');
        }

        if (strlen($matches[1]) === 4) {
            [$year, $month, $day] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
        } else {
            [$day, $month, $year] = [(int) $matches[1], (int) $matches[2], (int) $matches[3]];
        }

        if ($year < 100) {
            $year += $year > 50 ? 2500 : 2000;
        }
        if ($year > 2400) {
            $year -= 543;
        }
        if ($year < 1900 || $year > 2100 || !checkdate($month, $day, $year)) {
            throw new InvalidArgumentException('วันที่ไม่ถูกต้อง');
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function mapStatus(string $rawStatus): string
    {
        $status = mb_strtolower(preg_replace('/\s+/u', '', trim($rawStatus)));

        return match ($status) {
            'อนุมัติ', 'อนุมัติแล้ว', 'approved' => 'approved',
            'ไม่อนุมัติ', 'rejected' => 'rejected',
            'อนุมัติขั้นที่1', 'step1_approved' => 'step1_approved',
            default => 'pending',
        };
    }

    private function isExcludedLeaveType(string $leaveType): bool
    {
        foreach (config('services.leave_sheet.excluded_types', []) as $excludedType) {
            if ($excludedType !== '' && mb_stripos($leaveType, $excludedType) !== false) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $record
     *  @return bool true when a new leave request was inserted
     */
    private function upsertLeave(array $record): bool
    {
        $leaveTypeId = $this->resolveLeaveTypeId($record['leave_type']);

        $query = DB::table('leave_requests')
            ->where('start_date', $record['start_date'])
            ->where('end_date', $record['end_date'])
            ->where('cid', $record['cid'])
            ->lockForUpdate();

        $existingRows = $query->get(['id', 'user_id', 'leave_type_id', 'status']);
        $sameTypeRows = $existingRows->where('leave_type_id', $leaveTypeId);

        if ($sameTypeRows->count() > 1 || ($sameTypeRows->isEmpty() && $existingRows->count() > 1)) {
            throw new InvalidArgumentException('พบรายการบุคคลและช่วงวันที่ซ้ำมากกว่าหนึ่งรายการ');
        }

        $existing = $sameTypeRows->first() ?? $existingRows->first();
        $now = now();
        $data = [
            'user_id' => $existing?->user_id,
            'external_name' => $record['name'],
            'cid' => $record['cid'],
            'leave_type_id' => $leaveTypeId,
            'start_date' => $record['start_date'],
            'end_date' => $record['end_date'],
            'total_days' => $record['total_days'],
            'reason' => $record['reason'],
            'status' => $record['status'],
            'step1_status' => $record['status'],
            'step2_status' => $record['status'],
            'updated_at' => $now,
        ];

        if ($existing) {
            // An administrator's cancellation must survive later sheet syncs.
            if ($existing->status === 'cancelled') {
                unset($data['status'], $data['step1_status'], $data['step2_status']);
            }

            DB::table('leave_requests')->where('id', $existing->id)->update($data);

            return false;
        }

        $data['created_at'] = $now;
        DB::table('leave_requests')->insert($data);

        return true;
    }

    private function resolveLeaveTypeId(string $name): int
    {
        $existing = DB::table('leave_types')->where('name', $name)->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        $code = 'SYNC_' . substr(hash('sha256', mb_strtolower($name)), 0, 15);
        if (DB::table('leave_types')->where('code', $code)->exists()) {
            throw new InvalidArgumentException('ไม่สามารถสร้างรหัสประเภทการลาได้ กรุณาตรวจสอบข้อมูลประเภทลา');
        }

        return (int) DB::table('leave_types')->insertGetId([
            'name' => $name,
            'code' => $code,
            'max_days' => null,
            'is_active' => 1,
            'sort_order' => 0,
        ]);
    }

    /** @param array<int, array{row_number: int, message: string}> $errors */
    private function appendError(array &$errors, int $rowNumber, string $message): void
    {
        if (count($errors) < self::MAX_REPORTED_ERRORS) {
            $errors[] = ['row_number' => $rowNumber, 'message' => $message];
        }
    }
}
