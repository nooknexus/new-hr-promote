<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Thaid\AuthController;


//
Route::group(['middleware' => 'guest'], function () {
    Route::get('/login', App\Livewire\Login::class)->middleware('external-browser')->name('login');
    Route::get('/register', App\Livewire\Register::class)->middleware('external-browser')->name('register');
    
    // ThaID Authentication Routes
    Route::prefix('thaid')->name('thaid.')->group(function () {
        Route::get('/redirect', [AuthController::class, 'redirect'])->middleware('external-browser')->name('redirect');
        Route::get('/callback', [AuthController::class, 'callback'])->name('callback');
    });

    Route::get('/provider/redirect', App\Livewire\Provider\Redirect::class)
        ->middleware('external-browser')
        ->name('provider.redirect');
});

Route::group(['middleware' => 'auth'], function () {
    Route::get('/', App\Livewire\Home::class)->name('home');
    Route::get('/about', App\Livewire\About::class)->name('about');
    Route::get('/profile', App\Livewire\Profile\Index::class)->name('profile.index');
    Route::get('/profile/{id}/edit', App\Livewire\Profile\Edit::class)->name('profile.edit');
    Route::get('/profile/payslip', App\Livewire\Profile\Payslip::class)->name('profile.payslip');
    
    // New route for salary promotion
    Route::get('/profile/salary-promotion', App\Livewire\SalaryPromotion::class)->name('profile.salary-promotion');
    Route::get('/profile/salary-promotion/{promotion}/download', [\App\Http\Controllers\SalaryPromotionController::class, 'download'])
        ->whereNumber('promotion')
        ->name('profile.salary-promotion.download');
    
    Route::get('/leave-history', App\Livewire\LeaveHistory::class)->name('leave.history');

    // Tax Certificate route
    Route::get('/tax-certificate', App\Livewire\TaxCertificate::class)
        ->middleware(['auth'])
        ->name('tax.certificate');
    Route::get('/tax-certificate/download/{taxYear}/{certificateNo}', [\App\Http\Controllers\TaxCertificateController::class, 'download'])
        ->whereNumber('taxYear')
        ->where('certificateNo', '[A-Za-z0-9_-]+')
        ->middleware(['auth'])
        ->name('tax.certificate.download');

    // Add this route to your existing web routes
    Route::get('/payslip/download/{id}', [\App\Http\Controllers\PayslipController::class, 'download'])->name('payslip.download')->middleware(['auth']);

    // Add these routes for direct access to print view and test PDF
    Route::get('/payslip/print-view/{id}', [\App\Http\Controllers\PayslipController::class, 'printView'])->name('payslip.print-view')->middleware(['auth']);

});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/leaves/template', function () {
        $path = resource_path('templates/leave-import-template.xlsx');
        abort_unless(is_file($path), 404);
        abort_if(\App\Support\OfficeScope::officeFor(auth()->user()) === '', 403);

        return response()->download($path, 'leave-import-template.xlsx');
    })->middleware('admin:leave')->name('leaves.template');
    Route::get('/users', App\Livewire\Admin\UserManagement::class)->middleware('admin:users')->name('users.index');
    Route::get('/offices', App\Livewire\Admin\OfficeManagement::class)->middleware('admin:offices')->name('offices.index');
    Route::get('/payslips', App\Livewire\Admin\PayslipManagement::class)->middleware('admin:payslip')->name('payslips.index');
    Route::get('/salary-promotions', App\Livewire\Admin\SalaryPromotionManagement::class)->middleware('admin:promotions')->name('salary-promotions.index');
    Route::get('/signatories', App\Livewire\Admin\Signatories::class)->middleware('admin:signatories')->name('signatories');
    Route::get('/tax-certificates', App\Livewire\Admin\TaxCertificateManagement::class)->middleware('admin:tax-certificates')->name('tax-certificates.index');
    Route::get('/leave-sheet-sync', App\Livewire\Admin\LeaveSheetSync::class)->middleware('admin:leave-sync')->name('leave-sheet-sync');
    Route::get('/leaves', App\Livewire\Admin\LeaveManagement::class)->middleware('admin:leave')->name('leaves.index');
    Route::get('/personnel', App\Livewire\Admin\PersonnelManagement::class)->middleware('admin:personnel')->name('personnel.index');
    Route::get('/personnel/template', function () {
        $path = resource_path('templates/personnel-import-template.xlsx');
        abort_unless(is_file($path), 404);

        return response()->download($path, 'personnel-import-template.xlsx');
    })->middleware('admin:personnel')->name('personnel.template');
    
    Route::get('/payslips/template', function () {
        $path = resource_path('templates/payslip-import-template.xlsx');
        abort_unless(is_file($path), 404);

        return response()->download($path, 'payslip-import-template.xlsx');
    })->middleware('admin:payslip')->name('payslips.template');
});

// Ensure these routes are accessible without auth for the callbacks
Route::get('/provider/callback', App\Livewire\Provider\Callback::class)->name('provider.callback');

// Public routes for information pages
Route::get('/provider-info', function () {
    return view('pages.provider-info');
})->name('provider.info');

// Add this route for font conversion (run once)
// MyMOPH OAuth routes
Route::get('/mymoph/callback', [App\Http\Controllers\Auth\MyMOPHController::class, 'callback'])->name('mymoph.callback');
