<?php
// Disposable MySQL concurrency probe; never uses real personnel/account tables.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\SsoUserProvisioner;
$original = config('database.connections.mysql');
if (! app()->environment('local') || ! in_array($original['host'], ['127.0.0.1', 'localhost'])) throw new RuntimeException('Local loopback required');
$prefix = 'codex_access_probe_'.bin2hex(random_bytes(6)).'_';
config(['database.default' => 'mysql', 'database.connections.mysql.prefix' => $prefix]);
DB::purge('mysql');
$exit = 1; $pid = null;
try {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('name_en')->nullable();
        $table->string('email')->unique();
        $table->string('password');
        $table->string('pid', 13)->nullable()->unique();
        $table->string('thaid_pid', 13)->nullable()->unique();
        $table->string('cid_hash')->nullable()->unique();
        $table->string('provider_id')->nullable()->unique();
        $table->string('account_id')->nullable()->unique();
        $table->string('role')->nullable();
        $table->string('office')->nullable();
        $table->boolean('is_active')->default(true);
        $table->string('hcode')->nullable();
        $table->string('hname')->nullable();
        $table->string('hospcode')->nullable();
        $table->text('address')->nullable();
        $table->date('birthdate')->nullable();
        $table->timestamp('email_verified_at')->nullable();
        $table->timestamp('last_login_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('office_id')->nullable();
        $table->string('office_name')->nullable();
    });

    DB::table('users')->insert(['name'=>'Fixture', 'email'=>'fixture@example.test', 'password'=>'fixture', 'pid'=>'1234567890123', 'is_active'=>true]);
    DB::table('person')->insert(['cid'=>'1234567890123']);
    $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    // Never share a live PDO across fork.
    DB::disconnect('mysql');
    $pid = pcntl_fork();
    if ($pid === 0) {
        fclose($sockets[0]); DB::purge('mysql');
        fread($sockets[1], 1);
        $signalled = false;
        DB::listen(function ($query) use (&$signalled, $sockets, $prefix) {
            if (! $signalled && str_starts_with($query->sql, 'select') && str_contains($query->sql, $prefix.'users')) {
                $signalled = true; fwrite($sockets[1], 'R');
            }
        });
        try {
            DB::statement('SET SESSION innodb_lock_wait_timeout = 3');
            $user = app(SsoUserProvisioner::class)->provision('1234567890123', ['name'=>'Refreshed fixture']);
            exit($user !== null ? 0 : 2);
        } catch (Throwable $error) { fwrite(STDERR, 'SSO failed: '.get_class($error).'
'); exit(3); }
    }
    fclose($sockets[1]); DB::purge('mysql');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 3');
    DB::beginTransaction();
    // Exact unchanged SalaryPromotionImportService person -> users lock sequence.
    DB::table('person')->where('cid', '1234567890123')->lockForUpdate()->first(['cid']);
    fwrite($sockets[0], 'S'); stream_set_timeout($sockets[0], 6);
    if (fread($sockets[0], 1) !== 'R') throw new RuntimeException('Worker read signal missing');
    DB::table('users')->where('pid', '1234567890123')->orWhere('thaid_pid', '1234567890123')->lockForUpdate()->get(['id']);
    DB::commit();
    pcntl_waitpid($pid, $status); $pid = null;
    if (pcntl_wexitstatus($status) !== 0) throw new RuntimeException('SSO worker failed');
    if (DB::table('users')->value('name') !== 'Refreshed fixture') throw new RuntimeException('Refresh missing');
    echo "PASS: concurrent import lock sequence and real SSO both commit on isolated InnoDB fixtures\n"; $exit=0;
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: '.get_class($error)."\n");
} finally {
    if (DB::transactionLevel()) DB::rollBack();
    if ($pid) pcntl_waitpid($pid, $status);
    if (config('database.connections.mysql.prefix') !== $prefix || ! preg_match('/^codex_access_probe_[a-f0-9]{12}_$/', $prefix)) throw new RuntimeException('Cleanup scope mismatch');
    Schema::dropIfExists('users'); Schema::dropIfExists('person');
}
exit($exit);
