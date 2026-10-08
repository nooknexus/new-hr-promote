<?php
// Isolated InnoDB proof for actual manual save versus actual Excel import.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Livewire\Admin\LeaveManagement;
use App\Services\LeaveExcelImportService;
$original = config('database.connections.mysql');
if (!app()->environment('local') || !in_array($original['host'], ['127.0.0.1','localhost'])) throw new RuntimeException('Local loopback required');
$prefix='codex_leave_probe_'.bin2hex(random_bytes(6)).'_';
config(['database.default'=>'mysql','database.connections.mysql.prefix'=>$prefix,'cache.default'=>'array']);
DB::purge('mysql');
$actor=new User; $actor->forceFill(['id'=>10,'name'=>'Fixture','role'=>'ADMIN','is_active'=>true]); $actor->exists=true;
$path=tempnam(sys_get_temp_dir(),'leave-concurrency-'); $pid=null; $exit=1;
try {
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

    DB::table('person')->insert(['cid'=>'0123456789012','pname'=>'นาย','fname'=>'Fixture','lname'=>'Test','office_id'=>'01']);
    DB::table('leave_types')->insert(['name'=>'ลาป่วย','code'=>'SICK']);
    $book=new PhpOffice\PhpSpreadsheet\Spreadsheet;
    $book->getActiveSheet()->fromArray([['CID','Type','Start','End','Duration','Reason'],['0123456789012','ลาป่วย','2026-09-01','2026-09-02','เต็มวัน','fixture']],null,'A1');
    $book->getActiveSheet()->setCellValueExplicit('A2','0123456789012',PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path); $book->disconnectWorksheets();
    Illuminate\Support\Facades\Http::fake(['*'=>Illuminate\Support\Facades\Http::response('',503)]);
    $sockets=stream_socket_pair(STREAM_PF_UNIX,STREAM_SOCK_STREAM,0);
    DB::disconnect('mysql'); $pid=pcntl_fork();
    if ($pid===-1) throw new RuntimeException('Fork failed');
    if ($pid===0) {
        fclose($sockets[0]); DB::purge('mysql'); fread($sockets[1],1);
        try {
            DB::statement('SET SESSION innodb_lock_wait_timeout=10');
            app(LeaveExcelImportService::class)->import($path,$actor); exit(2);
        } catch (RuntimeException $error) { exit(str_contains($error->getMessage(),'ทับช่วงวันที่') ? 0 : 3); }
        catch (Throwable) { exit(4); }
    }
    fclose($sockets[1]); DB::purge('mysql'); DB::beginTransaction();
    DB::table('person')->where('cid','0123456789012')->lockForUpdate()->first();
    fwrite($sockets[0],'S');
    $waiting=false; $until=microtime(true)+5;
    while (microtime(true)<$until) {
        foreach (DB::select('SHOW FULL PROCESSLIST') as $process) {
            $sql=(string)($process->Info ?? '');
            if (str_starts_with(strtolower($sql),'select') && str_contains($sql,$prefix.'person') && str_contains(strtolower($sql),'for update')) $waiting=true;
        }
        if ($waiting) break;
        usleep(10000);
    }
    if (!$waiting) throw new RuntimeException('Importer lock wait not observed');
    Auth::setUser($actor);
    $component=app(LeaveManagement::class);
    $component->cid='0123456789012'; $component->personName='นาย Fixture Test'; $component->leave_type_id=1;
    $component->start_date='2026-09-01'; $component->end_date='2026-09-02'; $component->reason='fixture';
    $component->save();
    // Use a different reason in the importer so this is an overlap, not an exact duplicate.
    DB::table('leave_requests')->update(['reason'=>'manual fixture']);
    DB::commit(); pcntl_waitpid($pid,$status); $pid=null;
    if (pcntl_wexitstatus($status)!==0 || DB::table('leave_requests')->count()!==1) throw new RuntimeException('Overlap serialization failed');
    echo "PASS: observed native InnoDB wait; actual manual save commits, actual importer refuses overlapping insert; one row remains\n";
    // Numeric Excel names must never coerce Thai VARCHAR leave types to zero in MySQL.
    $book=PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $book->getSheet(0)->setCellValue('B2',0)->setCellValue('C2','2026-09-03')->setCellValue('D2','2026-09-03');
    (new PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path); $book->disconnectWorksheets();
    $rejected=false;
    try { app(LeaveExcelImportService::class)->import($path,$actor); }
    catch (RuntimeException $error) { $rejected=str_contains($error->getMessage(),'ประเภทการลา'); }
    if (!$rejected || DB::table('leave_requests')->count()!==1) throw new RuntimeException('Numeric type accepted by native MySQL');
    echo "PASS: numeric leave type rejected by actual importer on native MySQL; existing row unchanged\n"; $exit=0;
} catch (Throwable $error) { fwrite(STDERR,'FAIL: '.get_class($error)."\n"); }
finally {
    if (DB::transactionLevel()) DB::rollBack();
    if ($pid) pcntl_waitpid($pid,$status);
    if (config('database.connections.mysql.prefix')!==$prefix || !preg_match('/^codex_leave_probe_[a-f0-9]{12}_$/',$prefix)) throw new RuntimeException('Cleanup scope mismatch');
    foreach (['leave_requests','leave_types','person'] as $table) Schema::dropIfExists($table);
    @unlink($path);
}
exit($exit);
