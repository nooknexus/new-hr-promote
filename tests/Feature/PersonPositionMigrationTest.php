<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('person position migration preserves existing rows and leading zeroes and reverses cleanly', function () {
    Schema::create('person', function (Blueprint $table) {
        $table->string('cid', 13)->primary();
        $table->string('fname')->nullable();
    });
    DB::table('person')->insert(['cid' => '1234567890123', 'fname' => 'ทดสอบ']);

    $migration = require database_path('migrations/2026_10_01_100200_add_no_position_to_person_table.php');
    $migration->up();
    DB::table('person')->where('cid', '1234567890123')->update(['no_position' => '01พล00139']);

    expect(Schema::hasColumn('person', 'no_position'))->toBeTrue()
        ->and(DB::table('person')->where('cid', '1234567890123')->value('no_position'))->toBe('01พล00139')
        ->and(DB::table('person')->where('cid', '1234567890123')->value('fname'))->toBe('ทดสอบ');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class);
    DB::table('person')->where('cid', '1234567890123')->update(['no_position' => null]);
    $migration->down();
    expect(Schema::hasColumn('person', 'no_position'))->toBeFalse()
        ->and(DB::table('person')->where('cid', '1234567890123')->value('fname'))->toBe('ทดสอบ');
});
