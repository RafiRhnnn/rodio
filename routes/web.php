<?php

use App\Http\Controllers\Admin\ConversionController as AdminConversionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ConversionController;
use App\Http\Controllers\ConverterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication — login only
|--------------------------------------------------------------------------
| There is deliberately no /register or /sign-up route: accounts are created
| exclusively by an admin. Each sensitive endpoint gets its own throttle
| bucket (see AppServiceProvider::registerRateLimiters()).
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware(['auth', 'active'])
    ->name('logout');

/*
| User area
*/
Route::middleware(['auth', 'active'])->group(function () {
    // Each role lands on its own dashboard.
    Route::get('/', function (Request $request) {
        return redirect()->route($request->user()->isAdmin() ? 'admin.dashboard' : 'dashboard');
    })->name('home');

    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/history', [ConversionController::class, 'history'])->name('history');

    Route::get('/converter', [ConverterController::class, 'index'])->name('converter');
    Route::post('/converter/upload', [ConverterController::class, 'store'])
        ->middleware('throttle:upload')->name('converter.upload.store');
    Route::delete('/converter/upload', [ConverterController::class, 'destroy'])
        ->middleware('throttle:upload')->name('converter.upload.destroy');

    Route::post('/converter', [ConversionController::class, 'store'])
        ->middleware('throttle:conversion')->name('conversion.store');

    Route::get('/api/conversions/{conversion}/status', [ConversionController::class, 'status'])
        ->whereNumber('conversion')->middleware('throttle:status')->name('conversions.status');

    Route::get('/api/conversions/{conversion}/result', [ConversionController::class, 'result'])
        ->whereNumber('conversion')->middleware('throttle:status')->name('conversions.result');

    Route::post('/api/conversions/{conversion}/cancel', [ConversionController::class, 'cancel'])
        ->whereNumber('conversion')->middleware('throttle:status')->name('conversions.cancel');

    // The only route that serves result bytes: policy + existence check.
    Route::get('/conversions/{conversion}/download', [ConversionController::class, 'download'])
        ->whereNumber('conversion')->middleware('throttle:download')->name('conversions.download');
});

/*
| Admin area
*/
Route::middleware(['auth', 'active', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::redirect('/', '/admin/dashboard');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        Route::resource('users', AdminUserController::class)->except('show');
        Route::patch('users/{user}/status', [AdminUserController::class, 'status'])->name('users.status');
        Route::put('users/{user}/password', [AdminUserController::class, 'password'])->name('users.password');
        Route::get('/conversions', [AdminConversionController::class, 'index'])->name('conversions');
        Route::post('/conversions/{conversion}/retry', [AdminConversionController::class, 'retry'])
            ->whereNumber('conversion')->name('conversions.retry');
        Route::view('/settings', 'admin.placeholder')->name('settings');
    });
