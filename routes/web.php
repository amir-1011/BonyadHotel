<?php

use App\Http\Controllers\AccommodationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// Home
Route::get('/', [HomeController::class, 'index'])->name('home');

// Auth (mobile OTP)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showMobileForm'])->name('auth.mobile');
    Route::post('/login', [AuthController::class, 'sendOtp'])->name('auth.send-otp');
    Route::get('/login/verify', [AuthController::class, 'showOtpForm'])->name('auth.otp.form');
    Route::post('/login/verify', [AuthController::class, 'verifyOtp'])->name('auth.otp.verify');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth');

// Profile
Route::middleware('auth')->group(function () {
    Route::get('/profile/setup', [ProfileController::class, 'setup'])->name('profile.setup');
    Route::post('/profile/setup', [ProfileController::class, 'saveSetup'])->name('profile.setup.save');
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('/profile/verify-national-id', [ProfileController::class, 'verifyNationalId'])->name('profile.verify-id');
});

// Accommodations
Route::get('/accommodations', [AccommodationController::class, 'index'])->name('accommodations.index');
Route::get('/accommodations/{accommodation}', [AccommodationController::class, 'show'])->name('accommodations.show');
Route::get('/api/provinces/{province}/cities', [AccommodationController::class, 'citiesByProvince'])->name('api.cities');

// Bookings (auth required)
Route::middleware('auth')->group(function () {
    Route::get('/accommodations/{accommodation}/book', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/accommodations/{accommodation}/book', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    // Reviews
    Route::post('/accommodations/{accommodation}/reviews', [ReviewController::class, 'store'])->name('reviews.store');

    // Favorites
    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/favorites/{accommodation}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
});

// ─── Super Admin Panel ───────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'show'])->name('users.show');
    Route::post('/users/{user}/role', [\App\Http\Controllers\Admin\UserController::class, 'assignRole'])->name('users.role');
    Route::post('/users/{user}/toggle', [\App\Http\Controllers\Admin\UserController::class, 'toggleStatus'])->name('users.toggle');
    Route::delete('/users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/accommodations', [\App\Http\Controllers\Admin\AccommodationController::class, 'index'])->name('accommodations.index');
    Route::get('/accommodations/create', [\App\Http\Controllers\Admin\AccommodationController::class, 'create'])->name('accommodations.create');
    Route::post('/accommodations', [\App\Http\Controllers\Admin\AccommodationController::class, 'store'])->name('accommodations.store');
    Route::get('/accommodations/{accommodation}/edit', [\App\Http\Controllers\Admin\AccommodationController::class, 'edit'])->name('accommodations.edit');
    Route::put('/accommodations/{accommodation}', [\App\Http\Controllers\Admin\AccommodationController::class, 'update'])->name('accommodations.update');
    Route::delete('/accommodations/{accommodation}', [\App\Http\Controllers\Admin\AccommodationController::class, 'destroy'])->name('accommodations.destroy');
    Route::post('/accommodations/{accommodation}/toggle', [\App\Http\Controllers\Admin\AccommodationController::class, 'toggleActive'])->name('accommodations.toggle');

    Route::get('/bookings', [\App\Http\Controllers\Admin\BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [\App\Http\Controllers\Admin\BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{booking}/status', [\App\Http\Controllers\Admin\BookingController::class, 'updateStatus'])->name('bookings.status');

    Route::get('/reviews', [\App\Http\Controllers\Admin\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/toggle', [\App\Http\Controllers\Admin\ReviewController::class, 'toggle'])->name('reviews.toggle');
    Route::delete('/reviews/{review}', [\App\Http\Controllers\Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');
});

// ─── Host Panel ──────────────────────────────────────────────────────────────
Route::prefix('host')->name('host.')->middleware(['auth', 'host'])->group(function () {
    Route::get('/', [\App\Http\Controllers\Host\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/accommodations', [\App\Http\Controllers\Host\AccommodationController::class, 'index'])->name('accommodations.index');
    Route::get('/accommodations/create', [\App\Http\Controllers\Host\AccommodationController::class, 'create'])->name('accommodations.create');
    Route::post('/accommodations', [\App\Http\Controllers\Host\AccommodationController::class, 'store'])->name('accommodations.store');
    Route::get('/accommodations/{accommodation}/edit', [\App\Http\Controllers\Host\AccommodationController::class, 'edit'])->name('accommodations.edit');
    Route::put('/accommodations/{accommodation}', [\App\Http\Controllers\Host\AccommodationController::class, 'update'])->name('accommodations.update');
    Route::delete('/accommodations/{accommodation}', [\App\Http\Controllers\Host\AccommodationController::class, 'destroy'])->name('accommodations.destroy');

    Route::get('/bookings', [\App\Http\Controllers\Host\BookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{booking}', [\App\Http\Controllers\Host\BookingController::class, 'show'])->name('bookings.show');
    Route::post('/bookings/{booking}/confirm', [\App\Http\Controllers\Host\BookingController::class, 'confirm'])->name('bookings.confirm');
    Route::post('/bookings/{booking}/cancel', [\App\Http\Controllers\Host\BookingController::class, 'cancel'])->name('bookings.cancel');

    Route::get('/reviews', [\App\Http\Controllers\Host\ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/reply', [\App\Http\Controllers\Host\ReviewController::class, 'reply'])->name('reviews.reply');
    Route::delete('/reviews/{review}/reply', [\App\Http\Controllers\Host\ReviewController::class, 'deleteReply'])->name('reviews.reply.delete');

    // Room Types (nested under accommodation)
    Route::prefix('accommodations/{accommodation}/room-types')->name('room-types.')->group(function () {
        Route::get('/',                     [\App\Http\Controllers\Host\RoomTypeController::class, 'index'])->name('index');
        Route::get('/create',               [\App\Http\Controllers\Host\RoomTypeController::class, 'create'])->name('create');
        Route::post('/',                    [\App\Http\Controllers\Host\RoomTypeController::class, 'store'])->name('store');
        Route::get('/{roomType}/edit',      [\App\Http\Controllers\Host\RoomTypeController::class, 'edit'])->name('edit');
        Route::put('/{roomType}',           [\App\Http\Controllers\Host\RoomTypeController::class, 'update'])->name('update');
        Route::delete('/{roomType}',        [\App\Http\Controllers\Host\RoomTypeController::class, 'destroy'])->name('destroy');
        // Rates
        Route::post('/{roomType}/rates',                    [\App\Http\Controllers\Host\RoomTypeController::class, 'storeRate'])->name('rates.store');
        Route::put('/{roomType}/rates/{rate}',              [\App\Http\Controllers\Host\RoomTypeController::class, 'updateRate'])->name('rates.update');
        Route::delete('/{roomType}/rates/{rate}',           [\App\Http\Controllers\Host\RoomTypeController::class, 'destroyRate'])->name('rates.destroy');
    });
});


