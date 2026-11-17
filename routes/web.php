<?php

use App\Http\Controllers\AdminReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\PixelforgeWebhookController;
use App\Http\Middleware\EnsureAdmin;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/pixelforge', PixelforgeWebhookController::class)
    ->name('webhooks.pixelforge');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('generate')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('/generate', [GenerationController::class, 'create'])->name('generate');
    Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
    Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');

    Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('/credits/purchase', [CreditController::class, 'purchase'])->name('credits.purchase');

    Route::get('/dashboard', fn () => redirect()->route('generate'))->name('dashboard');

    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::get('/admin/report', AdminReportController::class)->name('admin.report');
    });
});

require __DIR__.'/settings.php';
