<?php

use App\Http\Controllers\Api\V1\Admin\AdminCatalogController;
use App\Http\Controllers\Api\V1\Admin\AdminHotelController;
use App\Http\Controllers\Api\V1\Admin\AdminReportController;
use App\Http\Controllers\Api\V1\Admin\AdminRoomController;
use App\Http\Controllers\Api\V1\Admin\AdminStaffController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\Guest\GuestCatalogController;
use App\Http\Controllers\Api\V1\Guest\GuestRequestController;
use App\Http\Controllers\Api\V1\Guest\GuestSessionController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Platform\PlatformHotelController;
use App\Http\Controllers\Api\V1\Staff\StaffAuthController;
use App\Http\Controllers\Api\V1\Staff\StaffDashboardController;
use App\Http\Controllers\Api\V1\Staff\StaffRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class);

    Route::prefix('guest')->middleware('throttle:guest-session')->group(function () {
        Route::post('sessions', [GuestSessionController::class, 'store']);
    });

    Route::prefix('guest')->middleware(['guest.session', 'throttle:guest-read'])->group(function () {
        Route::get('context', [GuestSessionController::class, 'show']);
        Route::get('services', [GuestCatalogController::class, 'services']);
        Route::get('menu', [GuestCatalogController::class, 'menu']);
        Route::get('requests', [GuestRequestController::class, 'index']);
        Route::get('requests/{reference}', [GuestRequestController::class, 'show']);
        Route::post('orders/quote', [GuestRequestController::class, 'quote']);
        Route::get('attachments/{attachment}', [AttachmentController::class, 'guest']);
    });

    Route::prefix('guest')->middleware(['guest.session', 'throttle:guest-submit'])->group(function () {
        Route::post('requests', [GuestRequestController::class, 'store']);
        Route::post('orders', [GuestRequestController::class, 'order']);
        Route::post('requests/{reference}/cancel', [GuestRequestController::class, 'cancel']);
        Route::post('requests/{reference}/feedback', [GuestRequestController::class, 'feedback']);
    });

    Route::prefix('staff')->group(function () {
        Route::post('login', [StaffAuthController::class, 'login'])->middleware('throttle:login');
        Route::post('logout', [StaffAuthController::class, 'logout'])->middleware('auth:sanctum');

        Route::middleware(['auth:sanctum', 'hotel.staff'])->group(function () {
            Route::get('me', [StaffAuthController::class, 'me']);
            Route::get('dashboard', StaffDashboardController::class);
            Route::get('departments', [StaffDashboardController::class, 'departments']);
            Route::get('assignees', [StaffDashboardController::class, 'assignees']);
            Route::get('notifications', [StaffRequestController::class, 'notifications']);
            Route::post('notifications/{notification}/read', [StaffRequestController::class, 'readNotification']);
            Route::get('requests', [StaffRequestController::class, 'index']);
            Route::get('requests/{id}', [StaffRequestController::class, 'show']);
            Route::patch('requests/{id}/status', [StaffRequestController::class, 'status']);
            Route::post('requests/{id}/assign', [StaffRequestController::class, 'assign']);
            Route::post('requests/{id}/notes', [StaffRequestController::class, 'note']);
            Route::patch('requests/{id}/priority', [StaffRequestController::class, 'priority']);
            Route::post('requests/{id}/escalate', [StaffRequestController::class, 'escalate']);
            Route::post('requests/{id}/payment', [StaffRequestController::class, 'payment']);
            Route::post('requests/{id}/items/{item}/unavailable', [StaffRequestController::class, 'unavailable']);
            Route::get('attachments/{attachment}', [AttachmentController::class, 'staff']);
        });
    });

    Route::prefix('admin')->middleware(['auth:sanctum', 'hotel.staff', 'role:hotel_admin'])->group(function () {
        Route::get('hotel', [AdminHotelController::class, 'show']);
        Route::patch('hotel', [AdminHotelController::class, 'update']);
        Route::post('hotel/logo', [AdminHotelController::class, 'logo']);
        Route::patch('settings', [AdminHotelController::class, 'settings']);
        Route::get('rooms', [AdminRoomController::class, 'index']);
        Route::post('rooms', [AdminRoomController::class, 'store']);
        Route::patch('rooms/{room}', [AdminRoomController::class, 'update']);
        Route::get('rooms/{room}/history', [AdminRoomController::class, 'history']);
        Route::post('rooms/{room}/qr-code', [AdminRoomController::class, 'qrAction']);
        Route::get('rooms/{room}/qr-code.svg', [AdminRoomController::class, 'svg']);
        Route::get('rooms/{room}/qr-code.png', [AdminRoomController::class, 'png']);
        Route::get('service-categories', [AdminCatalogController::class, 'serviceCategories']);
        Route::post('service-categories', [AdminCatalogController::class, 'storeServiceCategory']);
        Route::get('services', [AdminCatalogController::class, 'services']);
        Route::post('services', [AdminCatalogController::class, 'storeService']);
        Route::patch('services/{service}', [AdminCatalogController::class, 'updateService']);
        Route::get('menu-categories', [AdminCatalogController::class, 'menuCategories']);
        Route::post('menu-categories', [AdminCatalogController::class, 'storeMenuCategory']);
        Route::get('menu-items', [AdminCatalogController::class, 'menuItems']);
        Route::post('menu-items', [AdminCatalogController::class, 'storeMenuItem']);
        Route::patch('menu-items/{item}', [AdminCatalogController::class, 'updateMenuItem']);
        Route::post('menu-items/{item}/image', [AdminCatalogController::class, 'menuImage']);
        Route::get('staff', [AdminStaffController::class, 'index']);
        Route::post('staff', [AdminStaffController::class, 'store']);
        Route::patch('staff/{user}', [AdminStaffController::class, 'update']);
        Route::post('staff/{user}/password', [AdminStaffController::class, 'password']);
        Route::post('staff/{user}/status', [AdminStaffController::class, 'status']);
        Route::get('audit-logs', [AdminStaffController::class, 'audit']);
        Route::get('reports/requests', [AdminReportController::class, 'requests']);
        Route::get('reports/requests.csv', [AdminReportController::class, 'csv']);
    });

    Route::prefix('platform')->middleware(['auth:sanctum', 'role:platform_admin'])->group(function () {
        Route::get('health', [PlatformHotelController::class, 'health']);
        Route::get('hotels', [PlatformHotelController::class, 'index']);
        Route::post('hotels', [PlatformHotelController::class, 'store']);
        Route::patch('hotels/{hotel}/status', [PlatformHotelController::class, 'status']);
    });
});
