<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        // Feature tests build their own legacy tables; the office registry is
        // shared, so every test starts with an empty offices table.
        if (! \Illuminate\Support\Facades\Schema::hasTable('offices')) {
            \Illuminate\Support\Facades\Schema::create('offices', function ($table) {
                $table->string('code', 2)->primary();
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
        if (! \Illuminate\Support\Facades\Schema::hasTable('user_personnel_types')) {
            \Illuminate\Support\Facades\Schema::create('user_personnel_types', function ($table) {
                $table->unsignedBigInteger('user_id');
                $table->unsignedTinyInteger('type_id');
                $table->primary(['user_id', 'type_id']);
            });
        }
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/** Register offices the way the create_offices_table migration does: from person.office_id/office_name. */
function syncOfficesFromPerson(): void
{
    $name = \Illuminate\Support\Facades\Schema::hasColumn('person', 'office_name') ? 'MAX(office_name)' : 'NULL';

    foreach (\Illuminate\Support\Facades\DB::table('person')->whereNotNull('office_id')->where('office_id', '!=', '')
        ->groupBy('office_id')->selectRaw("office_id, {$name} AS office_name")->get() as $office) {
        \Illuminate\Support\Facades\DB::table('offices')->updateOrInsert(
            ['code' => (string) $office->office_id],
            ['name' => trim((string) $office->office_name) ?: (string) $office->office_id],
        );
    }
}

/** @param array<string, string> $offices code => name */
function seedOffices(array $offices, bool $active = true): void
{
    foreach ($offices as $code => $name) {
        \Illuminate\Support\Facades\DB::table('offices')->insertOrIgnore(['code' => (string) $code, 'name' => $name, 'is_active' => $active]);
    }
}

/** @param list<int> $types person.type_id values the user is responsible for on salary promotions */
function assignPersonnelTypes(int $userId, array $types): void
{
    foreach ($types as $type) {
        \Illuminate\Support\Facades\DB::table('user_personnel_types')->insertOrIgnore(['user_id' => $userId, 'type_id' => $type]);
    }
}
