<?php

use App\Models\User;
use Illuminate\Support\Facades\Blade;

test('the login page loads the modern application asset', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('resources/css/app.css', false)
        ->assertDontSee('sb-admin-2.min.css', false);
});

test('an authenticated user receives accessible desktop and mobile navigation', function () {
    $this->actingAs(new User(['name' => 'Test User']));

    expect(Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>'))
        ->toContain('aria-label="เมนูหลัก"')
        ->toContain('data-mobile-menu-button');
});

test('sidebar separates user and administrator navigation for administrators', function () {
    $admin = new User(['name' => 'Admin User']);
    $admin->forceFill(['staff_type' => 'ADMIN', 'role' => 'SUPERADMIN']);
    $this->actingAs($admin);

    expect(Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>'))
        ->toContain('data-navigation-group="user"')
        ->not->toContain('เมนูผู้ใช้งาน')
        ->toContain('data-navigation-group="admin"')
        ->toContain('เมนูผู้ดูแลระบบ');
});

test('profile about and logout are grouped under the topbar user name instead of the sidebar', function () {
    $this->actingAs(new User(['name' => 'Test User']));

    $document = new DOMDocument();
    @$document->loadHTML(mb_convert_encoding(
        Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>'),
        'HTML-ENTITIES',
        'UTF-8',
    ));
    $xpath = new DOMXPath($document);
    $profileUrl = route('profile.index');
    $aboutUrl = route('about');

    expect($xpath->query('//aside//a[@href="'.$profileUrl.'"]')->length)->toBe(0)
        ->and($xpath->query('//aside//a[@href="'.$aboutUrl.'"]')->length)->toBe(0)
        ->and($xpath->query('//header//*[@id="user-account-menu"]//a[@href="'.$aboutUrl.'"]')->length)->toBe(1)
        ->and($xpath->query('//header//button[@aria-controls="user-account-menu"]')->item(0)?->textContent)->toContain('Test User')
        ->and($xpath->query('//header//*[@id="user-account-menu"]//a[@href="'.$profileUrl.'"]')->length)->toBe(1)
        ->and($xpath->query('//header//*[@id="user-account-menu"]//button[@*[name()="wire:click" and .="logout"]]')->length)->toBe(1);
});

test('dashboard summary cards do not retain the legacy fixed-height utility', function () {
    $html = view('livewire.home', [
        'currentSalary' => 0,
        'specialAllowance' => 0,
        'totalDeductions' => 0,
        'netIncome' => 0,
        'totalIncome' => 0,
        'taxWithheld' => 0,
        'salaryHistory' => [],
        'incomeExpenseData' => null,
        'promotionHistory' => [],
    ])->render();

    expect($html)->not->toContain('h-100');
});

test('dashboard renders dedicated responsive grids for summaries and charts', function () {
    $html = view('livewire.home', [
        'currentSalary' => 0,
        'specialAllowance' => 0,
        'totalDeductions' => 0,
        'netIncome' => 0,
        'totalIncome' => 0,
        'taxWithheld' => 0,
        'salaryHistory' => [],
        'incomeExpenseData' => null,
        'promotionHistory' => [],
    ])->render();

    expect(substr_count($html, 'dashboard-metrics'))->toBe(3)
        ->and($html)->toContain('dashboard-charts')
        ->and($html)->toContain('dashboard-promotion-chart');
});

test('promotion history chart avoids the Bootstrap column utility that conflicts with Tailwind grid', function () {
    $html = view('livewire.home', [
        'currentSalary' => 0,
        'specialAllowance' => 0,
        'totalDeductions' => 0,
        'netIncome' => 0,
        'totalIncome' => 0,
        'taxWithheld' => 0,
        'salaryHistory' => [],
        'incomeExpenseData' => null,
        'promotionHistory' => [],
    ])->render();

    expect($html)->toContain('dashboard-promotion-panel')
        ->and($html)->not->toContain('class="col-12"');
});
