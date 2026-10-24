<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\InstitutionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ListController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/explore', [CatalogController::class, 'explore'])->name('explore');
Route::get('/programs/{item}', [CatalogController::class, 'item'])->name('catalog.item');
Route::get('/universities', [CatalogController::class, 'universities'])->name('universities.index');
Route::get('/universities/{institution}', [CatalogController::class, 'university'])->name('universities.show');
Route::get('/lists', [ListController::class, 'index'])->name('lists.index');
Route::get('/lists/{list}', [ListController::class, 'show'])->name('lists.show');
Route::get('/lists/{list}/export/{format}', [ListController::class, 'export'])->name('lists.export');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::get('/recover', [AuthController::class, 'showRecover'])->name('recover');
    Route::post('/recover', [AuthController::class, 'recover'])->middleware('throttle:recover');
});

Route::middleware(['auth', 'auth.session'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/recovery-code', [AuthController::class, 'showRecoveryCode'])->name('recovery-code.show');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/my-lists', [DashboardController::class, 'store'])->name('lists.store');
    Route::view('/import', 'lists.import')->name('lists.import');

    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');
    Route::post('/account/recovery-code', [AccountController::class, 'regenerateRecoveryCode'])->name('account.recovery');
    Route::delete('/account', [AccountController::class, 'destroy'])->name('account.destroy');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/institutions', [InstitutionController::class, 'index'])->name('institutions');
        Route::put('/institutions/{institution:id}', [InstitutionController::class, 'update'])->name('institutions.update');
        Route::post('/institutions/merge', [InstitutionController::class, 'merge'])->name('institutions.merge');
    });
});
