<?php

use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DestinationController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\GeocodingController;
use App\Http\Controllers\Api\V1\ItineraryController;
use App\Http\Controllers\Api\V1\PackingItemController;
use App\Http\Controllers\Api\V1\TripController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Autentikasi (Login-only, tanpa registrasi publik)
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::put('/auth/password', [AuthController::class, 'updatePassword']);

        // Khusus Admin: Kelola User
        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::get('/users/{user}', [AdminUserController::class, 'show']);
            Route::put('/users/{user}', [AdminUserController::class, 'update']);
            Route::patch('/users/{user}/toggle-active', [AdminUserController::class, 'toggleActive']);
            Route::patch('/users/{user}/reset-password', [AdminUserController::class, 'resetPassword']);
        });

        // Modul Wishlist Destinasi
        Route::get('/tags', [DestinationController::class, 'tags']);
        Route::get('/destinations', [DestinationController::class, 'index']);
        Route::post('/destinations', [DestinationController::class, 'store']);
        Route::get('/destinations/{destination}', [DestinationController::class, 'show']);
        Route::put('/destinations/{destination}', [DestinationController::class, 'update']);
        Route::delete('/destinations/{destination}', [DestinationController::class, 'destroy']);
        Route::patch('/destinations/{destination}/visit', [DestinationController::class, 'visit']);
        Route::post('/destinations/{destination}/links', [DestinationController::class, 'addLink']);
        Route::delete('/destinations/{destination}/links/{link}', [DestinationController::class, 'deleteLink']);
        Route::post('/destinations/{destination}/create-trip', [DestinationController::class, 'createTrip']);

        // Modul Rencana Perjalanan
        Route::get('/trips', [TripController::class, 'index']);
        Route::post('/trips', [TripController::class, 'store']);
        Route::get('/trips/{trip}', [TripController::class, 'show']);
        Route::put('/trips/{trip}', [TripController::class, 'update']);
        Route::delete('/trips/{trip}', [TripController::class, 'destroy']);
        Route::get('/trips/{trip}/summary', [TripController::class, 'summary']);
        Route::post('/trips/{trip}/duplicate', [TripController::class, 'duplicate']);
        Route::put('/trips/{trip}/destinations', [TripController::class, 'syncDestinations']);

        // Itinerary Harian & Kegiatan
        Route::put('/days/{day}', [ItineraryController::class, 'updateDay']);
        Route::post('/days/{day}/items', [ItineraryController::class, 'storeItem']);
        Route::put('/items/{item}', [ItineraryController::class, 'updateItem']);
        Route::delete('/items/{item}', [ItineraryController::class, 'destroyItem']);

        // Pengeluaran Nyata
        Route::get('/trips/{trip}/expenses', [ExpenseController::class, 'index']);
        Route::post('/trips/{trip}/expenses', [ExpenseController::class, 'store']);
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

        // Daftar Packing
        Route::get('/trips/{trip}/packing-items', [PackingItemController::class, 'index']);
        Route::post('/trips/{trip}/packing-items', [PackingItemController::class, 'store']);
        Route::put('/packing-items/{packingItem}', [PackingItemController::class, 'update']);
        Route::delete('/packing-items/{packingItem}', [PackingItemController::class, 'destroy']);

        // Geocoding Proxy (Nominatim)
        Route::get('/geocoding/search', [GeocodingController::class, 'search']);
        Route::get('/geocoding/reverse', [GeocodingController::class, 'reverse']);
    });
});
