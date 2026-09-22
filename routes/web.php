<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Thaid\AuthController;
use App\Livewire\Provider\ProfileUpdate;
use App\Livewire\Provider\ProfileCallback;


//
Route::group(['middleware' => 'guest'], function () {
    Route::get('/login', App\Livewire\Login::class)->name('login');
    Route::get('/register', App\Livewire\Register::class)->name('register');
    
    // ThaID Authentication Routes
    Route::prefix('thaid')->name('thaid.')->group(function () {
        Route::get('/redirect', [AuthController::class, 'redirect'])->name('redirect');
        Route::get('/callback', [AuthController::class, 'callback'])->name('callback');
    });

    Route::get('/provider/redirect', App\Livewire\Provider\Redirect::class)
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
    
    Route::get('/training-info', function () {
        return view('training-info');
    })->name('training.info');

    // Provider ID Profile Update Routes - using direct controller approach
    Route::get('/provider/profile/update', ProfileUpdate::class)
        ->name('provider.profile.update');
    Route::get('/provider/profile/callback', App\Livewire\Provider\ProfileCallback::class)
        ->name('provider.profile.callback');

    // Tax Certificate route
    Route::get('/tax-certificate', App\Livewire\TaxCertificate::class)
        ->middleware(['auth'])
        ->name('tax.certificate');

    // Add this route to your existing web routes
    Route::get('/payslip/download/{id}', [\App\Http\Controllers\PayslipController::class, 'download'])->name('payslip.download')->middleware(['auth']);

    // Add these routes for direct access to print view and test PDF
    Route::get('/payslip/print-view/{id}', [\App\Http\Controllers\PayslipController::class, 'printView'])->name('payslip.print-view')->middleware(['auth']);
    
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

