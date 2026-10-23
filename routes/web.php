<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/forgot-password', [PasswordResetController::class, 'showRequestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// bKash redirects the browser here after payment (no auth: session may have expired).
Route::get('/payment/bkash/callback', [PaymentController::class, 'callback'])->name('payment.callback');

Route::middleware(['auth', 'not-rejected', 'student'])->prefix('portal')->name('portal.')->group(function () {
    Route::get('/', [PortalController::class, 'dashboard'])->name('dashboard');
    Route::get('/announcements', [PortalController::class, 'announcements'])->name('announcements');
    Route::get('/announcements/{announcement}', [PortalController::class, 'showAnnouncement'])->name('announcements.show');
    Route::get('/payments', [PortalController::class, 'payments'])->name('payments');
    Route::post('/pay/{event}', [PaymentController::class, 'pay'])->middleware('throttle:5,1')->name('pay');
    Route::get('/profile', [PortalController::class, 'editProfile'])->name('profile');
    Route::put('/profile', [PortalController::class, 'updateProfile'])->name('profile.update');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/students', [Admin\StudentController::class, 'index'])->name('students.index');
    Route::get('/students/export', [Admin\StudentController::class, 'export'])->name('students.export');
    Route::resource('student-fields', Admin\StudentFieldController::class)->except('show');
    Route::get('/students/{student}', [Admin\StudentController::class, 'show'])->name('students.show');
    Route::patch('/students/{student}/status', [Admin\StudentController::class, 'updateStatus'])->name('students.status');
    Route::patch('/students/{student}/admin', [Admin\StudentController::class, 'updateAdminAccess'])->name('students.admin');
    Route::patch('/students/{student}/card-photo', [Admin\StudentController::class, 'updateCardPhoto'])->name('students.card-photo');

    Route::resource('announcements', Admin\AnnouncementController::class)->except('show');
    Route::get('/email-members', [Admin\MemberEmailController::class, 'create'])->name('member-emails.create');
    Route::post('/email-members', [Admin\MemberEmailController::class, 'store'])->middleware('throttle:5,10')->name('member-emails.store');
    Route::resource('events', Admin\EventController::class)->except('show');

    Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
    Route::get('/welcome-page', [Admin\SettingController::class, 'edit'])->name('settings.edit');
    Route::put('/welcome-page', [Admin\SettingController::class, 'update'])->name('settings.update');

    Route::get('/payments/export', [Admin\PaymentController::class, 'export'])->name('payments.export');
});
