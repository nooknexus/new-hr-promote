<?php

use App\Services\SalaryPromotionImportService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
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
        $table->unique(['CID', 'evaluation_date']);
    });
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('no_position')->nullable();
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
});

function seedPromotionIdentity(): void
{
    DB::table('person')->insert(['cid' => '3650200600388', 'no_position' => 'เก่า-บุคลากร']);
    DB::table('users')->insert([
        'id' => 5, 'pid' => '3650200600388', 'thaid_pid' => '3650200600388',
        'no_position' => 'เก่า-ผู้ใช้',
    ]);
}

function confirmPromotionWorkbook(SalaryPromotionWorkbook $file): array
{
    $service = app(SalaryPromotionImportService::class);

    return $service->commit($file->path, 9, $service->preview($file->path)['baseline_hash']);
}

test('confirmed import inserts workbook salary amounts and updates both matched position fields with an audit', function () {
    seedPromotionIdentity();
    $file = new SalaryPromotionWorkbook;
    $result = confirmPromotionWorkbook($file);

    $promotion = DB::table('data_promote')->first();
    $audit = DB::table('data_promote_audits')->first();
    expect($result)->toMatchArray([
        'insert_count' => 1, 'update_count' => 0,
        'unmatched_person_count' => 0, 'unmatched_user_count' => 0,
    ]);
    expect($promotion->CID)->toBe('3650200600388')
        ->and($promotion->sum)->toBe('610.00')
        ->and($promotion->new_salary)->toBe('14390.00')
        ->and(DB::table('person')->value('no_position'))->toBe('01พล00139')
        ->and(DB::table('users')->value('no_position'))->toBe('01พล00139')
        ->and($audit->actor_id)->toBe(9)
        ->and($audit->action)->toBe('imported_created')
        ->and(json_decode($audit->before_data, true)['person_no_position'])->toBe('เก่า-บุคลากร')
        ->and(json_decode($audit->before_data, true)['user_no_position'])->toBe('เก่า-ผู้ใช้')
        ->and(json_decode($audit->after_data, true)['person_no_position'])->toBe('01พล00139')
        ->and(json_decode($audit->after_data, true)['user_no_position'])->toBe('01พล00139');
});

test('matching active promotion is updated rather than duplicated', function () {
    DB::table('data_promote')->insert([
        'id' => 3, 'CID' => '3650200600388', 'evaluation_date' => '2022-10-01',
        'sum' => '500.00', 'new_salary' => '14280.00',
    ]);
    $file = new SalaryPromotionWorkbook;

    expect(confirmPromotionWorkbook($file)['update_count'])->toBe(1)
        ->and(DB::table('data_promote')->count())->toBe(1)
        ->and(DB::table('data_promote')->where('id', 3)->value('sum'))->toBe('610.00')
        ->and(DB::table('data_promote_audits')->value('action'))->toBe('imported_updated');
});

test('two evaluation periods for one person import without a false baseline conflict', function () {
    seedPromotionIdentity();
    $later = SalaryPromotionWorkbook::ROW;
    $later[2] = '2023-04-01';
    $file = new SalaryPromotionWorkbook([SalaryPromotionWorkbook::ROW, $later]);

    $result = confirmPromotionWorkbook($file);

    expect($result['insert_count'])->toBe(2)
        ->and(DB::table('data_promote')->count())->toBe(2)
        ->and(DB::table('person')->value('no_position'))->toBe('01พล00139')
        ->and(DB::table('users')->value('no_position'))->toBe('01พล00139')
        ->and(DB::table('data_promote_audits')->count())->toBe(2);
});

test('confirmation rejects a promotion inserted after the preview was shown', function () {
    $file = new SalaryPromotionWorkbook;
    $service = app(SalaryPromotionImportService::class);
    $baseline = $service->preview($file->path)['baseline_hash'];
    DB::table('data_promote')->insert([
        'CID' => '3650200600388', 'evaluation_date' => '2022-10-01',
        'sum' => '700.00', 'new_salary' => '14480.00',
    ]);

    expect(fn () => $service->commit($file->path, 9, $baseline))->toThrow(ValidationException::class);
    expect(DB::table('data_promote')->value('new_salary'))->toBe('14480.00')
        ->and(DB::table('data_promote_audits')->count())->toBe(0);
});

test('confirmation rejects a promotion or position changed after preview', function () {
    seedPromotionIdentity();
    DB::table('data_promote')->insert([
        'CID' => '3650200600388', 'evaluation_date' => '2022-10-01',
        'sum' => '500.00', 'new_salary' => '14280.00',
    ]);
    $file = new SalaryPromotionWorkbook;
    $service = app(SalaryPromotionImportService::class);
    $baseline = $service->preview($file->path)['baseline_hash'];
    DB::table('person')->update(['no_position' => 'ใหม่จากแอดมินอื่น']);

    expect(fn () => $service->commit($file->path, 9, $baseline))->toThrow(ValidationException::class);
    expect(DB::table('person')->value('no_position'))->toBe('ใหม่จากแอดมินอื่น')
        ->and(DB::table('data_promote')->value('sum'))->toBe('500.00')
        ->and(DB::table('data_promote_audits')->count())->toBe(0);
});

test('missing person and user do not block promotion import or create identities', function () {
    $file = new SalaryPromotionWorkbook;
    $result = confirmPromotionWorkbook($file);

    expect($result['unmatched_person_count'])->toBe(1)
        ->and($result['unmatched_user_count'])->toBe(1)
        ->and(DB::table('data_promote')->count())->toBe(1)
        ->and(DB::table('person')->count())->toBe(0)
        ->and(DB::table('users')->count())->toBe(0);
});

test('person position updates even when no user account matches', function () {
    DB::table('person')->insert(['cid' => '3650200600388', 'no_position' => 'เดิม']);
    $file = new SalaryPromotionWorkbook;
    $result = confirmPromotionWorkbook($file);

    expect($result['unmatched_person_count'])->toBe(0)
        ->and($result['unmatched_user_count'])->toBe(1)
        ->and(DB::table('person')->value('no_position'))->toBe('01พล00139')
        ->and(DB::table('users')->count())->toBe(0);
});

test('user position updates even when no personnel row matches', function () {
    DB::table('users')->insert(['id' => 5, 'thaid_pid' => '3650200600388', 'no_position' => 'เดิม']);
    $file = new SalaryPromotionWorkbook;
    $result = confirmPromotionWorkbook($file);

    expect($result['unmatched_person_count'])->toBe(1)
        ->and($result['unmatched_user_count'])->toBe(0)
        ->and(DB::table('users')->value('no_position'))->toBe('01พล00139')
        ->and(DB::table('person')->count())->toBe(0);
});

test('blank position cell leaves both existing position values unchanged', function () {
    seedPromotionIdentity();
    $row = SalaryPromotionWorkbook::ROW;
    $row[4] = '';
    $file = new SalaryPromotionWorkbook([$row]);
    confirmPromotionWorkbook($file);

    expect(DB::table('person')->value('no_position'))->toBe('เก่า-บุคลากร')
        ->and(DB::table('users')->value('no_position'))->toBe('เก่า-ผู้ใช้');
});

test('ambiguous user match rejects the entire file', function () {
    seedPromotionIdentity();
    DB::table('users')->insert(['id' => 6, 'thaid_pid' => '3650200600388']);
    $file = new SalaryPromotionWorkbook;
    expect(fn () => confirmPromotionWorkbook($file))
        ->toThrow(ValidationException::class);
    expect(DB::table('data_promote')->count())->toBe(0);
});

test('newly archived target after preview rejects the entire file', function () {
    seedPromotionIdentity();
    $file = new SalaryPromotionWorkbook;
    $baseline = app(SalaryPromotionImportService::class)->preview($file->path)['baseline_hash'];
    DB::table('data_promote')->insert([
        'CID' => '3650200600388', 'evaluation_date' => '2022-10-01',
        'deleted_at' => now(),
    ]);
    expect(fn () => app(SalaryPromotionImportService::class)->commit($file->path, 9, $baseline))
        ->toThrow(ValidationException::class);
    expect(DB::table('person')->value('no_position'))->toBe('เก่า-บุคลากร');
});

test('a late audit failure rolls back promotion and both position updates', function () {
    seedPromotionIdentity();
    $file = new SalaryPromotionWorkbook;
    Schema::drop('data_promote_audits');

    expect(fn () => confirmPromotionWorkbook($file))
        ->toThrow(QueryException::class);
    expect(DB::table('data_promote')->count())->toBe(0)
        ->and(DB::table('person')->value('no_position'))->toBe('เก่า-บุคลากร')
        ->and(DB::table('users')->value('no_position'))->toBe('เก่า-ผู้ใช้');
});
