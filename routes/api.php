<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SystemSettingController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserRequestController;
use App\Http\Controllers\Api\TravelerController;
use App\Http\Controllers\Api\TravelerRequestController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PayoutAccountController;
use App\Http\Controllers\Api\TravelerTripController;
use App\Http\Controllers\Api\TripTrackingController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\TravelerOrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\BoosterController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\HelpController;
use App\Http\Controllers\Api\AdvertisementController;
use App\Http\Controllers\Api\PlatformWithdrawController;
use App\Http\Controllers\Api\NotificationController;

// Public 
Route::get('/settings/public', [SystemSettingController::class, 'publicSettings']);
Route::get('/faqs', [HelpController::class, 'faqs']);

Route::post('/user-requests',     [UserRequestController::class, 'store']);
Route::post('/traveler-requests', [TravelerRequestController::class, 'store']);

// Advertisement
Route::get('/advertisements/live',            [AdvertisementController::class, 'live']);
Route::get('/advertisements/packages',        [AdvertisementController::class, 'packages']);
Route::post('/advertisements/sync-by-order',  [AdvertisementController::class, 'syncByOrder']);  
Route::post('/advertisements/{id}/sync',       [AdvertisementController::class, 'syncPayment']);
Route::post('/advertisements/payment/notify',  [AdvertisementController::class, 'handleNotification']);
Route::post('/advertisements',                 [AdvertisementController::class, 'store']);
// trip
Route::get('/trips/available', [TripController::class, 'available']);
Route::get('/trips/{id}/public',  [TripController::class, 'publicShow']);
// Faq
Route::get('/faqs', [HelpController::class, 'faqs']); 

// Auth
Route::prefix('auth')->group(function () {

    Route::get('/google', [AuthController::class, 'googleRedirect'])->middleware('throttle:5,1');
    Route::get('/google/callback', [AuthController::class, 'googleCallback'])->middleware('throttle:5,1');
    Route::post('/google/token', [AuthController::class, 'googleTokenLogin']);

    Route::post('/register-customer', [AuthController::class, 'registerCustomer']);
    Route::post('/register-traveler', [AuthController::class, 'registerTraveler']);

    // Unified login
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:7,1');

    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    // Auth
    Route::middleware('multi.auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
    });
});

// ADMIN ONLY 
Route::middleware(['multi.auth', 'role:admin'])->prefix('admin')->group(function () {

    // General management
    Route::get('/profile',              [ProfileController::class, 'show']);
    Route::put('/profile',              [ProfileController::class, 'update']);
    Route::post('/profile/photo',       [ProfileController::class, 'updatePhoto']);
    Route::delete('/profile',           [ProfileController::class, 'destroy']);

    Route::get('/settings',               [SystemSettingController::class, 'index']);
    Route::put('/settings',               [SystemSettingController::class, 'update']);
    Route::get('/settings/history',       [SystemSettingController::class, 'history']);
    Route::post('/settings/reset/{key}',  [SystemSettingController::class, 'reset']);
    Route::patch('/settings/{key}',       [SystemSettingController::class, 'updateSingle']);

    Route::get   ('/notifications',              [NotificationController::class, 'index']);
    Route::get   ('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch ('/notifications/read-all',     [NotificationController::class, 'markAllRead']);
    Route::patch ('/notifications/{id}/read',    [NotificationController::class, 'markRead']);
    Route::delete('/notifications',              [NotificationController::class, 'destroyAll']);
    Route::delete('/notifications/{id}',         [NotificationController::class, 'destroy']);

    // User & Traveler management
    Route::get('/users',               [UserController::class, 'index']);
    Route::post('/users',              [UserController::class, 'store']);
    Route::get('/users/{id}',          [UserController::class, 'show']);
    Route::put('/users/{id}',          [UserController::class, 'update']);
    Route::patch('/users/{id}/status', [UserController::class, 'updateStatus']);
    Route::delete('/users/{id}',       [UserController::class, 'destroy']);

    Route::get('/user-requests',                [UserRequestController::class, 'index']);
    Route::get('/user-requests/{id}',           [UserRequestController::class, 'show']);
    Route::post('/user-requests/{id}/approve',  [UserRequestController::class, 'approve']);
    Route::patch('/user-requests/{id}/reject',  [UserRequestController::class, 'reject']);
    Route::delete('/user-requests/{id}',        [UserRequestController::class, 'destroy']);

    Route::get('/travelers',               [TravelerController::class, 'index']);
    Route::post('/travelers',              [TravelerController::class, 'store']);
    Route::get('/travelers/{id}',          [TravelerController::class, 'show']);
    Route::put('/travelers/{id}',          [TravelerController::class, 'update']);
    Route::patch('/travelers/{id}/status', [TravelerController::class, 'updateStatus']);
    Route::delete('/travelers/{id}',       [TravelerController::class, 'destroy']);

    Route::get('/traveler-requests',                [TravelerRequestController::class, 'index']);
    Route::get('/traveler-requests/{id}',           [TravelerRequestController::class, 'show']);
    Route::post('/traveler-requests/{id}/approve',  [TravelerRequestController::class, 'approve']);
    Route::patch('/traveler-requests/{id}/reject',  [TravelerRequestController::class, 'reject']);
    Route::delete('/traveler-requests/{id}',        [TravelerRequestController::class, 'destroy']);
    // Dashboard Admin
    Route::get('/dashboard', [DashboardController::class, 'admin']);
    // Booster management admin
    Route::get('/boosters',                          [BoosterController::class, 'adminPlans']);
    Route::post('/boosters',                         [BoosterController::class, 'store']);
    Route::put('/boosters/{id}',                     [BoosterController::class, 'update']);
    Route::patch('/boosters/{id}/toggle',            [BoosterController::class, 'toggleActive']);
    Route::get('/boosters/monitoring',               [BoosterController::class, 'adminMonitoring']);
    Route::patch('/boosters/traveler/{id}/status',   [BoosterController::class, 'updateStatus']);

    // Wallet management admin
    Route::get('/wallet/booster', [BoosterController::class, 'adminWallet']);
    Route::get('/wallet/advertisements', [AdvertisementController::class, 'adminWallet']);

    Route::get   ('/platform-withdraw',              [PlatformWithdrawController::class, 'index']);
    Route::post  ('/platform-withdraw',              [PlatformWithdrawController::class, 'store']);
    Route::get   ('/platform-withdraw/balance',      [PlatformWithdrawController::class, 'balance']);
    Route::get   ('/platform-withdraw/{id}',         [PlatformWithdrawController::class, 'show']);
    Route::patch ('/platform-withdraw/{id}/approve', [PlatformWithdrawController::class, 'approve']);
    Route::patch ('/platform-withdraw/{id}/complete',[PlatformWithdrawController::class, 'complete']);
    Route::patch ('/platform-withdraw/{id}/reject',  [PlatformWithdrawController::class, 'reject']);
    Route::delete('/platform-withdraw/{id}',         [PlatformWithdrawController::class, 'destroy']);

    // Get traveler trip data
    Route::get('/routes', [TripController::class, 'routes']);
    // Rating management admin
    Route::get('/ratings', [RatingController::class, 'adminIndex']);
    // Transactions list admin
    Route::get('/transactions', [PaymentController::class, 'adminIndex']); 
    // Report management 
    Route::get('/disputes',                  [ReportController::class, 'adminIndex']);
    Route::patch('/disputes/{id}/review',    [ReportController::class, 'markInReview']);
    Route::patch('/disputes/{id}/resolve',   [ReportController::class, 'resolve']);

    // Help management admin
    Route::get('/help/tickets',              [HelpController::class, 'adminIndex']);
    Route::post('/help/tickets/{id}/reply',  [HelpController::class, 'reply']);
    Route::patch('/help/tickets/{id}/resolve', [HelpController::class, 'resolve']);
    Route::get('/help/faqs',                 [HelpController::class, 'adminFaqs']);
    Route::post('/help/faqs',                [HelpController::class, 'storeFaq']);
    Route::put('/help/faqs/{id}',            [HelpController::class, 'updateFaq']);
    Route::delete('/help/faqs/{id}',         [HelpController::class, 'destroyFaq']);

    // Advertisement management 
    Route::get('/advertisements',                    [AdvertisementController::class, 'adminIndex']); 
    Route::post('/advertisements/approve-manual', [AdvertisementController::class, 'storeManual']);
    Route::delete('/advertisements/{id}',            [AdvertisementController::class, 'destroy']);
    Route::patch('/advertisements/{id}/approve',     [AdvertisementController::class, 'approve']);
    Route::patch('/advertisements/{id}/reject',      [AdvertisementController::class, 'reject']);
});

// TRAVELER
  Route::middleware(['multi.auth', 'role:traveler'])->group(function () {

    // General management
    Route::get('/traveler/profile',        [ProfileController::class, 'show']);
    Route::put('/traveler/profile',        [ProfileController::class, 'update']);
    Route::post('/traveler/profile/photo', [ProfileController::class, 'updatePhoto']);
    Route::delete('/traveler/profile',     [ProfileController::class, 'destroy']);
    Route::get('/traveler/dashboard', [DashboardController::class, 'traveler']);
    Route::get('/traveler/reviews',   [RatingController::class, 'travelerReviews']);

    Route::get   ('/traveler/notifications',              [NotificationController::class, 'index']);
    Route::get   ('/traveler/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch ('/traveler/notifications/read-all',     [NotificationController::class, 'markAllRead']);
    Route::patch ('/traveler/notifications/{id}/read',    [NotificationController::class, 'markRead']);
    Route::delete('/traveler/notifications',              [NotificationController::class, 'destroyAll']);
    Route::delete('/traveler/notifications/{id}',         [NotificationController::class, 'destroy']);

    // Payout account management
    Route::get('/traveler/payout-accounts',               [PayoutAccountController::class, 'index']);
    Route::post('/traveler/payout-accounts',              [PayoutAccountController::class, 'store']);
    Route::delete('/traveler/payout-accounts/{id}',       [PayoutAccountController::class, 'destroy']);
    Route::patch('/traveler/payout-accounts/{id}/default', [PayoutAccountController::class, 'setDefault']);

    // Trip management
    Route::get('/traveler/trips',                  [TravelerTripController::class, 'index']);
    Route::post('/traveler/trips',                 [TravelerTripController::class, 'store']);
    Route::get('/traveler/trips/{id}',             [TravelerTripController::class, 'show']);
    Route::patch('/traveler/trips/{id}/status',    [TravelerTripController::class, 'updateStatus']);
    Route::delete('/traveler/trips/{id}',          [TravelerTripController::class, 'destroy']);

    Route::post('/traveler/trips/{tripId}/tracking/start',    [TripTrackingController::class, 'start']);
    Route::post('/traveler/trips/{tripId}/tracking/location', [TripTrackingController::class, 'updateLocation']);
    Route::post('/traveler/trips/{tripId}/tracking/stop',     [TripTrackingController::class, 'stop']);
    Route::get('/traveler/trips/{tripId}/tracking',           [TripTrackingController::class, 'history']);

    // Order management
    Route::get('/traveler/orders',              [TravelerOrderController::class, 'index']);
    Route::get('/traveler/orders/{id}',         [TravelerOrderController::class, 'show']);
    Route::patch('/traveler/orders/{id}/accept', [TravelerOrderController::class, 'accept']);
    Route::patch('/traveler/orders/{id}/reject', [TravelerOrderController::class, 'reject']);
    Route::patch('/traveler/orders/{id}/status', [TravelerOrderController::class, 'updateStatus']);
    Route::post('/traveler/orders/{id}/price', [TravelerOrderController::class, 'updatePrice']);
    Route::get('/traveler/trips/{tripId}/orders', [TravelerOrderController::class, 'byTrip']);

    // Wallet management
    Route::get('/traveler/wallet',          [PayoutAccountController::class, 'wallet']);
    Route::get('/traveler/wallet/income',   [PayoutAccountController::class, 'recentIncome']);
    Route::get('/traveler/wallet/history',  [PayoutAccountController::class, 'walletHistory']);
    Route::post('/traveler/wallet/withdraw', [PayoutAccountController::class, 'createWithdraw']);
    Route::get('/traveler/wallet/withdraws', [PayoutAccountController::class, 'withdrawHistory']);

    // Booster management
    Route::get('/traveler/boosters/plans',           [BoosterController::class, 'plans']);
    Route::post('/traveler/boosters/buy',            [BoosterController::class, 'buy']);
    Route::post('/traveler/boosters/{id}/sync',      [BoosterController::class, 'syncPayment']);
    Route::post('/traveler/boosters/sync-by-order', [BoosterController::class, 'syncByOrderId']);
    Route::get('/traveler/boosters/active',          [BoosterController::class, 'active']);
    Route::get('/traveler/boosters/history',         [BoosterController::class, 'history']);

    //Report (Dispute and Rating) management
    Route::get('/traveler/ratings', [RatingController::class, 'travelerIndex']);
    Route::get('/traveler/disputes',              [ReportController::class, 'travelerIndex']);
    Route::post('/traveler/disputes/{id}/reply',  [ReportController::class, 'travelerReply']);
    Route::post('/traveler/disputes/{id}/resolve',      [ReportController::class, 'travelerResolve']); 

});

// CUSTOMER 
Route::middleware(['multi.auth', 'role:customer'])->group(function () {

    // General management
    Route::get('/customer/profile',              [ProfileController::class, 'show']);
    Route::put('/customer/profile',              [ProfileController::class, 'update']);
    Route::post('/customer/profile/photo',       [ProfileController::class, 'updatePhoto']);
    Route::delete('/customer/profile',           [ProfileController::class, 'destroy']);

    Route::get   ('/notifications',              [NotificationController::class, 'index']);
    Route::get   ('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch ('/notifications/read-all',     [NotificationController::class, 'markAllRead']);
    Route::patch ('/notifications/{id}/read',    [NotificationController::class, 'markRead']);
    Route::delete('/notifications',              [NotificationController::class, 'destroyAll']);
    Route::delete('/notifications/{id}',         [NotificationController::class, 'destroy']);

    // Order management
    Route::post('/customer/orders',            [CustomerOrderController::class, 'store']);
    Route::get('/customer/orders',             [CustomerOrderController::class, 'index']);
    Route::get('/customer/orders/{id}',        [CustomerOrderController::class, 'show']);
    Route::post('/customer/orders/{id}/pay', [PaymentController::class, 'createPayment']);
    Route::get('/customer/orders/{id}/payment-status', [PaymentController::class, 'checkStatus']);
    Route::patch('/customer/orders/{id}/cancel', [CustomerOrderController::class, 'cancel']);

    // Rating management
    Route::post('/customer/orders/{id}/rating', [RatingController::class, 'store']);
    Route::get('/customer/orders/{id}/rating', [RatingController::class, 'show']);

    // Get traveler tracking
    Route::get('/trips/{id}/detail', [TripController::class, 'show']);
    Route::get('/trips/{tripId}/tracking', [TripTrackingController::class, 'customerView']);

    // Payment
    Route::post('/customer/orders/{id}/payment-sync', [PaymentController::class, 'syncStatus']);

    // Report Customer by transaction
    Route::post('/customer/orders/{id}/report',    [ReportController::class, 'store']);
    Route::get('/customer/orders/{id}/report',     [ReportController::class, 'showByTransaction']);
    Route::get('/customer/orders/{id}/report/answer',   [ReportController::class, 'customerAnswer']); // ← baru
    // Help management
    Route::post('/customer/help/tickets',     [HelpController::class, 'store']);
    Route::get('/customer/help/tickets',      [HelpController::class, 'myTickets']);
});

// Midtrans notification
Route::post('/midtrans/notification', [PaymentController::class, 'handleNotification']);
Route::post('/midtrans/booster/notification', [BoosterController::class, 'handleNotification']); 

Route::get('/payments/{id}/debug', [PaymentController::class, 'debugMidtrans']);
Route::post('/payments/{id}/force-update', [PaymentController::class, 'forceUpdateStatus']);