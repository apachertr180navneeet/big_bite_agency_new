<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\HomeController;

use App\Http\Controllers\Admin\{
    AdminAuthController,
    ReceiptController,
    ReportController
};

use App\Http\Controllers\User\{
    AuthController,
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

        Route::get('sales-person-report', [ReportController::class, 'salespersionreport'])->name('sales.person.report');
         Route::get('cash-report', [ReportController::class, 'caashReport'])->name('cash.report');
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


