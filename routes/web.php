<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\AvailabilityController;
use App\Http\Controllers\BaleMiniAppController;
use App\Http\Controllers\BaleSetupController;
use App\Http\Controllers\BaleWebhookController;
use App\Http\Controllers\AccommodationController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\FavoriteController;
use Illuminate\Support\Facades\Route;

// ─── Bale / Webhook (keep as controllers) ───────────────────────────────────
Route::get('/miniapp/bale', [BaleMiniAppController::class, 'index'])->name('miniapp.bale.index');
Route::post('/miniapp/bale/authenticate', [BaleMiniAppController::class, 'authenticate'])->name('miniapp.bale.authenticate');
Route::post('/webhooks/bale/{secret}', [BaleWebhookController::class, 'handle'])->name('bale.webhook');
Route::get('/bale/setup/{secret}', [BaleSetupController::class, 'register'])->name('bale.setup');

// ─── Staff login (admin / host) ───────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', \App\Livewire\Auth\StaffLogin::class)->name('admin.login');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout')->middleware('auth');

// ─── Shared API endpoints (needed by admin/host manual booking) ─────────────
Route::get('/api/provinces/{province}/cities',                       [AccommodationController::class, 'citiesByProvince'])->name('api.cities');
Route::get('/api/room-types/{roomType}/availability',                [AvailabilityController::class, 'roomType'])->name('api.room-types.availability');
Route::get('/api/accommodations/{accommodation}/rooms-availability', [AvailabilityController::class, 'accommodationRooms'])->name('api.accommodations.rooms-availability');

// ─── User-facing routes (redirected to /admin/login when STAFF_ONLY_MODE=true) ─
Route::middleware('staff_mode.block')->group(function () {

// ─── Home (Livewire) ─────────────────────────────────────────────────────────
Route::get('/', \App\Livewire\Pages\Home::class)->name('home');

// ─── Auth (Livewire OTP) ─────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',        \App\Livewire\Auth\Login::class)->name('auth.mobile');
    Route::post('/login',       [AuthController::class, 'sendOtp'])->name('auth.send-otp');
    Route::get('/login/verify', \App\Livewire\Auth\VerifyOtp::class)->name('auth.otp.form');
    Route::post('/login/verify', [AuthController::class, 'verifyOtp'])->name('auth.otp.verify');
});

// ─── Profile (Livewire) ──────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/profile/setup',  \App\Livewire\Pages\ProfileSetup::class)->name('profile.setup');
    Route::post('/profile/setup', [ProfileController::class, 'saveSetup'])->name('profile.setup.save');
    Route::get('/profile',        \App\Livewire\Pages\ProfileIndex::class)->name('profile.index');
    Route::post('/profile/verify-national-id', [ProfileController::class, 'verifyNationalId'])->name('profile.verify-id');
});

// ─── Accommodations (Livewire) ───────────────────────────────────────────────
Route::get('/accommodations',              \App\Livewire\Pages\AccommodationIndex::class)->name('accommodations.index');
Route::get('/accommodations/{accommodation}', \App\Livewire\Pages\AccommodationShow::class)->name('accommodations.show');

// ─── Bookings & Favorites (Livewire, auth required) ─────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/accommodations/{accommodation}/book',  \App\Livewire\Pages\BookingCreate::class)->name('bookings.create');
    Route::post('/accommodations/{accommodation}/book', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings',           \App\Livewire\Pages\BookingIndex::class)->name('bookings.index');
    Route::get('/bookings/{booking}/pdf', [\App\Http\Controllers\BookingReceiptController::class, 'download'])->name('bookings.pdf');
    Route::get('/bookings/{booking}',  \App\Livewire\Pages\BookingShow::class)->name('bookings.show');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
    Route::get('/favorites',          \App\Livewire\Pages\FavoriteIndex::class)->name('favorites.index');
    Route::post('/favorites/{accommodation}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/accommodations/{accommodation}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});

}); // end staff_mode.block

// ─── Super Admin Panel ───────────────────────────────────────────────────────
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {

    // Dashboard
    Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('dashboard');

    // Users
    Route::get('/users/export',      [\App\Http\Controllers\Admin\UserController::class, 'export'])->name('users.export');
    Route::get('/users',             \App\Livewire\Admin\UserIndex::class)->name('users.index');
    Route::get('/users/create-host', \App\Livewire\Admin\HostCreate::class)->name('users.create-host');
    Route::get('/users/{user}',      \App\Livewire\Admin\UserShow::class)->name('users.show');
    Route::get('/users/{user}/edit', \App\Livewire\Admin\UserEdit::class)->name('users.edit');

    // Accommodations
    Route::get('/accommodations',                      \App\Livewire\Admin\AccommodationIndex::class)->name('accommodations.index');
    Route::get('/accommodations/import',               \App\Livewire\Admin\AccommodationImport::class)->name('accommodations.import');
    Route::get('/accommodations/import/sample',        function () {
        $path = storage_path('app/samples/accommodation-import-sample.csv');
        abort_unless(file_exists($path), 404);
        return response()->download($path, 'accommodation-import-sample.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    })->name('accommodations.import.sample');
    Route::get('/accommodations/create',               \App\Livewire\Admin\AccommodationCreate::class)->name('accommodations.create');
    Route::get('/accommodations/{accommodation}/edit', \App\Livewire\Admin\AccommodationEdit::class)->name('accommodations.edit');
    Route::get('/accommodations/{accommodation}/manual-booking', \App\Livewire\Admin\ManualBooking::class)->name('accommodations.manual-booking');
    // Sales report (keep as controller — complex chart data)
    Route::get('/accommodations/{accommodation}/report', [\App\Http\Controllers\Admin\AccommodationController::class, 'salesReport'])->name('accommodations.report');

    // Room Types (complex nested CRUD — keep as controllers)
    Route::prefix('/accommodations/{accommodation}/room-types')->name('room-types.')->group(function () {
        Route::get('/',     [\App\Http\Controllers\Admin\RoomTypeController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Admin\RoomTypeController::class, 'create'])->name('create');
        Route::post('/',    [\App\Http\Controllers\Admin\RoomTypeController::class, 'store'])->name('store');
        Route::get('/{roomType}/edit',  [\App\Http\Controllers\Admin\RoomTypeController::class, 'edit'])->name('edit');
        Route::put('/{roomType}',       [\App\Http\Controllers\Admin\RoomTypeController::class, 'update'])->name('update');
        Route::delete('/{roomType}',    [\App\Http\Controllers\Admin\RoomTypeController::class, 'destroy'])->name('destroy');
        Route::post('/{roomType}/rates',                 [\App\Http\Controllers\Admin\RoomTypeController::class, 'storeRate'])->name('rates.store');
        Route::put('/{roomType}/rates/{rate}',           [\App\Http\Controllers\Admin\RoomTypeController::class, 'updateRate'])->name('rates.update');
        Route::delete('/{roomType}/rates/{rate}',        [\App\Http\Controllers\Admin\RoomTypeController::class, 'destroyRate'])->name('rates.destroy');
        Route::get('/{roomType}/blocked-dates',              [\App\Http\Controllers\Admin\RoomTypeController::class, 'blockedDates'])->name('blocked-dates');
        Route::post('/{roomType}/blocked-dates',             [\App\Http\Controllers\Admin\RoomTypeController::class, 'storeBlockedDate'])->name('blocked-dates.store');
        Route::delete('/{roomType}/blocked-dates/{blocked}', [\App\Http\Controllers\Admin\RoomTypeController::class, 'destroyBlockedDate'])->name('blocked-dates.destroy');
        Route::get('/{roomType}/daily-availability',                [\App\Http\Controllers\Admin\RoomTypeController::class, 'dailyAvailability'])->name('daily-availability');
        Route::post('/{roomType}/daily-availability',               [\App\Http\Controllers\Admin\RoomTypeController::class, 'storeDailyAvailability'])->name('daily-availability.store');
        Route::delete('/{roomType}/daily-availability/{override}',  [\App\Http\Controllers\Admin\RoomTypeController::class, 'destroyDailyAvailability'])->name('daily-availability.destroy');
    });

    // Bookings — export must come before {booking} wildcard
    Route::get('/bookings/export',    [\App\Http\Controllers\Admin\BookingController::class, 'export'])->name('bookings.export');
    Route::get('/bookings',           \App\Livewire\Admin\BookingIndex::class)->name('bookings.index');
    Route::get('/bookings/{booking}/pdf', [\App\Http\Controllers\BookingReceiptController::class, 'download'])->name('bookings.pdf');
    Route::get('/bookings/{booking}', \App\Livewire\Admin\BookingShow::class)->name('bookings.show');

    // Developer commission wallet
    Route::get('/commission-wallet/export', [\App\Http\Controllers\Admin\CommissionWalletController::class, 'export'])->name('commission-wallet.export');
    Route::get('/commission-wallet', \App\Livewire\Admin\CommissionWallet::class)->name('commission-wallet');
    Route::get('/commission-wallet/{entry}', \App\Livewire\Admin\CommissionWalletShow::class)->name('commission-wallet.show');

    // Reviews
    Route::get('/reviews', \App\Livewire\Admin\ReviewIndex::class)->name('reviews.index');

    // Veteran policy settings
    Route::get('/veteran-policy', \App\Livewire\Admin\VeteranPolicySettings::class)->name('veteran-policy');

    // Location & type catalog (admin-only delete)
    Route::get('/location-catalog', \App\Livewire\Admin\LocationCatalogSettings::class)->name('location-catalog');

    // Programs — supportive-report must come before {program} wildcard
    Route::get('/programs/supportive-report', \App\Livewire\Admin\ProgramSupportiveReport::class)->name('programs.supportive-report');
    Route::get('/programs',           \App\Livewire\Admin\ProgramIndex::class)->name('programs.index');
    Route::get('/programs/{program}', \App\Livewire\Admin\ProgramShow::class)->name('programs.show');
});

// ─── Host Panel ──────────────────────────────────────────────────────────────
Route::prefix('host')->name('host.')->middleware(['auth', 'host', 'host.permission'])->group(function () {

    // Dashboard
    Route::get('/', \App\Livewire\Host\Dashboard::class)->name('dashboard');

    // Profile (available to all hosts)
    Route::get('/profile', \App\Livewire\Host\Profile::class)->name('profile');

    // Accommodations
    Route::get('/accommodations',                      \App\Livewire\Host\AccommodationIndex::class)->name('accommodations.index');
    Route::get('/accommodations/create',               \App\Livewire\Host\AccommodationCreate::class)->name('accommodations.create');
    Route::get('/accommodations/{accommodation}/edit', \App\Livewire\Host\AccommodationEdit::class)->name('accommodations.edit');
    Route::get('/accommodations/{accommodation}/manual-booking', \App\Livewire\Host\ManualBooking::class)->name('accommodations.manual-booking');

    // Bookings
    Route::get('/bookings/export',    [\App\Http\Controllers\Host\BookingController::class, 'export'])->name('bookings.export');
    Route::get('/bookings',           \App\Livewire\Host\BookingIndex::class)->name('bookings.index');
    Route::get('/bookings/{booking}/pdf', [\App\Http\Controllers\BookingReceiptController::class, 'download'])->name('bookings.pdf');
    Route::get('/bookings/{booking}', \App\Livewire\Host\BookingShow::class)->name('bookings.show');

    // Reviews
    Route::get('/reviews', \App\Livewire\Host\ReviewIndex::class)->name('reviews.index');

    // Users (guests with bookings at assigned accommodations)
    Route::get('/users/export', [\App\Http\Controllers\Host\UserController::class, 'export'])->name('users.export');
    Route::get('/users', \App\Livewire\Host\UserIndex::class)->name('users.index');

    // Room Types (nested CRUD — keep as controllers)
    Route::prefix('accommodations/{accommodation}/room-types')->name('room-types.')->group(function () {
        Route::get('/',                [\App\Http\Controllers\Host\RoomTypeController::class, 'index'])->name('index');
        Route::get('/create',          [\App\Http\Controllers\Host\RoomTypeController::class, 'create'])->name('create');
        Route::post('/',               [\App\Http\Controllers\Host\RoomTypeController::class, 'store'])->name('store');
        Route::get('/{roomType}/edit', [\App\Http\Controllers\Host\RoomTypeController::class, 'edit'])->name('edit');
        Route::put('/{roomType}',      [\App\Http\Controllers\Host\RoomTypeController::class, 'update'])->name('update');
        Route::delete('/{roomType}',   [\App\Http\Controllers\Host\RoomTypeController::class, 'destroy'])->name('destroy');
        Route::post('/{roomType}/rates',              [\App\Http\Controllers\Host\RoomTypeController::class, 'storeRate'])->name('rates.store');
        Route::put('/{roomType}/rates/{rate}',        [\App\Http\Controllers\Host\RoomTypeController::class, 'updateRate'])->name('rates.update');
        Route::delete('/{roomType}/rates/{rate}',     [\App\Http\Controllers\Host\RoomTypeController::class, 'destroyRate'])->name('rates.destroy');
        Route::get('/{roomType}/blocked-dates',              [\App\Http\Controllers\Host\RoomTypeController::class, 'blockedDates'])->name('blocked-dates');
        Route::post('/{roomType}/blocked-dates',             [\App\Http\Controllers\Host\RoomTypeController::class, 'storeBlockedDate'])->name('blocked-dates.store');
        Route::delete('/{roomType}/blocked-dates/{blocked}', [\App\Http\Controllers\Host\RoomTypeController::class, 'destroyBlockedDate'])->name('blocked-dates.destroy');
        Route::get('/{roomType}/daily-availability',                [\App\Http\Controllers\Host\RoomTypeController::class, 'dailyAvailability'])->name('daily-availability');
        Route::post('/{roomType}/daily-availability',               [\App\Http\Controllers\Host\RoomTypeController::class, 'storeDailyAvailability'])->name('daily-availability.store');
        Route::delete('/{roomType}/daily-availability/{override}',  [\App\Http\Controllers\Host\RoomTypeController::class, 'destroyDailyAvailability'])->name('daily-availability.destroy');
    });

    // Programs — supportive-report and create must come before {program} wildcard
    Route::get('/programs/supportive-report', \App\Livewire\Host\ProgramSupportiveReport::class)->name('programs.supportive-report');
    Route::get('/programs/create',            \App\Livewire\Host\ProgramCreate::class)->name('programs.create');
    Route::get('/programs',                   \App\Livewire\Host\ProgramIndex::class)->name('programs.index');
    Route::get('/programs/{program}',         \App\Livewire\Host\ProgramShow::class)->name('programs.show');
    Route::get('/programs/{program}/edit',    \App\Livewire\Host\ProgramEdit::class)->name('programs.edit');
    // Delete (Livewire handles this in ProgramIndex/Show via actions; keep controller fallback)
    Route::delete('/programs/{program}',      [\App\Http\Controllers\Host\ProgramController::class, 'destroy'])->name('programs.destroy');
});


