<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AgentLinkController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BankDetailsController;
use App\Http\Controllers\Api\CommissionController;
use App\Http\Controllers\Api\CommunityController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentWebhookController;
use App\Http\Controllers\Api\PhoneVerificationController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\RentalController;
use App\Http\Controllers\Api\VisitController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/phone-verifications', [PhoneVerificationController::class, 'send'])->middleware('throttle:10,1');
    Route::post('/auth/phone-verifications/verify', [PhoneVerificationController::class, 'verify'])->middleware('throttle:10,1');

    if (app()->environment('local', 'testing') && config('intermediarius.payment_gateway') === 'fake') {
        Route::post('/payments/fake/webhook', [PaymentWebhookController::class, 'fake']);
    }

    Route::get('/properties', [PropertyController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/bank-details', [BankDetailsController::class, 'show']);
        Route::put('/bank-details', [BankDetailsController::class, 'update']);

        Route::get('/properties/mine', [PropertyController::class, 'mine']);
        Route::post('/properties', [PropertyController::class, 'store']);
        Route::delete('/properties/{property}', [PropertyController::class, 'destroy'])->whereNumber('property');
        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/properties/{property}/favorite', [FavoriteController::class, 'toggle'])->whereNumber('property');

        Route::get('/agent-links', [AgentLinkController::class, 'index']);
        Route::post('/properties/{property}/agents', [AgentLinkController::class, 'invite'])->whereNumber('property');
        Route::patch('/agent-links/{agent_link}', [AgentLinkController::class, 'update']);

        Route::get('/availability', [AvailabilityController::class, 'show']);
        Route::put('/availability', [AvailabilityController::class, 'update']);

        Route::get('/visits', [VisitController::class, 'index']);
        Route::post('/visits', [VisitController::class, 'store']);
        Route::post('/visits/checkin', [VisitController::class, 'checkin']);

        Route::get('/rentals', [RentalController::class, 'index']);
        Route::post('/rentals', [RentalController::class, 'store']);
        Route::get('/commissions', [CommissionController::class, 'index']);
        Route::patch('/commissions/{commission}/pay', [CommissionController::class, 'pay']);

        Route::get('/community/requests', [CommunityController::class, 'index']);
        Route::post('/community/requests', [CommunityController::class, 'store']);
        Route::post('/community/requests/{community_request}/offers', [CommunityController::class, 'offer']);
        Route::patch('/community/requests/{community_request}/close', [CommunityController::class, 'close']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::patch('/notifications/{user_notification}/read', [NotificationController::class, 'read']);
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);

        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('/stats', [AdminController::class, 'stats']);
            Route::get('/users', [AdminController::class, 'users']);
            Route::patch('/users/{user}', [AdminController::class, 'reviewUser']);
            Route::get('/properties', [AdminController::class, 'properties']);
            Route::patch('/properties/{property}', [AdminController::class, 'reviewProperty']);
            Route::get('/payments', [AdminController::class, 'payments']);
            Route::get('/owner-payouts', [AdminController::class, 'ownerPayouts']);
            Route::get('/settings', [AdminController::class, 'settings']);
            Route::put('/settings', [AdminController::class, 'saveSettings']);
        });
    });

    Route::get('/properties/{property}', [PropertyController::class, 'show'])->whereNumber('property');
});
