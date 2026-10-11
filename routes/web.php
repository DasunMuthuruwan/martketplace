<?php

use App\Authorization\Permissions;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Two-factor setup (Fortify endpoints do the work; this page shows status, QR and recovery codes).
    Route::view('/account/two-factor', 'account.two-factor')->name('account.two-factor');
});

// Admin area: staff only, Owner/Admin need confirmed 2FA. Screens are built in M6.
Route::middleware(['auth', 'verified', 'can:'.Permissions::ACCESS_ADMIN, 'staff.2fa'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::view('/', 'admin.placeholder')->name('dashboard');
    });
