<?php

use App\Livewire\Admin\TaxCertificateManagement;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    Schema::dropIfExists('data_tax');
    Schema::dropIfExists('person');

    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
        $table->string('office_id', 2)->nullable();
    });

    Schema::create('data_tax', function (Blueprint $table) {
        $table->string('cid', 13);
        $table->unsignedInteger('income_year');
        $table->string('running_code1')->nullable();
        $table->string('department_name')->nullable();
        $table->string('department_tax_id')->nullable();
        $table->string('department_addr')->nullable();
        $table->string('tax_id')->nullable();
        $table->string('fullname')->nullable();
        $table->string('addr')->nullable();
        $table->string('running_code2')->nullable();
        $table->string('income_type_id')->nullable();
        $table->string('income_type_name')->nullable();
        $table->decimal('income_money', 15, 2)->nullable();
        $table->decimal('withholding_tax', 15, 2)->nullable();
        $table->decimal('total_money', 15, 2)->nullable();
        $table->decimal('sumTaxAmount', 15, 2)->nullable();
        $table->string('sumTaxText')->nullable();
        $table->string('outcomeType')->nullable();
        $table->string('outcomeName')->nullable();
        $table->decimal('outcomeAmount', 15, 2)->nullable();
        $table->string('tax_type_id')->nullable();
        $table->string('tax_type_name')->nullable();
        $table->string('date_report')->nullable();
        $table->decimal('socialSecurity', 15, 2)->nullable();
        $table->dateTime('r_update')->nullable();
        $table->unique(['cid', 'income_year']);
    });
});

afterEach(function () {
    Schema::dropIfExists('data_tax');
    Schema::dropIfExists('person');
});

function taxManagementActor(string $role, ?string $office = '00'): User
{
    $user = new User;
    $user->forceFill([
        'id' => 91,
        'name' => 'ผู้ทดสอบการเงิน',
        'role' => $role,
        'office' => $office,
    ]);
    $user->exists = true;

    return $user;
}

/** @return array<string, int|float|string|null> */
function taxManagementRecord(array $overrides = []): array
{
    return array_replace([
        'cid' => '1234567890123',
        'income_year' => 2567,
        'running_code1' => 'TC-2567-01',
        'department_name' => 'กรมทดสอบ',
        'department_tax_id' => '0105550000000',
        'department_addr' => '99 ถนนทดสอบ',
        'tax_id' => '0105550000000',
        'fullname' => 'สมศรี ใจดี',
        'addr' => '1 ถนนผู้รับเงิน',
        'running_code2' => '0001',
        'income_type_id' => '2',
        'income_type_name' => 'ภ.ง.ด.1ก พิเศษ',
        'income_money' => 31550.00,
        'withholding_tax' => 1234.00,
        'total_money' => 32784.00,
        'sumTaxAmount' => 1234.00,
        'sumTaxText' => 'หนึ่งพันสองร้อยสามสิบสี่บาทถ้วน',
        'outcomeType' => '1',
        'outcomeName' => 'กบข.',
        'outcomeAmount' => 1893.00,
        'tax_type_id' => '3',
        'tax_type_name' => 'ออกให้ครั้งเดียว',
        'date_report' => '29012568',
        'r_update' => now(),
    ], $overrides);
}

/** @param array<int, string> $overrides */
function taxManagementTxtRecord(array $overrides = []): string
{
    $fields = [
        'TC-TXT-01',
        'กรมทดสอบ',
        '0105550000000',
        '99 ถนนทดสอบ',
        '1234567890123',
        '0105550000000',
        'สมศรี ใจดี',
        '1 ถนนผู้รับเงิน',
        '0001',
        '2',
        '2567',
        '000003155000',
        '000000001234',
        '000003156234',
        '000000001234',
        'สามหมื่นหนึ่งพันห้าร้อยห้าสิบบาทถ้วน',
        '1',
        '000000189300',
        '3',
        '29012568',
    ];

    return implode('$', array_replace($fields, $overrides));
}

function taxManagementTxtUpload(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('certificates.txt', $contents);
}

// Production break caught: a FINANCE user who cannot create, update, or remove the exact CID/year record has no usable management workflow.
test('finance manages one tax certificate by its cid and income year', function () {
    $finance = taxManagementActor('FINANCE');

    $this->actingAs($finance)
        ->get(route('admin.tax-certificates.index'))
        ->assertOk()
        ->assertSee('จัดการใบรับรองภาษี');

    $component = Livewire::actingAs($finance)
        ->test(TaxCertificateManagement::class)
        ->call('openCreateModal')
        ->assertDispatched('tax-certificate-form-opened');

    foreach (taxManagementRecord() as $field => $value) {
        if (! in_array($field, ['income_type_name', 'outcomeName', 'tax_type_name', 'r_update'], true)) {
            $component->set($field, (string) $value);
        }
    }

    $component->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('tax-certificate-form-closed')
        ->assertDispatched('tax-certificate-saved');

    expect((array) DB::table('data_tax')->where('cid', '1234567890123')->where('income_year', 2567)->first())
        ->toMatchArray([
            'running_code1' => 'TC-2567-01',
            'income_type_name' => 'ภ.ง.ด.1ก พิเศษ',
            'outcomeName' => 'กบข.',
            'tax_type_name' => 'ออกให้ครั้งเดียว',
            'socialSecurity' => null,
        ]);

    $component->call('edit', '1234567890123', 2567)
        ->assertSet('editingCid', '1234567890123')
        ->assertSet('editingIncomeYear', 2567)
        ->set('running_code1', 'TC-2567-UPDATED')
        ->set('income_money', '42000.50')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('tax-certificate-saved');

    expect(DB::table('data_tax')->where('cid', '1234567890123')->where('income_year', 2567)->value('running_code1'))
        ->toBe('TC-2567-UPDATED')
        ->and((float) DB::table('data_tax')->where('cid', '1234567890123')->where('income_year', 2567)->value('income_money'))
        ->toBe(42000.5);

    $component->call('edit', '1234567890123', 2567)
        ->call('confirmDelete')
        ->assertDispatched('tax-certificate-delete-confirm-opened')
        ->call('delete')
        ->assertHasNoErrors()
        ->assertDispatched('tax-certificate-delete-confirm-closed')
        ->assertDispatched('tax-certificate-deleted');

    expect(DB::table('data_tax')->where('cid', '1234567890123')->where('income_year', 2567)->exists())->toBeFalse();
});

// Production break caught: accepting malformed identity, tax year, or money data would persist a certificate that cannot safely map to its source record.
test('manual certificate validation rejects malformed cid tax year and money values', function () {
    Livewire::actingAs(taxManagementActor('FINANCE'))
        ->test(TaxCertificateManagement::class)
        ->call('openCreateModal')
        ->set('cid', '123')
        ->set('income_year', '256')
        ->set('running_code1', 'TC-BAD')
        ->set('income_type_id', '2')
        ->set('outcomeType', '1')
        ->set('tax_type_id', '3')
        ->set('date_report', '29012568')
        ->set('income_money', '-1.00')
        ->call('save')
        ->assertHasErrors(['cid', 'income_year', 'income_money']);

    expect(DB::table('data_tax')->count())->toBe(0);
});

// Production break caught: an insert query fault must not escape with SQL and bound certificate data; the operator receives only a generic save error.
test('manual certificate insert failures return a generic component error', function () {
    DB::unprepared("\n        CREATE TRIGGER reject_tax_certificate_insert\n        BEFORE INSERT ON data_tax\n        BEGIN\n            SELECT RAISE(ABORT, 'tax-certificate-insert-fault');\n        END;\n    ");

    $component = Livewire::actingAs(taxManagementActor('FINANCE'))
        ->test(TaxCertificateManagement::class)
        ->call('openCreateModal');

    foreach (taxManagementRecord() as $field => $value) {
        if (! in_array($field, ['income_type_name', 'outcomeName', 'tax_type_name', 'r_update'], true)) {
            $component->set($field, (string) $value);
        }
    }

    $component->call('save')
        ->assertHasErrors('form')
        ->assertSee('ไม่สามารถบันทึกใบรับรองภาษีได้')
        ->assertDontSee('tax-certificate-insert-fault');

    expect(DB::table('data_tax')->count())->toBe(0);
});

// Production break caught: a delete query fault must not escape with SQL and bound certificate data; the operator receives only a generic delete error.
test('tax certificate delete failures return a generic component error', function () {
    DB::table('data_tax')->insert(taxManagementRecord());
    DB::unprepared("\n        CREATE TRIGGER reject_tax_certificate_delete\n        BEFORE DELETE ON data_tax\n        BEGIN\n            SELECT RAISE(ABORT, 'tax-certificate-delete-fault');\n        END;\n    ");

    Livewire::actingAs(taxManagementActor('FINANCE'))
        ->test(TaxCertificateManagement::class)
        ->call('confirmDelete', '1234567890123', 2567)
        ->call('delete')
        ->assertHasErrors('delete')
        ->assertSee('ไม่สามารถลบใบรับรองภาษีได้')
        ->assertDontSee('tax-certificate-delete-fault');

    expect(DB::table('data_tax')->where('cid', '1234567890123')->where('income_year', 2567)->exists())->toBeTrue();
});

// Production break caught: accepting a third fractional digit rounds or truncates a tax amount after validation instead of rejecting the invalid source value.
test('manual certificate rejects more than two fractional digits for every monetary input', function (string $field, string $cid) {
    $component = Livewire::actingAs(taxManagementActor('FINANCE'))
        ->test(TaxCertificateManagement::class)
        ->call('openCreateModal');

    foreach (taxManagementRecord(['cid' => $cid]) as $recordField => $value) {
        if (! in_array($recordField, ['income_type_name', 'outcomeName', 'tax_type_name', 'r_update'], true)) {
            $component->set($recordField, (string) $value);
        }
    }

    $component->set($field, '0.009')
        ->call('save')
        ->assertHasErrors($field);

    expect(DB::table('data_tax')->where('cid', $cid)->exists())->toBeFalse();
})->with([
    'income amount' => ['income_money', '1111111111111'],
    'withholding tax' => ['withholding_tax', '2222222222222'],
    'total amount' => ['total_money', '3333333333333'],
    'tax total' => ['sumTaxAmount', '4444444444444'],
    'fund amount' => ['outcomeAmount', '5555555555555'],
]);

// Production break caught: reading a legacy table whose physical column is CID must still load the record for an edit action that receives the canonical lowercase identity.
test('edit normalizes the legacy uppercase CID column name', function () {
    Schema::dropIfExists('data_tax');
    Schema::create('data_tax', function (Blueprint $table) {
        $table->string('CID', 13);
        $table->unsignedInteger('income_year');
        $table->string('running_code1')->nullable();
        $table->string('fullname')->nullable();
        $table->decimal('income_money', 15, 2)->nullable();
        $table->decimal('withholding_tax', 15, 2)->nullable();
        $table->string('outcomeName')->nullable();
        $table->decimal('outcomeAmount', 15, 2)->nullable();
        $table->string('date_report')->nullable();
        $table->unique(['CID', 'income_year']);
    });
    DB::table('data_tax')->insert([
        'CID' => '6666666666666',
        'income_year' => 2567,
        'running_code1' => 'LEGACY-CID',
    ]);

    Livewire::actingAs(taxManagementActor('FINANCE'))
        ->test(TaxCertificateManagement::class)
        ->call('edit', '6666666666666', 2567)
        ->assertSet('editingCid', '6666666666666')
        ->assertSet('cid', '6666666666666')
        ->assertSet('running_code1', 'LEGACY-CID');
});

// Production break caught: filtering the list by another year, CID, or name must not expose an unrelated certificate as a matching search result.
test('finance filters certificates by income year cid and name', function () {
    DB::table('data_tax')->insert([
        taxManagementRecord(['cid' => '1111111111111', 'income_year' => 2567, 'running_code1' => 'YEAR-2567', 'fullname' => 'สมชาย ตรงปี']),
        taxManagementRecord(['cid' => '2222222222222', 'income_year' => 2566, 'running_code1' => 'CID-2222', 'fullname' => 'สมหญิง ตรงบัตร']),
        taxManagementRecord(['cid' => '3333333333333', 'income_year' => 2565, 'running_code1' => 'NAME-SEARCH', 'fullname' => 'ค้นหาตามชื่อ']),
    ]);

    $component = Livewire::actingAs(taxManagementActor('FINANCE'))->test(TaxCertificateManagement::class);

    $component->set('yearFilter', '2567')
        ->assertSee('YEAR-2567')
        ->assertDontSee('CID-2222')
        ->set('yearFilter', '')
        ->set('cidFilter', '2222')
        ->assertSee('CID-2222')
        ->assertDontSee('YEAR-2567')
        ->set('cidFilter', '')
        ->set('nameFilter', 'ค้นหาตามชื่อ')
        ->assertSee('NAME-SEARCH')
        ->assertDontSee('YEAR-2567');
});

// Production break caught: an account whose role changes after the component loads must not retain a direct Livewire write capability.
test('unauthorized roles are rejected by direct management actions', function () {
    $actor = taxManagementActor('FINANCE');
    $component = Livewire::actingAs($actor)->test(TaxCertificateManagement::class)->assertOk();

    $actor->role = 'LEAVE';

    $component->call('openCreateModal')->assertForbidden();
    expect(DB::table('data_tax')->count())->toBe(0);
});

// Production break caught: using a different encoding for confirm than preview can silently store corrupted text, while cancellation must not write previewed rows.
test('utf8 TXT preview keeps masked diagnostics and confirmation commits only after explicit confirmation', function () {
    $invalidCid = '123456789012';
    $contents = implode("\n", [
        taxManagementTxtRecord([1 => '¡', 4 => '4444444444444', 6 => 'อักษร UTF-8']),
        taxManagementTxtRecord([4 => $invalidCid]),
    ])."\n";

    $component = Livewire::actingAs(taxManagementActor('FINANCE'))
        ->test(TaxCertificateManagement::class)
        ->assertSet('importEncoding', 'tis-620')
        ->set('importEncoding', 'utf-8')
        ->set('importFile', taxManagementTxtUpload($contents))
        ->call('previewImport')
        ->assertHasNoErrors()
        ->assertSet('importPreviewReady', true)
        ->assertSee('1234')
        ->assertDontSee($invalidCid)
        ->assertSeeHtml("window.portalModal.show('taxCertificateImportConfirmModal')");

    expect($component->get('importPreview'))->toMatchArray([
        'read' => 2,
        'valid' => 1,
        'skipped' => 1,
    ])->and(DB::table('data_tax')->count())->toBe(0);

    $component->call('closeModal')
        ->assertSet('importFile', null)
        ->assertSet('importPreviewReady', false)
        ->assertDispatched('tax-certificate-import-confirm-closed');

    expect(DB::table('data_tax')->count())->toBe(0);

    $component->set('importEncoding', 'utf-8')
        ->set('importFile', taxManagementTxtUpload($contents))
        ->call('previewImport')
        ->assertSet('importPreviewReady', true)
        ->call('confirmImport')
        ->assertHasNoErrors()
        ->assertDispatched('tax-certificate-import-confirm-closed')
        ->assertDispatched('tax-certificate-import-saved');

    expect($component->get('importResult'))->toMatchArray([
        'read' => 2,
        'inserted' => 1,
        'updated' => 0,
        'skipped' => 1,
        'failed' => 0,
    ])->and(DB::table('data_tax')->where('cid', '4444444444444')->where('income_year', 2567)->value('department_name'))
        ->toBe('¡')
        ->and(DB::table('data_tax')->where('cid', $invalidCid)->exists())
        ->toBeFalse();
});

// Production break caught: changing the charset after preview can make confirmation decode the same source bytes differently and persist corrupted text.
test('changing the selected import encoding after preview requires a new preview', function () {
    $component = Livewire::actingAs(taxManagementActor('FINANCE'))
        ->test(TaxCertificateManagement::class)
        ->set('importEncoding', 'utf-8')
        ->set('importFile', taxManagementTxtUpload(taxManagementTxtRecord([
            1 => '¡',
            4 => '5555555555555',
        ])."\n"))
        ->call('previewImport')
        ->assertSet('importPreviewReady', true);

    $component->set('importEncoding', 'tis-620')
        ->call('confirmImport')
        ->assertHasErrors('importFile')
        ->assertSet('importPreviewReady', false);

    expect(DB::table('data_tax')->count())->toBe(0);
});

// Production break caught: opening delete confirmation from the edit form leaves both dialogs active instead of replacing the form with a single confirmation dialog.
test('the edit delete control replaces the form modal with the delete confirmation', function () {
    DB::table('data_tax')->insert(taxManagementRecord());

    Livewire::actingAs(taxManagementActor('FINANCE'))
        ->test(TaxCertificateManagement::class)
        ->call('edit', '1234567890123', 2567)
        ->assertSeeHtml("wire:click=\"confirmDelete\" onclick=\"window.portalModal.hide(document.getElementById('taxCertificateFormModal')); window.portalModal.show('taxCertificateDeleteModal')\"");
});

// Production break caught: a successful delete can leave either the edit or confirmation dialog open when its close events do not precede the toast event.
test('delete confirmation and success close every tax certificate dialog before the toast', function () {
    DB::table('data_tax')->insert(taxManagementRecord());

    $component = Livewire::actingAs(taxManagementActor('FINANCE'))
        ->test(TaxCertificateManagement::class)
        ->call('edit', '1234567890123', 2567)
        ->call('confirmDelete')
        ->assertDispatched('tax-certificate-form-closed')
        ->assertDispatched('tax-certificate-delete-confirm-opened');

    expect(array_column($component->effects['dispatches'], 'name'))->toBe([
        'tax-certificate-form-closed',
        'tax-certificate-delete-confirm-opened',
    ]);

    $component->call('delete')
        ->assertHasNoErrors()
        ->assertDispatched('tax-certificate-form-closed')
        ->assertDispatched('tax-certificate-delete-confirm-closed')
        ->assertDispatched('tax-certificate-deleted');

    expect(array_column($component->effects['dispatches'], 'name'))->toBe([
        'tax-certificate-form-closed',
        'tax-certificate-delete-confirm-closed',
        'tax-certificate-deleted',
    ])->and(DB::table('data_tax')->where('cid', '1234567890123')->where('income_year', 2567)->exists())
        ->toBeFalse();
});

// Production break caught: omitting portal-modal close and toast handlers leaves successful mutations invisible or traps the user in the dialog.
test('management view uses application modals and toast events for successful mutations', function () {
    $html = Livewire::actingAs(taxManagementActor('FINANCE'))->test(TaxCertificateManagement::class)->html();
    $view = file_get_contents(resource_path('views/livewire/admin/tax-certificate-management.blade.php'));

    expect($view)
        ->toContain("window.portalModal.show('taxCertificateFormModal')")
        ->toContain("window.portalModal.show('taxCertificateImportConfirmModal')")
        ->toContain('window.portalToast.show(message)');

    expect($html)
        ->toContain('data-modal-close')
        ->toContain('value="tis-620"')
        ->toContain('value="utf-8"');
});

function seedTaxCertificatesInTwoOffices(): void
{
    DB::table('person')->insert([
        ['cid' => '1111111111111', 'fname' => 'บุคลากรหน่วยหนึ่ง', 'office_id' => '01'],
        ['cid' => '2222222222222', 'fname' => 'บุคลากรหน่วยสอง', 'office_id' => '02'],
    ]);
    DB::table('data_tax')->insert([
        taxManagementRecord(['cid' => '1111111111111', 'running_code1' => 'TC-OFFICE-01']),
        taxManagementRecord(['cid' => '2222222222222', 'running_code1' => 'TC-OFFICE-02']),
    ]);
}

test('ADMIN and FINANCE in office 00 see tax certificates of every office', function (string $role, ?string $office) {
    seedTaxCertificatesInTwoOffices();

    Livewire::actingAs(taxManagementActor($role, $office))
        ->test(TaxCertificateManagement::class)
        ->assertSee('TC-OFFICE-01')
        ->assertSee('TC-OFFICE-02');
})->with([
    'ADMIN without an office' => ['ADMIN', null],
    'ADMIN of another office' => ['ADMIN', '01'],
    'FINANCE of office 00' => ['FINANCE', '00'],
]);

test('FINANCE of another office sees only tax certificates of their own office', function () {
    seedTaxCertificatesInTwoOffices();

    Livewire::actingAs(taxManagementActor('FINANCE', '01'))
        ->test(TaxCertificateManagement::class)
        ->assertSee('TC-OFFICE-01')
        ->assertDontSee('TC-OFFICE-02');
});

test('FINANCE without an office sees no tax certificates', function () {
    seedTaxCertificatesInTwoOffices();

    Livewire::actingAs(taxManagementActor('FINANCE', null))
        ->test(TaxCertificateManagement::class)
        ->assertDontSee('TC-OFFICE-01')
        ->assertDontSee('TC-OFFICE-02');
});

test('FINANCE of another office cannot edit, delete, or add certificates for another office', function () {
    seedTaxCertificatesInTwoOffices();
    $finance = taxManagementActor('FINANCE', '01');

    Livewire::actingAs($finance)->test(TaxCertificateManagement::class)
        ->call('edit', '2222222222222', 2567)->assertNotFound();
    Livewire::actingAs($finance)->test(TaxCertificateManagement::class)
        ->call('confirmDelete', '2222222222222', 2567)->assertNotFound();

    $component = Livewire::actingAs($finance)->test(TaxCertificateManagement::class);
    foreach (taxManagementRecord(['cid' => '2222222222222', 'income_year' => 2568]) as $field => $value) {
        if (property_exists(TaxCertificateManagement::class, $field)) {
            $component->set($field, (string) $value);
        }
    }
    $component->call('save')->assertHasErrors('cid');

    expect(DB::table('data_tax')->where('cid', '2222222222222')->count())->toBe(1);
});

test('an office-scoped TXT import skips personnel outside the importing office', function () {
    DB::table('person')->insert([
        ['cid' => '1111111111111', 'office_id' => '01'],
        ['cid' => '2222222222222', 'office_id' => '02'],
    ]);
    $contents = implode("\n", [
        taxManagementTxtRecord([4 => '1111111111111']),
        taxManagementTxtRecord([4 => '2222222222222']),
        taxManagementTxtRecord([4 => '3333333333333']),
    ])."\n";

    $component = Livewire::actingAs(taxManagementActor('FINANCE', '01'))
        ->test(TaxCertificateManagement::class)
        ->set('importEncoding', 'utf-8')
        ->set('importFile', taxManagementTxtUpload($contents))
        ->call('previewImport')
        ->assertSet('importPreviewReady', true)
        ->assertSee('ข้าม ไม่ใช่บุคลากรในหน่วยงาน')
        ->assertDontSee('2222222222222');

    expect($component->get('importPreview'))->toMatchArray(['read' => 3, 'valid' => 1, 'skipped' => 2]);

    $component->call('confirmImport')
        ->assertHasNoErrors()
        ->assertDispatched('tax-certificate-import-saved', message: 'นำเข้าใบรับรองภาษีแล้ว เพิ่มใหม่ 1 ปรับปรุง 0 ข้าม 2 รายการ');

    expect(DB::table('data_tax')->pluck('cid')->all())->toBe(['1111111111111']);
});
