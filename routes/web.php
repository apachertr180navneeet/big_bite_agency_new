<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use App\Http\Controllers\Web\HomeController;

use App\Http\Controllers\Admin\{
    AdminAuthController,
    ReceiptController,
    ReportController
};

use App\Http\Controllers\User\{
    AuthController,
    ReceiptController as UserReceiptController,
};

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/


Route::get('/', [HomeController::class, 'index'])->name('/');
Route::get('/home', [HomeController::class, 'index'])->name('home');

Route::name('admin.')->prefix('admin')->group(function () {
    Route::get('/', [AdminAuthController::class, 'index']);

    Route::get('login', [AdminAuthController::class, 'login'])->name('login');

    Route::post('login', [AdminAuthController::class, 'postLogin'])->name('login.post');

    Route::get('forget-password', [AdminAuthController::class, 'showForgetPasswordForm'])->name('forget.password.get');

    Route::post('forget-password', [AdminAuthController::class, 'submitForgetPasswordForm'])->name('forget.password.post');

    Route::get('reset-password/{token}', [AdminAuthController::class, 'showResetPasswordForm'])->name('reset.password.get');

    Route::post('reset-password', [AdminAuthController::class, 'submitResetPasswordForm'])->name('reset.password.post');

    Route::middleware(['admin'])->group(function () {
    	Route::get('dashboard', [AdminAuthController::class, 'adminDashboard'])->name('dashboard');

        Route::get('change-password', [AdminAuthController::class, 'changePassword'])->name('change.password');

        Route::post('update-password', [AdminAuthController::class, 'updatePassword'])->name('update.password');

        Route::get('logout', [AdminAuthController::class, 'logout'])->name('logout');

        Route::get('profile', [AdminAuthController::class, 'adminProfile'])->name('profile');

        Route::post('profile', [AdminAuthController::class, 'updateAdminProfile'])->name('update.profile');

        foreach ([ 'salesperson','customer' ,'invoice', 'receipt' ] as $resource) {
            Route::prefix($resource)->name("$resource.")->group(function () use ($resource) {
                $controller = "App\Http\Controllers\Admin\\" . ucfirst($resource) . "Controller";
                Route::get('/', [$controller, 'index'])->name('index');
                Route::get('all', [$controller, 'getall'])->name('getall');
                Route::get('/create', [$controller, 'create'])->name('create');
                Route::post('/store', [$controller, 'store'])->name('store');
                Route::delete('/delete/{id}', [$controller, 'delete'])->name('delete');
                Route::post('/status/{id}', [$controller, 'changeStatus'])->name('status');
                Route::get('/edit/{id}', [$controller, 'edit'])->name('edit');
                Route::post('/update/{id}', [$controller, 'update'])->name('update');
            });
        }

        Route::get('receipt/excel', [ReceiptController::class, 'exportExcel'])->name('receipt.excel');

        Route::get('sales-person-report', [ReportController::class, 'salespersionreport'])->name('sales.person.report');
        Route::get('cash-report', [ReportController::class, 'caashReport'])->name('cash.report');
        Route::get('firm-ledger-report', [ReportController::class, 'firmLedgerReport'])->name('firm.ledger.report');
        Route::get('firm-ledger-details-report', [ReportController::class, 'firmLedgerDetailsReport'])->name('firm.ledger.details.report');
        Route::get('/sales-person-report/excel', [ReportController::class, 'exportExcel'])->name('sales.person.report.excel');
        Route::get('/sales-person-report/pdf', [ReportController::class, 'exportPdf'])->name('sales.person.report.pdf');
        Route::get('/cash-report/excel', [ReportController::class, 'cashReportExportExcel'])->name('cash.report.excel');
        Route::get('/cash-report/pdf', [ReportController::class, 'cashReportExportPdf'])->name('cash.report.pdf');
        Route::get('/firm-ledger/excel', [ReportController::class, 'firmLedgerExcel'])->name('firm.ledger.excel');
        Route::get('/firm-ledger/pdf', [ReportController::class, 'firmLedgerPdf'])->name('firm.ledger.pdf');
    });

});


Route::name('user.')->prefix('user')->group(function () {
    Route::get('/', [AuthController::class, 'index']);

    Route::get('login', [AuthController::class, 'login'])->name('login');

    Route::post('login', [AuthController::class, 'postLogin'])->name('login.post');

    Route::get('forget-password', [AuthController::class, 'showForgetPasswordForm'])->name('forget.password.get');

    Route::post('forget-password', [AuthController::class, 'submitForgetPasswordForm'])->name('forget.password.post');

    Route::get('reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('reset.password.get');

    Route::post('reset-password', [AuthController::class, 'submitResetPasswordForm'])->name('reset.password.post');

    Route::middleware(['user'])->group(function () {
        Route::get('dashboard', [AuthController::class, 'dashboard'])->name('dashboard');
        Route::get('firm-ledger-report', [AuthController::class, 'firmLedgerReport'])->name('firm.ledger.report');
        Route::get('receipt/create', [UserReceiptController::class, 'create'])->name('receipt.create');
        Route::post('receipt/store', [UserReceiptController::class, 'store'])->name('receipt.store');
        Route::get('receipt/pending-invoices/{firm_id}', [UserReceiptController::class, 'getPendingInvoices'])->name('receipt.pending.invoices');

        Route::get('change-password', [AuthController::class, 'changePassword'])->name('change.password');

        Route::post('update-password', [AuthController::class, 'updatePassword'])->name('update.password');

        Route::get('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('profile', [AuthController::class, 'profile'])->name('profile');

        Route::post('profile', [AuthController::class, 'updateProfile'])->name('update.profile');
    });

});

Route::middleware(['auth'])->group(function () {

});


// Ajax Route
Route::get('/get-pending-invoices/{firm_id}', [ReceiptController::class, 'getPendingInvoices'])->name('get.pending.invoices');

Route::get('/get-invoice-detail/{id}', [ReceiptController::class, 'getInvoiceDetail'])->name('get.invoice.detail');

// =========================================================================
// Server Migration & Maintenance Routes
// =========================================================================
$migrationHandler = function (Request $request) {
    try {
        $action = $request->query('action', 'migrate');
        $params = ['--force' => true];

        if ($request->filled('path')) {
            $params['--path'] = $request->query('path');
        }

        if ($action === 'status') {
            Artisan::call('migrate:status', []);
            $title = 'Migration Status';
            $badgeColor = '#38bdf8';
        } elseif ($action === 'rollback') {
            Artisan::call('migrate:rollback', $params);
            $title = 'Migration Rollback';
            $badgeColor = '#fbbf24';
        } elseif ($action === 'clear') {
            Artisan::call('optimize:clear', []);
            $title = 'Cache & Optimize Cleared';
            $badgeColor = '#34d399';
        } else {
            Artisan::call('migrate', $params);
            $title = 'Migration Executed';
            $badgeColor = '#4ade80';
        }

        $output = Artisan::output();
        if (empty(trim($output))) {
            $output = "Command executed successfully.\nNo additional console output returned (Database is already up to date).";
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => true,
                'action' => $action,
                'message' => "{$title} successfully.",
                'output' => $output,
            ], 200);
        }

        $safeOutput = htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
        $time = now()->toDateTimeString();
        $baseUrl = url($request->path());

        return response("<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>{$title} - Server Migration</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #0b0f19; color: #f1f5f9; padding: 40px 20px; margin: 0; }
        .container { max-width: 860px; margin: 0 auto; background: #131b2e; border: 1px solid #1e293b; border-radius: 14px; padding: 28px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #1e293b; padding-bottom: 18px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px; }
        .title-group { display: flex; align-items: center; gap: 12px; }
        h1 { margin: 0; font-size: 22px; font-weight: 700; color: #38bdf8; }
        .badge { font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 9999px; text-transform: uppercase; background: {$badgeColor}22; color: {$badgeColor}; border: 1px solid {$badgeColor}55; }
        .time { font-size: 13px; color: #64748b; margin: 0; }
        pre { background: #070a12; color: #a5f3fc; padding: 20px; border-radius: 10px; overflow-x: auto; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 13.5px; line-height: 1.6; border: 1px solid #1e293b; margin: 0 0 24px 0; white-space: pre-wrap; word-break: break-word; }
        .actions-label { font-size: 13px; font-weight: 600; text-transform: uppercase; color: #94a3b8; margin-bottom: 12px; letter-spacing: 0.5px; }
        .buttons { display: flex; flex-wrap: wrap; gap: 10px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; color: #ffffff; background: #2563eb; transition: all 0.2s ease; border: none; cursor: pointer; }
        .btn:hover { background: #1d4ed8; transform: translateY(-1px); }
        .btn-status { background: #0284c7; }
        .btn-status:hover { background: #0369a1; }
        .btn-clear { background: #0d9488; }
        .btn-clear:hover { background: #0f766e; }
        .btn-specific { background: #7c3aed; }
        .btn-specific:hover { background: #6d28d9; }
        .btn-danger { background: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .info-card { margin-top: 24px; padding: 14px 18px; background: #0f172a; border-radius: 8px; border: 1px dashed #334155; font-size: 13px; color: #94a3b8; }
        .info-card code { color: #38bdf8; font-family: monospace; background: #1e293b; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <div class='title-group'>
                <h1>{$title}</h1>
                <span class='badge'>Success</span>
            </div>
            <p class='time'>Executed: {$time}</p>
        </div>

        <pre>{$safeOutput}</pre>

        <div class='actions-label'>Quick Actions</div>
        <div class='buttons'>
            <a href='{$baseUrl}' class='btn'>Run All Migrations (migrate)</a>
            <a href='{$baseUrl}?path=database/migrations/2026_09_30_145800_add_entry_type_to_invoices_table.php' class='btn btn-specific'>Update Invoices Table (entry_type)</a>
            <a href='{$baseUrl}?action=status' class='btn btn-status'>Migration Status</a>
            <a href='{$baseUrl}?action=clear' class='btn btn-clear'>Clear Cache & Optimize</a>
            <a href='{$baseUrl}?action=rollback' class='btn btn-danger' onclick='return confirm(\"Are you sure you want to rollback the last migration batch?\");'>Rollback Last Batch</a>
        </div>

        <div class='info-card'>
            💡 <strong>API / Postman:</strong> Send <code>Accept: application/json</code> header to receive response as JSON.
        </div>
    </div>
</body>
</html>", 200, ['Content-Type' => 'text/html']);

    } catch (\Throwable $e) {
        $errorMessage = $e->getMessage();
        $errorTrace = $e->getTraceAsString();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => false,
                'error' => $errorMessage,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }

        $safeMsg = htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8');
        $safeTrace = htmlspecialchars($errorTrace, ENT_QUOTES, 'UTF-8');
        $baseUrl = url($request->path());

        return response("<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Migration Failed - Error</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background: #0b0f19; color: #f1f5f9; padding: 40px 20px; margin: 0; }
        .container { max-width: 860px; margin: 0 auto; background: #18121a; border: 1px solid #7f1d1d; border-radius: 14px; padding: 28px; box-shadow: 0 20px 40px rgba(0,0,0,0.5); }
        h1 { margin: 0 0 16px 0; font-size: 22px; color: #f87171; }
        .error-box { background: #450a0a33; border: 1px solid #ef444455; color: #fca5a5; padding: 14px 18px; border-radius: 8px; font-weight: 600; font-size: 14px; margin-bottom: 20px; }
        pre { background: #070a12; color: #f87171; padding: 18px; border-radius: 10px; overflow-x: auto; font-family: monospace; font-size: 13px; line-height: 1.5; border: 1px solid #2d1517; white-space: pre-wrap; word-break: break-word; }
        .buttons { display: flex; gap: 10px; margin-top: 20px; }
        .btn { padding: 10px 18px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; color: #fff; background: #3b82f6; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>⚠️ Migration Failed</h1>
        <div class='error-box'>{$safeMsg}</div>
        <pre>{$safeTrace}</pre>
        <div class='buttons'>
            <a href='{$baseUrl}?action=status' class='btn'>Check Status</a>
            <a href='{$baseUrl}?action=clear' class='btn' style='background:#475569;'>Clear Cache</a>
            <a href='{$baseUrl}' class='btn' style='background:#10b981;'>Try Again</a>
        </div>
    </div>
</body>
</html>", 500, ['Content-Type' => 'text/html']);
    }
};

Route::get('/run-migration', $migrationHandler)->name('run.migration');
Route::get('/run-migrations', $migrationHandler)->name('run.migrations');
Route::get('/migrate', $migrationHandler)->name('migrate');

