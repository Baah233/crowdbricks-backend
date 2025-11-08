<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\InvestmentController;
use App\Http\Controllers\Api\InvestorController;
use App\Http\Controllers\Api\DeveloperController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NewsletterController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\SecurityController;
use App\Http\Controllers\Api\KYCController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\InvestmentAdminController;
use App\Http\Controllers\Api\Admin\NewsAdminController;

/*
|--------------------------------------------------------------------------
| API Routes — CrowdBricks v1
|--------------------------------------------------------------------------
|
| This file defines the REST API for both public and authenticated routes.
| Version prefix `/v1` is used for all routes. Sanctum middleware secures
| user, developer, investor, and admin actions.
|
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | 🟢 Public Routes
    |--------------------------------------------------------------------------
    */
    Route::get('/ping', fn() => response()->json(['ok' => true]));

    // Auth (rate limited)
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->name('login')->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

    // Public projects
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);

    // AI Chat Assistant (available to everyone - visitors and logged-in users)
    Route::post('/ai/chat', [InvestorController::class, 'aiChat'])->middleware('throttle:20,1');

    // Newsletter subscription
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);
    Route::post('/newsletter/unsubscribe', [NewsletterController::class, 'unsubscribe']);

    // Public news articles
    Route::get('/news', [NewsController::class, 'index']);
    Route::get('/news/{slug}', [NewsController::class, 'show']);

    // Public chat & webhooks
    Route::post('/chat', [ChatController::class, 'chat'])->middleware('throttle:30,1');
    Route::post('/payments/webhook/{gateway}', [TransactionController::class, 'webhook']);


    /*
    |--------------------------------------------------------------------------
    | 🔒 Authenticated Routes (Protected by Sanctum)
    |--------------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {

        // 🔹 Auth actions
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/user', [AuthController::class, 'me']);
        Route::post('/user/change-password', [AuthController::class, 'changePassword']);

        /*
        |--------------------------------------------------------------------------
        | 💼 Investor Routes
        |--------------------------------------------------------------------------
        */
        Route::post('/investments', [InvestmentController::class, 'store']);
        Route::get('/user/investments', [InvestorController::class, 'investments']);
        Route::get('/user/stats', [InvestorController::class, 'stats']);
        Route::get('/user/transactions', [InvestorController::class, 'transactions']);
        Route::get('/user/portfolio-history', [InvestorController::class, 'portfolioHistory']);
        Route::get('/user/profile', [InvestorController::class, 'profile']);
        Route::put('/user/profile', [InvestorController::class, 'updateProfile']);
        Route::put('/user/preferences', [InvestorController::class, 'updatePreferences']);
        
        /*
        |--------------------------------------------------------------------------
        | 🔐 Two-Factor Authentication Routes
        |--------------------------------------------------------------------------
        */
        Route::post('/user/2fa/enable', [InvestorController::class, 'enableTwoFactor']);
        Route::post('/user/2fa/disable', [InvestorController::class, 'disableTwoFactor']);
        Route::post('/user/2fa/verify', [InvestorController::class, 'verifyTwoFactor']);

        /*
        |--------------------------------------------------------------------------
        | 🔒 Security & Login Activity Routes
        |--------------------------------------------------------------------------
        */
        Route::get('/user/login-activities', [InvestorController::class, 'loginActivities']);
        Route::post('/user/logout-device/{id}', [InvestorController::class, 'logoutDevice']);

        /*
        |--------------------------------------------------------------------------
        | � Financial Operations Routes
        |--------------------------------------------------------------------------
        */
        Route::get('/user/dividends', [InvestorController::class, 'dividends']);
        Route::get('/user/tax-report', [InvestorController::class, 'taxReport']);

        /*
        |--------------------------------------------------------------------------
        | �📱 Phone Verification Routes
        |--------------------------------------------------------------------------
        */
        Route::post('/user/phone/change-request', [InvestorController::class, 'requestPhoneChange'])->middleware('throttle:3,1440'); // 3 per day
        Route::post('/user/phone/send-verification', [InvestorController::class, 'sendPhoneVerification'])->middleware('throttle:5,60'); // 5 per hour
        Route::post('/user/phone/verify', [InvestorController::class, 'verifyPhone'])->middleware('throttle:10,60'); // 10 per hour
        
        /*
        |--------------------------------------------------------------------------
        | 📸 Profile Picture Routes
        |--------------------------------------------------------------------------
        */
        Route::post('/user/profile-picture', [InvestorController::class, 'uploadProfilePicture']);

        /*
        |--------------------------------------------------------------------------
        | 🎫 Support Ticket Routes
        |--------------------------------------------------------------------------
        */
        Route::post('/support/ticket', [InvestorController::class, 'submitSupportTicket']);
        Route::get('/support/tickets', [InvestorController::class, 'getSupportTickets']);
        Route::get('/support/tickets/{id}', [InvestorController::class, 'getSupportTicketById']);
        Route::post('/support/tickets/{id}/reply', [InvestorController::class, 'replyToSupportTicket']);
        Route::get('/support/unread-count', [InvestorController::class, 'getUnreadSupportMessagesCount']);

        /*
        |--------------------------------------------------------------------------
        | 💳 Wallet Routes
        |--------------------------------------------------------------------------
        */
        Route::get('/wallet', [WalletController::class, 'getWallet']);
        Route::post('/wallet/deposit', [WalletController::class, 'deposit']);
        Route::post('/wallet/withdraw', [WalletController::class, 'withdraw']);
        Route::get('/wallet/transactions', [WalletController::class, 'transactions']);

        /*
        |--------------------------------------------------------------------------
        | 🔔 Notification Routes
        |--------------------------------------------------------------------------
        */
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

        /*
        |--------------------------------------------------------------------------
        | 🏗️ Developer Routes
        |--------------------------------------------------------------------------
        */
        Route::prefix('developer')->group(function () {
            Route::get('/stats', [DeveloperController::class, 'stats']);
            Route::get('/projects', [DeveloperController::class, 'projects']);
            Route::get('/projects/{id}', [DeveloperController::class, 'getProject']);
            Route::post('/projects', [DeveloperController::class, 'store']);
            Route::post('/projects/{id}/updates', [DeveloperController::class, 'addUpdate']);
            Route::get('/transactions', [DeveloperController::class, 'transactions']);
            
            // Analytics & Performance
            Route::get('/funding-timeline', [DeveloperController::class, 'fundingTimeline']);
            Route::get('/funding-trends', [DeveloperController::class, 'fundingTimeline']); // Alias for frontend
            Route::get('/investor-engagement', [DeveloperController::class, 'investorEngagement']);
            Route::get('/revenue-breakdown', [DeveloperController::class, 'revenueBreakdown']);
            Route::get('/top-performing-project', [DeveloperController::class, 'topPerformingProject']);
            
            // Financial Dashboard & Wallet
            Route::get('/financial-dashboard', [DeveloperController::class, 'financialDashboard']);
            Route::get('/wallet', [DeveloperController::class, 'financialDashboard']); // Alias for frontend
            
            // Notifications & Trust Level
            Route::get('/notifications', [DeveloperController::class, 'notifications']);
            Route::get('/trust-level', [DeveloperController::class, 'trustLevel']);
        });

        /*
        |--------------------------------------------------------------------------
        | 🔒 Security Routes (2FA, Login History, Sessions)
        |--------------------------------------------------------------------------
        */
        Route::prefix('security')->group(function () {
            Route::get('/overview', [SecurityController::class, 'overview']);
            
            // 2FA Management (rate limited - 5 attempts per minute)
            Route::post('/2fa/enable', [SecurityController::class, 'enable2FA'])->middleware('throttle:5,1');
            Route::post('/2fa/verify', [SecurityController::class, 'verify2FA'])->middleware('throttle:5,1');
            Route::post('/2fa/disable', [SecurityController::class, 'disable2FA'])->middleware('throttle:5,1');
            
            // Login History & Sessions
            Route::get('/login-history', [SecurityController::class, 'loginHistory']);
            Route::get('/active-sessions', [SecurityController::class, 'activeSessions']);
            Route::delete('/sessions/{sessionId}', [SecurityController::class, 'terminateSession']);
            Route::post('/sessions/terminate-all', [SecurityController::class, 'terminateAllSessions']);
        });

        /*
        |--------------------------------------------------------------------------
        | 🆔 KYC Verification Routes
        |--------------------------------------------------------------------------
        */
        Route::prefix('kyc')->group(function () {
            Route::get('/status', [KYCController::class, 'status']);
            Route::post('/upload', [KYCController::class, 'upload']);
            Route::get('/documents/{kycId}', [KYCController::class, 'getDocuments']);
            
            // Admin routes
            Route::post('/approve/{kycId}', [KYCController::class, 'approve']);
            Route::post('/reject/{kycId}', [KYCController::class, 'reject']);
        });

        /*
        |--------------------------------------------------------------------------
        | 💰 Wallet Security Routes (Developer)
        |--------------------------------------------------------------------------
        */
        Route::prefix('wallet')->group(function () {
            Route::get('/developer', [WalletController::class, 'getDeveloperWallet']);
            Route::post('/pin/set', [WalletController::class, 'setTransactionPin'])->middleware('throttle:5,1');
            Route::post('/pin/verify', [WalletController::class, 'verifyPin'])->middleware('throttle:5,1');
            Route::post('/withdraw/secure', [WalletController::class, 'requestSecureWithdrawal'])->middleware('throttle:10,60'); // 10 per hour
            Route::post('/auto-withdraw/toggle', [WalletController::class, 'toggleAutoWithdraw']);
        });

        /*
        |--------------------------------------------------------------------------
        | 🧱 Project Management (for Authenticated Users)
        |--------------------------------------------------------------------------
        */
        Route::post('/projects', [ProjectController::class, 'store']);
        Route::patch('/projects/{id}', [ProjectController::class, 'update']);
        Route::get('/user/projects', [ProjectController::class, 'myProjects']);
        Route::post('/projects/{id}/submit', [ProjectController::class, 'submitForApproval']);
        Route::post('/projects/{id}/updates', [ProjectController::class, 'addUpdate']);

        /*
        |--------------------------------------------------------------------------
        | 🧑‍💼 Admin Routes (Used by Admin Dashboard)
        |--------------------------------------------------------------------------
        | These endpoints are consumed by the React AdminDashboard via adminApi.js
        | All routes are prefixed with /api/v1/admin
        |--------------------------------------------------------------------------
        */
        Route::prefix('admin')->group(function () {
            // --- Users ---
            Route::get('/users', [AdminController::class, 'users']);
            Route::post('/users/{id}/approve', [AdminController::class, 'approveUser']);
            Route::post('/users/{id}/reject', [AdminController::class, 'rejectUser']);
            Route::post('/users/{id}/toggle-admin', [AdminController::class, 'toggleAdmin']);
            
            // --- Phone Change Requests ---
            Route::get('/phone-change-requests', [AdminController::class, 'getPhoneChangeRequests']);
            Route::post('/phone-change/{id}/approve', [AdminController::class, 'approvePhoneChange']);
            Route::post('/phone-change/{id}/reject', [AdminController::class, 'rejectPhoneChange']);

            // --- Projects ---
            Route::get('/projects', [AdminController::class, 'projects']);
            Route::post('/projects/{id}/approve', [AdminController::class, 'approveProject']);

            // --- Investments ---
            Route::get('/investments', [AdminController::class, 'investments']);
            Route::patch('/investments/{id}/status', [AdminController::class, 'updateInvestmentStatus']);
            Route::post('/investments/{id}/status', [InvestmentAdminController::class, 'updateStatus']); // optional legacy route

            // --- Notifications (optional SSE or polling) ---
             Route::get('/notifications', [AdminController::class, 'notifications']);
            Route::get('/notifications/stream', [AdminController::class, 'stream']); // optional SSE
            Route::get('/stats', [AdminController::class, 'stats']);

            // --- Support Tickets ---
            Route::get('/support-tickets', [AdminController::class, 'getSupportTickets']);
            Route::get('/support-tickets/unread-count', [AdminController::class, 'getUnreadTicketCount']);
            Route::get('/support-tickets/{id}', [AdminController::class, 'getSupportTicketById']);
            Route::post('/support-tickets/{id}/respond', [AdminController::class, 'respondToTicket']);
            Route::patch('/support-tickets/{id}/status', [AdminController::class, 'updateTicketStatus']);
            Route::patch('/support-tickets/{id}/assign', [AdminController::class, 'assignTicket']);

            // --- News Management ---
            Route::get('/news', [NewsAdminController::class, 'index']);
            Route::post('/news', [NewsAdminController::class, 'store']);
            Route::put('/news/{id}', [NewsAdminController::class, 'update']);
            Route::post('/news/{id}', [NewsAdminController::class, 'update']); // For file uploads with _method
            Route::delete('/news/{id}', [NewsAdminController::class, 'destroy']);
            Route::post('/news/{id}/toggle-publish', [NewsAdminController::class, 'togglePublish']);
            Route::patch('/support-tickets/{id}/priority', [AdminController::class, 'updateTicketPriority']);

        });
    });
});
