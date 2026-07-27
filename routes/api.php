<?php

use App\Http\Controllers\Api\V1\AccommodationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CancellationRequestController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ─── Auth (public) ───────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('/otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:10,1');
        Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::delete('/tokens', [AuthController::class, 'logoutAll']);
        });
    });

    // ─── Catalog & Availability (public) ─────────────────────────────────────
    Route::get('/provinces', [CatalogController::class, 'provinces']);
    Route::get('/locations', [CatalogController::class, 'locations']);
    Route::get('/provinces/{province}/cities', [CatalogController::class, 'cities']);

    Route::get('/accommodations', [AccommodationController::class, 'index']);
    Route::get('/accommodations/{accommodation}', [AccommodationController::class, 'show']);

    Route::get('/room-types/{roomType}/availability', [AvailabilityController::class, 'roomType']);
    Route::get('/room-types/{roomType}/physical-rooms', [AvailabilityController::class, 'physicalRooms']);
    Route::get('/accommodations/{accommodation}/physical-rooms', [AvailabilityController::class, 'accommodationPhysicalRooms']);
    Route::get('/accommodations/{accommodation}/rooms-availability', [AvailabilityController::class, 'accommodationRooms']);

    // ─── Authenticated guest API ─────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/verify-national-id', [ProfileController::class, 'verifyNationalId']);

        Route::get('/bookings', [BookingController::class, 'index']);
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/accommodations/{accommodation}/bookings', [BookingController::class, 'store']);
        Route::get('/bookings/{booking}/pdf', [BookingController::class, 'pdf']);

        Route::get('/bookings/{booking}/cancellation-reasons', [CancellationRequestController::class, 'reasons']);
        Route::get('/bookings/{booking}/cancellation-preview', [CancellationRequestController::class, 'preview']);
        Route::post('/bookings/{booking}/cancellation-requests', [CancellationRequestController::class, 'store']);

        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites/{accommodation}/toggle', [FavoriteController::class, 'toggle']);

        Route::post('/accommodations/{accommodation}/reviews', [ReviewController::class, 'store']);
    });
});
