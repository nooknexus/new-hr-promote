<?php

use App\Services\PersonnelMatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    Schema::create('person', function ($table) {
        $table->string('cid', 13)->primary();
        $table->string('pname')->nullable();
        $table->string('fname')->nullable();
        $table->string('lname')->nullable();
    });
});

test('it matches a leave record to personnel by CID from the person table', function () {
    DB::table('person')->insert([
        'cid' => '1234567890123',
        'pname' => 'นาง',
        'fname' => 'ทดสอบ',
        'lname' => 'บุคลากร',
    ]);

    $name = (new PersonnelMatcher)->findDisplayNameByCid('1234567890123');

    expect($name)->toBe('นาง ทดสอบ บุคลากร');
});
