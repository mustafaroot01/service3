<?php

use App\Http\Controllers\Api\Customer\AuthController;
use App\Http\Controllers\Api\Customer\BlogController;
use App\Http\Controllers\Api\Customer\CategoryController;
use App\Http\Controllers\Api\Customer\GovernorateController;
use App\Http\Controllers\Api\Customer\LegalPageController;
use App\Http\Controllers\Api\Customer\NotificationController;
use App\Http\Controllers\Api\Customer\OrderController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\ServiceController;
use App\Http\Controllers\Api\Customer\SliderController;
use App\Http\Controllers\Api\Customer\SpecializationController;
use App\Http\Controllers\Api\Customer\TechnicianApplicationController;
use Illuminate\Support\Facades\Route;

Route::prefix('customer')->name('customer.')->group(function () {

    Route::prefix('auth')->name('auth.')->group(function () {
        // Signup sends a WhatsApp code, so it is held to the messaging cap.
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('throttle:otp-send')->name('register');

        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])
            ->middleware('throttle:otp-verify')->name('verify-otp');

        Route::post('resend-otp', [AuthController::class, 'resendOtp'])
            ->middleware('throttle:otp-send')->name('resend-otp');

        // Throttled inside the service so only wrong passwords count and a
        // correct one resets the tally — a route middleware would lock a
        // legitimate customer out on his sixth honest login.
        Route::post('login', [AuthController::class, 'login'])->name('login');

        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
            ->middleware('throttle:otp-send')->name('forgot-password');

        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('throttle:otp-verify')->name('reset-password');

        Route::middleware('auth:user')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::get('governorates', [GovernorateController::class, 'index'])->name('governorates.index');
    Route::get('governorates/{governorate}/districts', [GovernorateController::class, 'districts'])->name('governorates.districts');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}/services', [CategoryController::class, 'services'])->name('categories.services');

    Route::get('services', [ServiceController::class, 'index'])->name('services.index');
    Route::get('services/{service}', [ServiceController::class, 'show'])->name('services.show');

    Route::get('sliders', [SliderController::class, 'index'])->name('sliders.index');

    Route::get('blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('blog/{blog}', [BlogController::class, 'show'])->name('blog.show');

    Route::get('legal-pages/{key}', [LegalPageController::class, 'show'])->name('legal-pages.show');

    Route::get('specializations', [SpecializationController::class, 'index'])->name('specializations.index');

    // Public on purpose: anyone browsing the app can apply, with or without an
    // account. No send cap here — the cap counted rejected validation attempts
    // too, so an applicant correcting a photo format or size could burn the hour
    // without ever submitting once. The unique-phone guard still blocks repeats.
    Route::prefix('technician-application')->name('technician-application.')->group(function () {
        Route::get('/', [TechnicianApplicationController::class, 'form'])->name('form');
        Route::post('/', [TechnicianApplicationController::class, 'store'])->name('store');
    });

    Route::middleware('auth:user')->group(function () {
        Route::get('visit-window', [OrderController::class, 'visitWindow'])->name('visit-window');

        Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
        Route::match(['put', 'patch'], 'profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/change-password', [ProfileController::class, 'changePassword'])->name('profile.change-password');
        Route::post('profile/delete-request', [ProfileController::class, 'requestDeletion'])->name('profile.delete-request');

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::post('read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
            Route::post('{notification}/read', [NotificationController::class, 'markRead'])->name('read');
        });

        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');
            Route::post('/', [OrderController::class, 'store'])->name('store');
            Route::get('{order}', [OrderController::class, 'show'])->name('show');
            Route::post('{order}/cancel', [OrderController::class, 'cancel'])->name('cancel');
        });
    });
});
