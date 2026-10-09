<?php

use App\Livewire\Admin\SalaryPromotionImport;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\SalaryPromotionWorkbook;

beforeEach(function () {
    Schema::create('data_promote', function (Blueprint $table) {
        $table->id();
        $table->string('CID', 13);
        $table->date('evaluation_date');
        $table->string('promote')->nullable();
        $table->string('ps')->nullable();
        $table->string('office')->nullable();
        $table->string('department')->nullable();
        $table->string('before_salary')->nullable();
        $table->string('base')->nullable();
        $table->string('percent')->nullable();
        $table->string('sum')->nullable();
        $table->string('extra')->nullable();
        $table->string('new_salary')->nullable();
        $table->dateTime('update_date')->nullable();
        $table->dateTime('deleted_at')->nullable();
    });
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->unsignedTinyInteger('type_id')->nullable()->default(1);
        $table->string('office_id', 2)->nullable();
        $table->string('office_name')->nullable();
        $table->string('no_position')->nullable();
        $table->string('position')->nullable();
        $table->string('position_level')->nullable();
    });
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('pid', 13)->nullable();
        $table->string('thaid_pid', 13)->nullable();
        $table->string('no_position')->nullable();
    });
    Schema::create('data_promote_audits', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('promotion_id');
        $table->unsignedBigInteger('actor_id');
        $table->string('action');
        $table->json('before_data')->nullable();
        $table->json('after_data')->nullable();
        $table->timestamp('created_at');
    });
    assignPersonnelTypes(9, [1]);
});

function salaryImportActor(string $role, ?string $office = '00'): User
{
    $user = new User;
    $user->forceFill(['id' => 9, 'name' => 'ผู้ทดสอบ', 'role' => $role, 'office' => $office]);
    $user->exists = true;

    return $user;
}

function salaryImportUpload(SalaryPromotionWorkbook $book): UploadedFile
{
    return UploadedFile::fake()->createWithContent('salary.xlsx', file_get_contents($book->path));
}

test('only ADMIN and PROMOTE may preview or confirm an import', function () {
    foreach (['LEAVE', 'FINANCE'] as $role) {
        Livewire::actingAs(salaryImportActor($role))
            ->test(SalaryPromotionImport::class)
            ->assertForbidden();
    }
    foreach (['ADMIN', 'PROMOTE'] as $role) {
        Livewire::actingAs(salaryImportActor($role))
            ->test(SalaryPromotionImport::class)
            ->assertOk()
            ->assertSee('นำเข้า Excel');
    }
});

test('valid workbook previews counts without writing and confirmation commits with toast', function () {
    DB::table('person')->insert(['cid' => '3650200600388', 'no_position' => 'เดิม']);
    $book = new SalaryPromotionWorkbook;
    $component = Livewire::actingAs(salaryImportActor('PROMOTE'))
        ->test(SalaryPromotionImport::class)
        ->set('importFile', salaryImportUpload($book))
        ->call('previewUpload')
        ->assertHasNoErrors()
        ->assertSet('previewReady', true)
        ->assertSee('เพิ่มใหม่ 1');

    expect(DB::table('data_promote')->count())->toBe(0)
        ->and(DB::table('person')->value('no_position'))->toBe('เดิม');

    $component->call('confirmImport')
        ->assertHasNoErrors()
        ->assertDispatched('salary-import-confirm-closed')
        ->assertDispatched('salary-import-saved');

    expect(DB::table('data_promote')->value('new_salary'))->toBe('14390.00')
        ->and(DB::table('person')->value('no_position'))->toBe('01พล00139');
});

test('invalid workbook cannot open confirmation or write', function () {
    $row = SalaryPromotionWorkbook::ROW;
    $row[0] = 'bad-cid';
    $book = new SalaryPromotionWorkbook([$row]);
    Livewire::actingAs(salaryImportActor('ADMIN'))
        ->test(SalaryPromotionImport::class)
        ->set('importFile', salaryImportUpload($book))
        ->call('previewUpload')
        ->assertSet('previewReady', false)
        ->assertSee('แถว 2')
        ->call('confirmImport')
        ->assertHasErrors();
    expect(DB::table('data_promote')->count())->toBe(0);
});

test('cancelled preview does not write and clears uploaded state', function () {
    $book = new SalaryPromotionWorkbook;
    Livewire::actingAs(salaryImportActor('ADMIN'))
        ->test(SalaryPromotionImport::class)
        ->set('importFile', salaryImportUpload($book))
        ->call('previewUpload')
        ->assertSet('previewReady', true)
        ->call('cancelImport')
        ->assertSet('previewReady', false)
        ->assertSet('importFile', null)
        ->assertDispatched('salary-import-confirm-closed');
    expect(DB::table('data_promote')->count())->toBe(0);
});

test('a file changed after preview must be previewed again before confirmation', function () {
    $book = new SalaryPromotionWorkbook;
    $component = Livewire::actingAs(salaryImportActor('ADMIN'))
        ->test(SalaryPromotionImport::class)
        ->set('importFile', salaryImportUpload($book))
        ->call('previewUpload')
        ->assertSet('previewReady', true);

    $path = $component->get('importFile')->getRealPath();
    $spreadsheet = IOFactory::load($path);
    $spreadsheet->getActiveSheet()->setCellValue('A2', 'bad-cid');
    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    $component->call('confirmImport')
        ->assertHasErrors('importFile')
        ->assertSet('previewReady', false);
    expect(DB::table('data_promote')->count())->toBe(0);
});

test('preview reveals the total count when more than ten salary warnings exist', function () {
    $rows = [];
    foreach (range(1, 12) as $number) {
        $row = SalaryPromotionWorkbook::ROW;
        $row[0] = sprintf('%013d', $number);
        $rows[] = $row;
    }
    $book = new SalaryPromotionWorkbook($rows);

    Livewire::actingAs(salaryImportActor('ADMIN'))
        ->test(SalaryPromotionImport::class)
        ->set('importFile', salaryImportUpload($book))
        ->call('previewUpload')
        ->assertSet('previewReady', true)
        ->assertSee('คำเตือนทั้งหมด 12 แถว')
        ->assertSee('แสดง 10 แถวแรก');
});

test('authorization is rechecked on the confirmation request', function () {
    DB::table('person')->insert(['cid' => '3650200600388']);
    $actor = salaryImportActor('PROMOTE');
    $book = new SalaryPromotionWorkbook;
    $component = Livewire::actingAs($actor)
        ->test(SalaryPromotionImport::class)
        ->set('importFile', salaryImportUpload($book))
        ->call('previewUpload')
        ->assertSet('previewReady', true);
    $actor->role = 'LEAVE';
    $component->call('confirmImport')->assertForbidden();
    expect(DB::table('data_promote')->count())->toBe(0);
});

test('the management page embeds the importer and confirmation uses portal modal', function () {
    $view = file_get_contents(resource_path('views/livewire/admin/salary-promotion-management.blade.php'));
    $importView = file_get_contents(resource_path('views/livewire/admin/salary-promotion-import.blade.php'));
    expect($view)->toContain('<livewire:admin.salary-promotion-import');
    expect($importView)->toContain('window.portalModal.show(\'salaryImportConfirmModal\')')
        ->toContain('window.portalToast.show(message)');

    $book = new SalaryPromotionWorkbook;
    Livewire::actingAs(salaryImportActor('ADMIN'))
        ->test(SalaryPromotionImport::class)
        ->set('importFile', salaryImportUpload($book))
        ->call('previewUpload')
        ->assertSeeHtml('window.portalModal.show(\'salaryImportConfirmModal\')');
});

test('an office-scoped PROMOTE import skips personnel outside their office', function () {
    DB::table('person')->insert([
        ['cid' => '3650200600388', 'office_id' => '01'],
        ['cid' => '1111111111111', 'office_id' => '02'],
    ]);
    $otherOffice = SalaryPromotionWorkbook::ROW;
    $otherOffice[0] = '1111111111111';
    $notInPerson = SalaryPromotionWorkbook::ROW;
    $notInPerson[0] = '2222222222222';
    $book = new SalaryPromotionWorkbook([SalaryPromotionWorkbook::ROW, $otherOffice, $notInPerson]);

    Livewire::actingAs(salaryImportActor('PROMOTE', '01'))
        ->test(SalaryPromotionImport::class)
        ->set('importFile', salaryImportUpload($book))
        ->call('previewUpload')
        ->assertSet('previewReady', true)
        ->assertSet('previewCounts.insert_count', 1)
        ->assertSet('previewCounts.skipped_count', 2)
        ->assertSee('ข้าม (นอกต้นสังกัดหรือประเภทการจ้างที่รับผิดชอบ) 2')
        ->call('confirmImport')
        ->assertHasNoErrors()
        ->assertDispatched('salary-import-saved', message: 'นำเข้าเลื่อนขั้นเงินเดือนแล้ว เพิ่มใหม่ 1 ปรับปรุง 0 รายการ ข้ามบุคลากรนอกต้นสังกัดหรือประเภทการจ้างที่รับผิดชอบ 2 รายการ');

    expect(DB::table('data_promote')->pluck('CID')->all())->toBe(['3650200600388']);
});

test('a PROMOTE import skips personnel outside their assigned types', function () {
    DB::table('person')->insert([
        ['cid' => '3650200600388', 'type_id' => 3],
        ['cid' => '1111111111111', 'type_id' => 1],
    ]);
    DB::table('user_personnel_types')->where('user_id', 9)->delete();
    assignPersonnelTypes(9, [3]);
    $otherType = SalaryPromotionWorkbook::ROW;
    $otherType[0] = '1111111111111';
    $book = new SalaryPromotionWorkbook([SalaryPromotionWorkbook::ROW, $otherType]);

    Livewire::actingAs(salaryImportActor('PROMOTE', '00'))
        ->test(SalaryPromotionImport::class)
        ->set('importFile', salaryImportUpload($book))
        ->call('previewUpload')
        ->assertSet('previewCounts.insert_count', 1)
        ->assertSet('previewCounts.skipped_count', 1)
        ->call('confirmImport')
        ->assertHasNoErrors();

    expect(DB::table('data_promote')->pluck('CID')->all())->toBe(['3650200600388']);
});

test('a PROMOTE user without assigned types cannot import', function () {
    DB::table('user_personnel_types')->delete();

    Livewire::actingAs(salaryImportActor('PROMOTE', '00'))
        ->test(SalaryPromotionImport::class)
        ->assertForbidden();
});

test('ADMIN imports every row regardless of their office', function () {
    $other = SalaryPromotionWorkbook::ROW;
    $other[0] = '1111111111111';
    $book = new SalaryPromotionWorkbook([SalaryPromotionWorkbook::ROW, $other]);

    Livewire::actingAs(salaryImportActor('ADMIN', '05'))
        ->test(SalaryPromotionImport::class)
        ->set('importFile', salaryImportUpload($book))
        ->call('previewUpload')
        ->assertSet('previewCounts.insert_count', 2)
        ->assertSet('previewCounts.skipped_count', 0);
});
