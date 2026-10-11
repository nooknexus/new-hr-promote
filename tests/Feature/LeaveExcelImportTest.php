<?php

use App\Livewire\Admin\LeaveManagement;
use App\Models\User;
use App\Services\LeaveExcelImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    Cache::flush();
    Http::fake(['*' => Http::response('', 503)]);
    Schema::create('person', function ($table) {
        $table->string('cid', 13)->primary();
        foreach (['pname', 'fname', 'lname', 'office_id', 'office_name'] as $field) {
            $table->string($field)->nullable();
        }
    });
    Schema::create('leave_types', function ($table) {
        $table->id();
        $table->string('name')->unique();
        $table->string('code')->unique();
        $table->boolean('is_active')->default(true);
        $table->integer('sort_order')->default(0);
    });
    Schema::create('leave_requests', function ($table) {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('cid');
        $table->string('external_name')->nullable();
        $table->unsignedBigInteger('leave_type_id');
        $table->date('start_date');
        $table->date('end_date');
        $table->decimal('total_days', 5, 1);
        $table->text('reason')->nullable();
        foreach (['status', 'step1_status', 'step2_status'] as $field) {
            $table->string($field);
        }
        $table->timestamps();
    });
    DB::table('person')->insert([
        ['cid' => '0123456789012', 'pname' => 'นาย', 'fname' => 'ทดสอบ', 'lname' => 'นำเข้า', 'office_id' => '01', 'office_name' => 'ต้นสังกัดหนึ่ง'],
        ['cid' => '1234567890123', 'pname' => 'นาง', 'fname' => 'ทดสอบ', 'lname' => 'ต่างสังกัด', 'office_id' => '02', 'office_name' => 'ต้นสังกัดสอง'],
    ]);
    DB::table('leave_types')->insert(['name' => 'ลาป่วย', 'code' => 'SICK']);
    $this->paths = [];
});

afterEach(function () {
    foreach ($this->paths as $path) {
        @unlink($path);
    }
});

function excelLeaveActor(string $role = 'SUPERADMIN', ?string $office = null, bool $active = true): User
{
    $user = new User;
    $user->forceFill(['id' => 10, 'name' => 'ผู้ดูแลทดสอบ', 'role' => $role, 'office' => $office, 'is_active' => $active]);
    $user->exists = true;

    return $user;
}

function excelLeaveRow(array $overrides = []): array
{
    return array_replace(['0123456789012', 'ลาป่วย', '2026-09-01', '2026-09-02', 'เต็มวัน', 'หมายเหตุทดสอบ'], $overrides);
}

function excelLeaveFile(object $test, array $rows): string
{
    $book = new Spreadsheet;
    $sheet = $book->getActiveSheet();
    $sheet->fromArray(['เลขบัตร 13 หลัก', 'ประเภทการลา', 'วันที่เริ่ม', 'วันที่สิ้นสุด', 'เต็มวัน/ครึ่งวัน', 'หมายเหตุ'], null, 'A1');
    foreach ($rows as $index => $row) {
        $sheet->fromArray($row, null, 'A'.($index + 2));
        $sheet->setCellValueExplicit('A'.($index + 2), (string) $row[0], DataType::TYPE_STRING);
    }
    $path = tempnam(sys_get_temp_dir(), 'leave-xlsx-');
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();
    $test->paths[] = $path;

    return $path;
}

test('Excel leave import computes days creates approved records and skips an identical reimport', function () {
    $path = excelLeaveFile($this, [excelLeaveRow()]);
    $service = app(LeaveExcelImportService::class);
    expect($service->import($path, excelLeaveActor()))->toMatchArray(['created' => 1, 'skipped' => 0, 'out_of_office' => 0]);
    $row = DB::table('leave_requests')->first();
    expect($row->cid)->toBe('0123456789012')->and((float) $row->total_days)->toBe(2.0)
        ->and($row->external_name)->toBe('นาย ทดสอบ นำเข้า')->and($row->status)->toBe('approved')
        ->and($row->step1_status)->toBe('approved')->and($row->step2_status)->toBe('approved');
    expect($service->import($path, excelLeaveActor()))->toMatchArray(['created' => 0, 'skipped' => 1]);
    expect(DB::table('leave_requests')->count())->toBe(1);
});

test('Excel leave import reads the first data sheet when instructions are active', function () {
    $path = excelLeaveFile($this, [excelLeaveRow()]);
    $book = IOFactory::load($path);
    $book->createSheet()->setTitle('คำแนะนำ')->setCellValue('A2', 'กรุณากรอกข้อมูลในชีตแรก');
    $book->setActiveSheetIndex(1);
    (new Xlsx($book))->save($path);
    $book->disconnectWorksheets();

    expect(app(LeaveExcelImportService::class)->import($path, excelLeaveActor()))->toMatchArray(['created' => 1]);
    expect(DB::table('leave_requests')->value('cid'))->toBe('0123456789012');
});

test('Excel leave import rejects numeric leave types before querying names', function () {
    $path = excelLeaveFile($this, [excelLeaveRow([1 => 0])]);
    $typeQueries = 0;
    DB::listen(function ($query) use (&$typeQueries) {
        if (str_contains($query->sql, 'leave_types')) {
            $typeQueries++;
        }
    });
    expect(fn () => app(LeaveExcelImportService::class)->import($path, excelLeaveActor()))
        ->toThrow(RuntimeException::class);
    expect($typeQueries)->toBe(0);
    expect(DB::table('leave_requests')->count())->toBe(0);
});

test('Excel leave import skips other offices and denies missing office unauthorized and disabled actors', function () {
    $path = excelLeaveFile($this, [excelLeaveRow(), excelLeaveRow([0 => '1234567890123'])]);
    expect(app(LeaveExcelImportService::class)->import($path, excelLeaveActor('LEAVE', '01')))
        ->toMatchArray(['created' => 1, 'skipped' => 1, 'out_of_office' => 1]);
    foreach ([excelLeaveActor('LEAVE', ''), excelLeaveActor('FINANCE', '00'), excelLeaveActor('SUPERADMIN', null, false)] as $actor) {
        try {
            app(LeaveExcelImportService::class)->import($path, $actor);
            $this->fail('Import authorized');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            expect($error->getStatusCode())->toBe(403);
        }
    }
    expect(DB::table('leave_requests')->count())->toBe(1);
});

test('Excel leave import rejects invalid rows atomically without exposing their CID', function (array $overrides) {
    $path = excelLeaveFile($this, [excelLeaveRow(), excelLeaveRow($overrides)]);
    try {
        app(LeaveExcelImportService::class)->import($path, excelLeaveActor());
        $this->fail('Invalid import accepted');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toContain('แถว 3')->not->toContain('0123456789012')->not->toContain('9999999999999');
    }
    expect(DB::table('leave_requests')->count())->toBe(0);
})->with([
    'unknown person' => [[0 => '9999999999999']], 'unknown type' => [[1 => 'ประเภทที่ไม่มี']],
    'invalid date' => [[2 => '2026-02-30']], 'reversed dates' => [[2 => '2026-09-04']],
    'half multiple days' => [[4 => 'ครึ่งวัน']], 'invalid duration' => [[4 => 'จำนวนผิด']],
    'overlap within file' => [[2 => '2026-09-02', 3 => '2026-09-03']],
    'weekend only' => [[2 => '2026-09-05', 3 => '2026-09-06']],
    'oversized range' => [[2 => '2024-01-01', 3 => '2026-09-03']],
    'formula' => [[2 => '=TODAY()']],
]);

test('Excel leave import uses holidays and Excel serial dates and accepts a half working day', function () {
    Http::swap(new \Illuminate\Http\Client\Factory);
    Http::fake(['*' => Http::response("BEGIN:VCALENDAR\nBEGIN:VEVENT\nDTSTART;VALUE=DATE:20260902\nEND:VEVENT\nEND:VCALENDAR", 200)]);
    $path = excelLeaveFile($this, [excelLeaveRow([2 => 46266, 3 => 46267, 4 => 'ครึ่งวัน'])]);
    // Serial dates represent 1-2 September 2026; the second date is a holiday.
    expect(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject(46266)->format('Y-m-d'))->toBe('2026-09-01');
    app(LeaveExcelImportService::class)->import($path, excelLeaveActor());
    expect((float) DB::table('leave_requests')->value('total_days'))->toBe(0.5);
});

test('Excel leave import refuses active overlaps preserves cancelled history and skips inactive types', function () {
    $service = app(LeaveExcelImportService::class);
    $service->import(excelLeaveFile($this, [excelLeaveRow()]), excelLeaveActor());
    $original = DB::table('leave_requests')->first();
    expect(fn () => $service->import(excelLeaveFile($this, [excelLeaveRow([2 => '2026-09-02', 3 => '2026-09-03'])]), excelLeaveActor()))->toThrow(RuntimeException::class);
    expect(DB::table('leave_requests')->first())->toEqual($original);
    DB::table('leave_requests')->update(['status' => 'cancelled']);
    $service->import(excelLeaveFile($this, [excelLeaveRow()]), excelLeaveActor());
    expect(DB::table('leave_requests')->count())->toBe(2)->and(DB::table('leave_requests')->where('id', $original->id)->value('status'))->toBe('cancelled');
    DB::table('leave_types')->update(['is_active' => false]);
    expect(fn () => $service->import(excelLeaveFile($this, [excelLeaveRow([2 => '2026-09-03', 3 => '2026-09-03'])]), excelLeaveActor()))->toThrow(RuntimeException::class);
});

test('leave Excel import is available in a separate card below the header', function () {
    $component = Livewire::actingAs(excelLeaveActor())->test(LeaveManagement::class);
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$component->html());
    $xpath = new DOMXPath($document);
    $card = $xpath->query('//header/following-sibling::section[1][@aria-labelledby="leave-import-heading"]');
    expect($card->length)->toBe(1);
    expect($xpath->query('.//form[@*[name()="wire:submit"]="importExcel"]//input[@type="file"]', $card->item(0))->length)->toBe(1);
    expect($xpath->query('.//a[@href="'.route('admin.leaves.template').'"]', $card->item(0))->length)->toBe(1);
    expect($xpath->query('.//a[@href="'.route('admin.leaves.template').'"]/following-sibling::a[1][@href="'.route('admin.leave-sheet-sync').'"]', $card->item(0))->length)->toBe(1);
    expect($xpath->query('//div[@id="leaveImportModal"]')->length)->toBe(0);
});

test('leave Excel card validates uploads and shows its result toast', function () {
    $actor = excelLeaveActor();
    $component = Livewire::actingAs($actor)->test(LeaveManagement::class);
    $script = preg_replace('/<\/?script[^>]*>/', '', implode("\n", $component->effects['scripts']));
    $component->call('importExcel')->assertHasErrors('importFile');
    $component->set('importFile', UploadedFile::fake()->create('wrong.txt', 1, 'text/plain'))->call('importExcel')->assertHasErrors('importFile');
    $path = excelLeaveFile($this, [excelLeaveRow()]);
    $component->set('importFile', UploadedFile::fake()->createWithContent('leave.xlsx', file_get_contents($path)))
        ->call('importExcel')->assertHasNoErrors()->assertDispatched('leave-import-closed')->assertDispatched('leave-saved');
    expect(DB::table('leave_requests')->count())->toBe(1);
    $runner = <<<'JS'
const handlers={};
globalThis.$wire={on:(name,fn)=>handlers[name]=fn};
globalThis.window={portalToast:{show:message=>{if(message!=='saved')throw Error('missing result toast')}}};
JS;
    $runner .= "\n".$script."\nhandlers['leave-saved']({message:'saved'});";
    (new \Symfony\Component\Process\Process(['node', '-e', $runner]))->mustRun();
});

test('the leave template is downloadable only with leave access and has six columns', function () {
    $this->actingAs(excelLeaveActor('FINANCE', '00'))->get('/admin/leaves/template')->assertForbidden();
    $response = $this->actingAs(excelLeaveActor('LEAVE', '01'))->get('/admin/leaves/template')->assertOk();
    $book = IOFactory::load($response->getFile()->getPathname());
    expect($book->getActiveSheet()->getHighestDataColumn())->toBe('F')
        ->and($book->getActiveSheet()->getCell('A1')->getValue())->toBe('เลขบัตร 13 หลัก');
    $book->disconnectWorksheets();
});

test('manual leave creation reads the personnel row before inserting to share the import lock boundary', function () {
    $component = Livewire::actingAs(excelLeaveActor())->test(LeaveManagement::class)
        ->call('selectPerson', '0123456789012')->set('leave_type_id', 1)
        ->set('start_date', '2026-09-01')->set('end_date', '2026-09-02');
    $personRead = false;
    $readBeforeInsert = false;
    DB::listen(function ($query) use (&$personRead, &$readBeforeInsert) {
        if (str_starts_with($query->sql, 'select * from "person"')) {
            $personRead = true;
        }
        if (str_starts_with($query->sql, 'insert into "leave_requests"')) {
            $readBeforeInsert = $personRead;
        }
    });
    $component->call('save')->assertHasNoErrors();
    expect($readBeforeInsert)->toBeTrue();
});

test('editing a leave uses its stored owner for overlap checks even if a client changes the CID', function () {
    app(LeaveExcelImportService::class)->import(excelLeaveFile($this, [
        excelLeaveRow([2 => '2026-09-03', 3 => '2026-09-03']), excelLeaveRow(),
    ]), excelLeaveActor());
    $id = DB::table('leave_requests')->where('start_date', '2026-09-03')->value('id');
    Livewire::actingAs(excelLeaveActor())->test(LeaveManagement::class)->call('edit', $id)
        ->set('cid', '1234567890123')->set('start_date', '2026-09-01')->set('end_date', '2026-09-02')
        ->call('save')->assertHasErrors('start_date');
    expect(DB::table('leave_requests')->where('id', $id)->value('start_date'))->toBe('2026-09-03');
});

test('a database write failure rolls back the import and does not expose SQL or CID', function () {
    $component = Livewire::actingAs(excelLeaveActor())->test(LeaveManagement::class);
    $path = excelLeaveFile($this, [excelLeaveRow()]);
    $component->set('importFile', UploadedFile::fake()->createWithContent('leave.xlsx', file_get_contents($path)));
    DB::listen(function ($query) {
        if (str_starts_with($query->sql, 'insert into "leave_requests"')) {
            throw new \Illuminate\Database\QueryException('sqlite', $query->sql, $query->bindings, new PDOException('write failed'));
        }
    });
    $component->call('importExcel')->assertHasErrors('importFile');
    expect($component->instance()->getErrorBag()->first('importFile'))->not->toContain('0123456789012')->not->toContain('insert into');
    expect(DB::table('leave_requests')->count())->toBe(0);
});
