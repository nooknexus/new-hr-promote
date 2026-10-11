<?php

use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

function taxCertificateAccessUser(string $role): User
{
    $user = new User;
    $user->forceFill(['id' => 128, 'name' => 'ผู้ทดสอบ', 'role' => $role, 'office' => '00']);
    $user->exists = true;

    return $user;
}

test('tax certificate management access is limited to ADMIN and FINANCE while finance retains signatories access', function () {
    expect(AdminAccess::allows(taxCertificateAccessUser('SUPERADMIN'), 'tax-certificates'))->toBeTrue()
        ->and(AdminAccess::allows(taxCertificateAccessUser('FINANCE'), 'tax-certificates'))->toBeTrue()
        ->and(AdminAccess::allows(taxCertificateAccessUser('LEAVE'), 'tax-certificates'))->toBeFalse()
        ->and(AdminAccess::allows(taxCertificateAccessUser('PROMOTE'), 'tax-certificates'))->toBeFalse()
        ->and(AdminAccess::allows(taxCertificateAccessUser('FINANCE'), 'signatories'))->toBeTrue();
});

test('tax certificate management route has its name path and ability middleware', function () {
    $route = Route::getRoutes()->getByName('admin.tax-certificates.index');

    expect($route)->not->toBeNull();
    expect($route->uri())->toBe('admin/tax-certificates')
        ->and($route->gatherMiddleware())->toContain('admin:tax-certificates');
});

test('leave and promotion users are forbidden from tax certificate management before component dispatch', function () {
    foreach (['LEAVE', 'PROMOTE'] as $role) {
        $this->actingAs(taxCertificateAccessUser($role))
            ->get('/admin/tax-certificates')
            ->assertForbidden();
    }
});

test('sidebar shows tax certificate management to finance but not leave', function () {
    $taxCertificateUrl = url('/admin/tax-certificates');

    $this->actingAs(taxCertificateAccessUser('FINANCE'));
    $financeSidebar = Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>');

    expect($financeSidebar)->toContain($taxCertificateUrl);

    $this->actingAs(taxCertificateAccessUser('LEAVE'));
    $leaveSidebar = Blade::render('<x-layouts.app><p>เนื้อหา</p></x-layouts.app>');

    expect($leaveSidebar)->not->toContain($taxCertificateUrl);
});
