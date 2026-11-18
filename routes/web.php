<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Payroll\DirectoryController;
use App\Http\Controllers\Payroll\PaymentController;
use App\Http\Controllers\Payroll\WorksheetController;
use App\Http\Controllers\Teams\TeamInvitationController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Middleware\ProtectPayrollResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->user()?->can('administer-payroll')) {
        return to_route('payroll.index');
    }

    if (auth()->guest()) {
        Inertia::clearHistory();
    }

    return Inertia::render('welcome');
})->name('home');

Route::prefix('payroll')->name('payroll.')
    ->middleware(['auth', 'verified', 'can:administer-payroll', ProtectPayrollResponse::class])
    ->group(function (): void {
        Route::get('/', [WorksheetController::class, 'index'])->name('index');
        Route::post('/runs', [WorksheetController::class, 'store'])->name('store');
        Route::patch('/runs/{run}/rows/{row}/adjustments', [WorksheetController::class, 'adjustments'])->name('adjustments');
        Route::patch('/runs/{run}/rows/{row}/statutory', [WorksheetController::class, 'statutory'])->name('statutory');
        Route::post('/runs/{run}/finalise', [WorksheetController::class, 'finalise'])->name('finalise');
        Route::get('/runs/{run}/rows/{row}/payslip', [WorksheetController::class, 'payslip'])->name('payslip');
        Route::get('/directory', [DirectoryController::class, 'index'])->name('directory');
        Route::put('/employer', [DirectoryController::class, 'employer'])->name('employer');
        Route::post('/employees', [DirectoryController::class, 'store'])->name('employees.store');
        Route::put('/employees/{employee}', [DirectoryController::class, 'update'])->name('employees.update');
        Route::get('/runs/{run}/payment', [PaymentController::class, 'show'])->name('payment');
        Route::post('/runs/{run}/payment/preview', [PaymentController::class, 'download'])->name('download');
        Route::post('/runs/{run}/payment/rows/{row}/correction', [PaymentController::class, 'correct'])->name('correct');
    });

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::post('invitations/{invitation}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
    Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'decline'])->name('invitations.decline');
});

require __DIR__.'/settings.php';
