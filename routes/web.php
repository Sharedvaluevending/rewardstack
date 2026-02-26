<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Business\DashboardController as BusinessDashboardController;
use App\Http\Controllers\Business\QRCodeController;
use App\Http\Controllers\Business\PromotionController;
use App\Http\Controllers\Business\AnalyticsController as BusinessAnalyticsController;
use App\Http\Controllers\Business\PrintStudioController;
use App\Http\Controllers\Business\MerchController;
use App\Http\Controllers\Business\PrintKitController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Controllers\Webhooks\PrintfulWebhookController;
use App\Http\Controllers\Webhooks\SendGridWebhookController;
use App\Http\Controllers\Crm\CampaignPromoController;
use App\Http\Controllers\Business\EmployeeController;
use App\Http\Controllers\Business\QRcadeController;
use App\Http\Controllers\Business\Crm\DashboardController as BusinessCrmDashboardController;
use App\Http\Controllers\Business\Crm\CustomerController as BusinessCrmCustomerController;
use App\Http\Controllers\Business\Crm\SegmentController as BusinessCrmSegmentController;
use App\Http\Controllers\Business\Crm\CampaignController as BusinessCrmCampaignController;
use App\Http\Controllers\Business\Crm\AutomationController as BusinessCrmAutomationController;
use App\Http\Controllers\Business\Crm\SettingsController as BusinessCrmSettingsController;
use App\Http\Controllers\Business\Crm\RecommendationsController as BusinessCrmRecommendationsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\BusinessManagementController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\QRcadeAdminController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\CrmController as AdminCrmController;
use App\Http\Controllers\Employee\RedemptionController;
use App\Http\Controllers\Portal\PortalController;
use App\Http\Controllers\Portal\PortalGameController;
use App\Http\Controllers\Portal\PortalRewardController;
use App\Http\Controllers\Portal\PortalLeaderboardController;
use App\Http\Controllers\Portal\BusinessSubscriptionController;
use App\Http\Controllers\ScanController;
use App\Http\Controllers\PlayController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\PublicBusinessController;
use App\Http\Controllers\PublicPromoEmailController;
use App\Http\Controllers\EmailUnsubscribeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Webhook Routes (No authentication required)
|--------------------------------------------------------------------------
*/

// Stripe webhooks cannot include custom headers like X-Webhook-Token.
// We rely on Stripe's signature verification via STRIPE_WEBHOOK_SECRET inside StripeWebhookController.
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->middleware('throttle:240,1')
    ->name('webhooks.stripe');
Route::post('/webhooks/printful', [PrintfulWebhookController::class, 'handle'])
    ->middleware('webhook.token:printful')
    ->name('webhooks.printful');
Route::post('/webhooks/sendgrid/events', [SendGridWebhookController::class, 'handle'])
    ->middleware(['webhook.sendgrid', 'throttle:webhook-sendgrid'])
    ->name('webhooks.sendgrid.events');

/*
|--------------------------------------------------------------------------
| Health Check (no auth)
|--------------------------------------------------------------------------
*/

Route::get('/health', HealthController::class)->name('health');

/*
|--------------------------------------------------------------------------
| Short Redirect Routes (For simplified QR codes)
|--------------------------------------------------------------------------
*/

Route::get('/r/{rewardCode}', [RedemptionController::class, 'quickRedeemReward'])->name('short.reward');
Route::get('/t/{tokenCode}', [RedemptionController::class, 'quickRedeemToken'])->name('short.token');

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', [PublicPageController::class, 'home'])->name('home');
// Backwards-compatible "home" route (some devices/PWA shortcuts may still open /home).
Route::get('/home', function (\Illuminate\Http\Request $request) {
    $user = $request->user();
    if (!$user) {
        return redirect()->route('home');
    }
    return match ($user->role) {
        'admin' => redirect()->route('admin.dashboard'),
        'business' => redirect()->route('business.dashboard'),
        'employee' => redirect()->route('employee.redeem'),
        'user', 'customer' => redirect()->route('portal.dashboard'),
        default => redirect()->route('home'),
    };
})->middleware('web')->name('home.legacy');

Route::get('/features', [PublicPageController::class, 'features'])->name('features');

Route::get('/pricing', [PublicPageController::class, 'pricing'])->name('pricing');

Route::get('/demo', [PublicPageController::class, 'demo'])->name('demo');

// Referral signup landing page
Route::get('/join/{code}', [App\Http\Controllers\ReferralLandingController::class, 'show'])->name('referral.landing');

Route::get('/privacy', [PublicPageController::class, 'privacy'])->name('privacy');

Route::get('/terms', [PublicPageController::class, 'terms'])->name('terms');

// Signed campaign promo save (public, signed)
Route::get('/crm/promo/save', [CampaignPromoController::class, 'save'])
    ->middleware('signed')
    ->name('crm.campaigns.promo.save');

// Signed unsubscribe endpoint (no auth)
Route::get('/email/unsubscribe', [EmailUnsubscribeController::class, 'unsubscribe'])
    ->middleware('signed')
    ->name('email.unsubscribe');

/*
|--------------------------------------------------------------------------
| QR Code Scan Routes (Public)
|--------------------------------------------------------------------------
*/

Route::get('/m/{code}', [\App\Http\Controllers\MerchTagController::class, 'scan'])
    ->middleware('throttle:scan')
    ->name('merch.scan');
Route::get('/m/{code}/claim', [\App\Http\Controllers\MerchTagController::class, 'claimView'])
    ->name('merch.claim.view');
Route::post('/m/{code}/claim', [\App\Http\Controllers\MerchTagController::class, 'claim'])
    ->middleware('auth')
    ->name('merch.claim');

Route::get('/s/{code}', [ScanController::class, 'scan'])->middleware('throttle:scan')->name('scan');
Route::get('/promo/{code}', [ScanController::class, 'showPromotion'])->middleware('throttle:scan')->name('promotion.show');
// Public business page (no redemption QR/code shown)
Route::get('/b/{business:slug}', [PublicBusinessController::class, 'show'])->name('public.business.show');
// Optional consent-based geo enrichment (called from frontend after scan lands on our pages)
Route::get('/api/public/scan/geo', [ScanController::class, 'updateScanGeo'])->middleware('throttle:scan-geo')->name('api.public.scan.geo');
// Guest-friendly: email a link back to a promo (rate limited)
Route::post('/api/public/promo/email', [PublicPromoEmailController::class, 'send'])->middleware('throttle:promo-email')->name('api.public.promo.email');

// Employee quick-redeem (requires auth but accessed from promo page)
Route::get('/redeem/{code}', [RedemptionController::class, 'quickRedeem'])->middleware('auth')->name('redeem.quick');
// Per-user promo QR/code redemption (customer shows this; staff scans it)
Route::get('/redeem/token/{tokenCode}', [RedemptionController::class, 'quickRedeemToken'])->middleware(['auth', 'throttle:redeem-token-lookup'])->name('redeem.token');
// Reward redemption (customer shows QR code; staff scans it)
Route::get('/redeem/reward/{rewardCode}', [RedemptionController::class, 'quickRedeemReward'])->middleware(['auth', 'throttle:redeem-token-lookup'])->name('redeem.reward');
Route::post('/redeem/reward/{rewardCode}', [RedemptionController::class, 'redeemReward'])->middleware('auth')->name('redeem.reward.process');
// AJAX: token info lookup for staff redemption UI (returns punch-card progress / prefills)
Route::get('/employee/token-info/{tokenCode}', [RedemptionController::class, 'tokenInfo'])->middleware(['auth', 'throttle:redeem-token-lookup'])->name('employee.token.info');

/*
|--------------------------------------------------------------------------
| Game Play Routes (Public/Auth)
|--------------------------------------------------------------------------
*/



// Game API routes (JSON responses for anonymous play)
Route::middleware(['throttle:game'])->withoutMiddleware([\App\Http\Middleware\HandleInertiaRequests::class, \App\Http\Middleware\VerifyCsrfToken::class])->group(function () {
    Route::post('/api/play/{code}/game/{game}/start', [PlayController::class, 'startSession']);
    Route::post('/api/play/session/{session}/play', [PlayController::class, 'startPlaying']);
    Route::post('/api/play/session/{session}/score', [PlayController::class, 'submitScore']);
    Route::post('/api/game/verify-location', [PlayController::class, 'verifyLocation']);

    // Debug logger endpoint (used for mobile touch debugging)
    if (config('app.wordsearch_debug_logger')) {
    Route::post('/api/debug/wordsearch', function (\Illuminate\Http\Request $request) {
        $payload = $request->validate([
            'entries' => 'required|array|max:25',
            'entries.*.sessionId' => 'nullable|string|max:100',
            'entries.*.runId' => 'nullable|string|max:100',
            'entries.*.hypothesisId' => 'nullable|string|max:10',
            'entries.*.location' => 'nullable|string|max:200',
            'entries.*.message' => 'nullable|string|max:200',
            'entries.*.data' => 'nullable|array',
            'entries.*.timestamp' => 'nullable|integer',
        ]);

        $path = '/var/www/.cursor/debug.log';
        foreach ($payload['entries'] as $entry) {
            // Avoid logging any sensitive values; this endpoint is only for touch coordinate debugging
            $line = json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n";
            @file_put_contents($path, $line, FILE_APPEND);
        }

        return response()->json(['ok' => true]);
    });
    }
});

// Public game page routes (Inertia pages)
Route::middleware(['throttle:game-pages'])->prefix('play')->name('play.')->group(function () {
    Route::get('/{code}', [PlayController::class, 'index'])->name('index');
    Route::get('/{code}/game/{game}', [PlayController::class, 'game'])->name('game');
});

/*
|--------------------------------------------------------------------------
| Authentication Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    // Login
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    
    // Business Registration
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');

    // Customer/User Portal Registration
    Route::get('/portal/join', [App\Http\Controllers\Auth\CustomerRegisterController::class, 'create'])->name('customer.register');
    Route::post('/portal/join', [App\Http\Controllers\Auth\CustomerRegisterController::class, 'store'])->middleware('throttle:register');

    // Employee Invitation Acceptance
    Route::get('/employee/accept/{token}', [App\Http\Controllers\Auth\EmployeeInviteController::class, 'show'])->name('employee.invite.show');
    Route::post('/employee/accept/{token}', [App\Http\Controllers\Auth\EmployeeInviteController::class, 'accept'])->name('employee.invite.accept');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::post('/cross-promo/{crossPromotion}/claim/{promotion}', [ScanController::class, 'claimCrossPromoOffer'])->name('cross-promo.claim');
    Route::put('/account/password', [App\Http\Controllers\AccountController::class, 'updatePassword'])->name('account.password.update');
    
    // Notifications
    Route::post('/notifications/{id}/read', [App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
});

/*
|--------------------------------------------------------------------------
| User Portal Routes (Customer/Player)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:customer'])->prefix('portal')->name('portal.')->group(function () {
    // Dashboard
    Route::get('/', [PortalController::class, 'index'])->name('dashboard');
    Route::get('/profile', [PortalController::class, 'profile'])->name('profile');
    Route::put('/profile', [PortalController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/avatar', [PortalController::class, 'uploadAvatar'])->name('profile.avatar');
    Route::delete('/profile/avatar', [PortalController::class, 'removeAvatar'])->name('profile.avatar.remove');
    
    // Games
    Route::get('/games', [PortalGameController::class, 'index'])->name('games');
    Route::get('/games/nearby', [PortalGameController::class, 'nearby'])->name('games.nearby');
    Route::get('/games/history', [PortalGameController::class, 'history'])->name('games.history');
    
    // Levels & Badges
    Route::get('/levels', [PortalController::class, 'levels'])->name('levels');
    
    // Reward Details (individual reward pages - keep for reward detail views)
    Route::get('/rewards', [PortalRewardController::class, 'index'])->name('rewards.index');
    Route::get('/rewards/{reward}', [PortalRewardController::class, 'show'])->name('rewards.show');
    Route::post('/rewards/{reward}/claim', [PortalRewardController::class, 'claim'])->name('rewards.claim');
    Route::post('/rewards/claim-by-code', [PortalRewardController::class, 'claimByCode'])->name('rewards.claim-by-code');
    
    // Scans & Promotions
    Route::get('/scans', [App\Http\Controllers\Portal\PortalScanController::class, 'index'])->name('scans');
    Route::delete('/scans/{scan}', [App\Http\Controllers\Portal\PortalScanController::class, 'destroy'])->name('scans.destroy');
    Route::get('/partner-deals', [App\Http\Controllers\Portal\PartnerDealsController::class, 'index'])->name('partner-deals');
    Route::post('/qr-codes/{qrCode}/save', [App\Http\Controllers\Portal\PortalSavedQRCodeController::class, 'store'])->name('qr-codes.save');
    Route::delete('/qr-codes/{qrCode}/unsave', [App\Http\Controllers\Portal\PortalSavedQRCodeController::class, 'destroy'])->name('qr-codes.unsave');

    // Merch (Ambassador)
    Route::get('/merch', [App\Http\Controllers\Portal\PortalMerchController::class, 'index'])->name('merch');
    Route::get('/merch/ping', [App\Http\Controllers\Portal\PortalMerchController::class, 'ping'])->name('merch.ping');
    
    // Badges
    Route::get('/badges', [PortalController::class, 'badges'])->name('badges');
    Route::post('/badges/{badge}/feature', [PortalController::class, 'toggleBadgeFeature'])->name('badges.feature');
    
    // Leaderboards
    Route::get('/leaderboards', [PortalLeaderboardController::class, 'index'])->name('leaderboards');
    Route::get('/leaderboards/{leaderboard}', [PortalLeaderboardController::class, 'show'])->name('leaderboards.show');

    // Referral Army 💰
    Route::get('/referrals', [App\Http\Controllers\Portal\ReferralController::class, 'index'])->name('referrals');
    Route::post('/referrals/payout', [App\Http\Controllers\Portal\ReferralController::class, 'requestPayout'])->name('referrals.payout');
    Route::post('/referrals/regenerate', [App\Http\Controllers\Portal\ReferralController::class, 'regenerateCode'])->name('referrals.regenerate');

    // Stripe Connect (Bank Payouts)
    Route::get('/stripe/connect', [App\Http\Controllers\Portal\StripeConnectController::class, 'connect'])->name('stripe.connect');
    Route::get('/stripe/return', [App\Http\Controllers\Portal\StripeConnectController::class, 'return'])->name('stripe.return');
    Route::get('/stripe/refresh', [App\Http\Controllers\Portal\StripeConnectController::class, 'refresh'])->name('stripe.refresh');
    Route::get('/stripe/dashboard', [App\Http\Controllers\Portal\StripeConnectController::class, 'dashboard'])->name('stripe.dashboard');
    Route::post('/stripe/reset', [App\Http\Controllers\Portal\StripeConnectController::class, 'reset'])->name('stripe.reset');
    Route::post('/stripe/disconnect', [App\Http\Controllers\Portal\StripeConnectController::class, 'disconnect'])->name('stripe.disconnect');

    // Email subscriptions (explicit opt-in for CRM)
    Route::get('/subscriptions', [BusinessSubscriptionController::class, 'index'])->name('subscriptions');
    Route::post('/subscriptions/{business}/subscribe', [BusinessSubscriptionController::class, 'subscribe'])->name('subscriptions.subscribe');
    Route::post('/subscriptions/{business}/unsubscribe', [BusinessSubscriptionController::class, 'unsubscribe'])->name('subscriptions.unsubscribe');
});

/*
|--------------------------------------------------------------------------
| Business Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:business'])->prefix('business')->name('business.')->group(function () {
    // Billing routes - accessible even if trial expired
    Route::get('/billing', [App\Http\Controllers\Business\BillingController::class, 'index'])->name('billing');
    Route::post('/billing/subscribe', [App\Http\Controllers\Business\BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::get('/billing/portal', [App\Http\Controllers\Business\BillingController::class, 'portal'])->name('billing.portal');
    
    // Onboarding routes - accessible even if trial expired
    Route::post('/onboarding/complete-step', [App\Http\Controllers\Business\OnboardingController::class, 'completeStep'])->name('onboarding.complete-step');
    Route::post('/onboarding/dismiss', [App\Http\Controllers\Business\OnboardingController::class, 'dismiss'])->name('onboarding.dismiss');
    Route::post('/onboarding/reopen', [App\Http\Controllers\Business\OnboardingController::class, 'reopen'])->name('onboarding.reopen');
    Route::get('/onboarding/progress', [App\Http\Controllers\Business\OnboardingController::class, 'progress'])->name('onboarding.progress');

    // Help routes - accessible even if trial expired
    Route::get('/help/faq', [App\Http\Controllers\Business\HelpController::class, 'faq'])->name('help.faq');
    
    // All other business routes require active subscription
    Route::middleware('subscription.active')->group(function () {
    // Dashboard
    Route::get('/dashboard', [BusinessDashboardController::class, 'index'])->name('dashboard');
    
    // QR Codes
    Route::resource('qr-codes', QRCodeController::class);
    Route::get('/qr-codes/{qrCode}/check-delete', [QRCodeController::class, 'checkDelete'])->name('qr-codes.check-delete');
    Route::post('/qr-codes/{qrCode}/duplicate', [QRCodeController::class, 'duplicate'])->name('qr-codes.duplicate');
    Route::get('/qr-codes/{qrCode}/download/{format}', [QRCodeController::class, 'download'])->name('qr-codes.download');
    
    // Promotions
    Route::resource('promotions', PromotionController::class);
    Route::get('/promotions/{promotion}/check-delete', [PromotionController::class, 'checkDelete'])->name('promotions.check-delete');
    Route::post('/promotions/{promotion}/toggle', [PromotionController::class, 'toggle'])->name('promotions.toggle');
    Route::get('/promotion-templates', [PromotionController::class, 'templates'])->name('promotions.templates');
    Route::get('/promotion-ideas', [PromotionController::class, 'ideas'])->name('promotions.ideas');
    
    // QRcade (Games)
    Route::get('/qrcade', [QRcadeController::class, 'index'])->name('qrcade');
    Route::get('/qrcade/how-to', [QRcadeController::class, 'howTo'])->name('qrcade.howto');
    Route::get('/qrcade/games', [QRcadeController::class, 'games'])->name('qrcade.games');
    Route::post('/qrcade/games/{game}/toggle', [QRcadeController::class, 'toggleGame'])->name('qrcade.games.toggle');
    Route::put('/qrcade/games/{game}', [QRcadeController::class, 'updateGame'])->name('qrcade.games.update');
    Route::get('/qrcade/rewards', [QRcadeController::class, 'rewards'])->name('qrcade.rewards');
    Route::put('/qrcade/rewards/{qrCodeGame}', [QRcadeController::class, 'updateRewards'])->name('qrcade.rewards.update');
    Route::get('/qrcade/schedule', [QRcadeController::class, 'schedule'])->name('qrcade.schedule');
    Route::put('/qrcade/schedule/{businessGame}', [QRcadeController::class, 'updateSchedule'])->name('qrcade.schedule.update');
    Route::get('/qrcade/leaderboards', [QRcadeController::class, 'leaderboards'])->name('qrcade.leaderboards');
    Route::post('/qrcade/leaderboards', [QRcadeController::class, 'createLeaderboard'])->name('qrcade.leaderboards.store');
    Route::get('/qrcade/leaderboards/{leaderboard}', [QRcadeController::class, 'showLeaderboard'])->name('qrcade.leaderboards.show');
    Route::get('/qrcade/leaderboards/{leaderboard}/edit', [QRcadeController::class, 'editLeaderboard'])->name('qrcade.leaderboards.edit');
    Route::put('/qrcade/leaderboards/{leaderboard}', [QRcadeController::class, 'updateLeaderboard'])->name('qrcade.leaderboards.update');
    Route::delete('/qrcade/leaderboards/{leaderboard}', [QRcadeController::class, 'deleteLeaderboard'])->name('qrcade.leaderboards.destroy');
    Route::post('/qrcade/leaderboards/{leaderboard}/toggle', [QRcadeController::class, 'toggleLeaderboard'])->name('qrcade.leaderboards.toggle');
    Route::get('/qrcade/analytics', [QRcadeController::class, 'analytics'])->name('qrcade.analytics');
    Route::post('/qrcade/packs/{gamePack}/purchase', [QRcadeController::class, 'purchasePack'])->name('qrcade.packs.purchase');
    
    // Partnerships & Cross-Promos
    Route::get('/partnerships', [App\Http\Controllers\Business\PartnershipController::class, 'index'])->name('partnerships');
    Route::delete('/partnerships/{partnership}', [App\Http\Controllers\Business\PartnershipController::class, 'destroy'])->name('partnerships.destroy');
    Route::get('/partnerships/search', [App\Http\Controllers\Business\PartnershipController::class, 'searchBusinesses'])->name('partnerships.search');
    Route::post('/partnerships/request', [App\Http\Controllers\Business\PartnershipController::class, 'sendRequest'])->name('partnerships.request');
    Route::post('/partnerships/{partnership}/accept', [App\Http\Controllers\Business\PartnershipController::class, 'acceptRequest'])->name('partnerships.accept');
    Route::post('/partnerships/{partnership}/decline', [App\Http\Controllers\Business\PartnershipController::class, 'declineRequest'])->name('partnerships.decline');
    Route::post('/partnerships/{partnership}/cancel', [App\Http\Controllers\Business\PartnershipController::class, 'cancelRequest'])->name('partnerships.cancel');
    Route::post('/partnerships/cross-promo', [App\Http\Controllers\Business\PartnershipController::class, 'createCrossPromo'])->name('partnerships.cross-promo');
    Route::get('/partnerships/cross-promo/{crossPromotion}/edit', [App\Http\Controllers\Business\PartnershipController::class, 'editCrossPromo'])->name('partnerships.cross-promo.edit');
    Route::put('/partnerships/cross-promo/{crossPromotion}', [App\Http\Controllers\Business\PartnershipController::class, 'updateCrossPromo'])->name('partnerships.cross-promo.update');
    Route::post('/partnerships/cross-promo/{crossPromotion}/pause', [App\Http\Controllers\Business\PartnershipController::class, 'pauseCrossPromo'])->name('partnerships.cross-promo.pause');
    Route::post('/partnerships/cross-promo/{crossPromotion}/resume', [App\Http\Controllers\Business\PartnershipController::class, 'resumeCrossPromo'])->name('partnerships.cross-promo.resume');
    Route::get('/partnerships/cross-promo/{crossPromotion}/rules', [App\Http\Controllers\Business\PartnershipController::class, 'rulesNegotiation'])->name('partnerships.cross-promo.rules');
    Route::post('/partnerships/cross-promo/{crossPromotion}/rules/agree', [App\Http\Controllers\Business\PartnershipController::class, 'agreeRules'])->name('partnerships.cross-promo.rules.agree');
    Route::get('/partnerships/cross-promo/{crossPromotion}/analytics', [App\Http\Controllers\Business\CrossPromoAnalyticsController::class, 'show'])->name('partnerships.cross-promo.analytics');
    Route::get('/partnerships/{partnership}/analytics', [App\Http\Controllers\Business\PartnershipController::class, 'partnerAnalytics'])->name('partnerships.analytics');
    Route::post('/partnerships/cross-promo/{crossPromotion}/accept', [App\Http\Controllers\Business\PartnershipController::class, 'acceptCrossPromo'])->name('partnerships.cross-promo.accept');
    Route::post('/partnerships/cross-promo/{crossPromotion}/decline', [App\Http\Controllers\Business\PartnershipController::class, 'declineCrossPromo'])->name('partnerships.cross-promo.decline');
    Route::get('/partnerships/partners', [App\Http\Controllers\Business\PartnershipController::class, 'getAcceptedPartners'])->name('partnerships.partners');
    
    // Stackable Deals (Simplified - ONE global pool)
    Route::get('/stackable-pools', [App\Http\Controllers\Business\StackablePoolController::class, 'index'])->name('stackable-pools');
    Route::post('/stackable/set', [App\Http\Controllers\Business\StackablePoolController::class, 'setStackable'])->name('stackable.set');
    Route::post('/stackable/remove', [App\Http\Controllers\Business\StackablePoolController::class, 'removeStackable'])->name('stackable.remove');
    
    // Analytics
    Route::get('/analytics', [BusinessAnalyticsController::class, 'index'])->name('analytics');
    Route::get('/analytics/finance', [BusinessAnalyticsController::class, 'finance'])->name('analytics.finance');
    Route::get('/analytics/partnerships', [BusinessAnalyticsController::class, 'partnerships'])->name('analytics.partnerships');
    Route::get('/analytics/scans', [BusinessAnalyticsController::class, 'scans'])->name('analytics.scans');
    Route::get('/analytics/redemptions', [BusinessAnalyticsController::class, 'redemptions'])->name('analytics.redemptions');
    Route::get('/analytics/merch', [BusinessAnalyticsController::class, 'merch'])->name('analytics.merch');
    Route::get('/analytics/export', [BusinessAnalyticsController::class, 'export'])->name('analytics.export');
    Route::get('/analytics/report', [BusinessAnalyticsController::class, 'report'])->name('analytics.report');
    
    // AI Insights
    Route::get('/ai-insights/basic', [App\Http\Controllers\Business\AIInsightsController::class, 'basic'])->name('ai-insights.basic');
    Route::get('/ai-insights/advanced', [App\Http\Controllers\Business\AIInsightsController::class, 'advanced'])->name('ai-insights.advanced');
    Route::post('/ai-insights/generate', [App\Http\Controllers\Business\AIInsightsController::class, 'generate'])->name('ai-insights.generate');
    
    // Print Studio
    Route::get('/print-studio', [PrintStudioController::class, 'index'])->name('print-studio');
    Route::post('/print-studio/generate', [PrintStudioController::class, 'generate'])->name('print-studio.generate');
    Route::get('/print-studio/download/{job}', [PrintStudioController::class, 'download'])->name('print-studio.download');
    
    // Sticker / Print Kits (Avery DPO)
    Route::get('/print-kits', [PrintKitController::class, 'index'])->name('print-kits');
    Route::post('/print-kits', [PrintKitController::class, 'createOrder'])->name('print-kits.create');
    Route::delete('/print-kits/{order}', [PrintKitController::class, 'destroy'])->name('print-kits.destroy');
    Route::get('/print-kits/orders', [PrintKitController::class, 'orders'])->name('print-kits.orders');
    Route::get('/print-kits/checkout/{order}', [PrintKitController::class, 'checkout'])->name('print-kits.checkout');
    Route::post('/print-kits/checkout/{order}/pay', [PrintKitController::class, 'pay'])->name('print-kits.pay');
    Route::get('/print-kits/{order}/avery', [PrintKitController::class, 'avery'])->name('print-kits.avery');
    Route::get('/print-kits/launch/{sizeKey}', [PrintKitController::class, 'launchAvery'])->name('print-kits.launch');
    
    // Merch Store
    Route::get('/merch', [MerchController::class, 'index'])->name('merch');
    Route::get('/merch/products', [MerchController::class, 'products'])->name('merch.products');
    Route::post('/merch/preview', [MerchController::class, 'generatePreview'])->name('merch.preview');
    Route::post('/merch/quote', [MerchController::class, 'quote'])->middleware('throttle:orders')->name('merch.quote');
    Route::post('/merch/order', [MerchController::class, 'order'])->middleware('throttle:orders')->name('merch.order');
    Route::get('/merch/orders', [MerchController::class, 'orders'])->name('merch.orders');
    
    // Employees
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/create', [EmployeeController::class, 'create'])->name('employees.create');
    Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::post('/employees/invite/{invite}/resend', [EmployeeController::class, 'resendInvite'])->name('employees.invite.resend');
    Route::delete('/employees/invite/{invite}', [EmployeeController::class, 'cancelInvite'])->name('employees.invite.cancel');
    
    // Settings
    Route::get('/settings', [BusinessDashboardController::class, 'settings'])->name('settings');
    Route::put('/settings', [BusinessDashboardController::class, 'updateSettings'])->name('settings.update');
    Route::post('/settings/logo', [BusinessDashboardController::class, 'uploadLogo'])->name('settings.logo');
    Route::delete('/settings/logo', [BusinessDashboardController::class, 'removeLogo'])->name('settings.logo.remove');

    // CRM (email + automations + segments)
    Route::prefix('crm')->name('crm.')->group(function () {
        Route::get('/', [BusinessCrmDashboardController::class, 'index'])->name('index');
        Route::get('/customers', [BusinessCrmCustomerController::class, 'index'])->name('customers');
        Route::get('/recommendations', [BusinessCrmRecommendationsController::class, 'index'])->name('recommendations');
        Route::post('/recommendations/generate', [BusinessCrmRecommendationsController::class, 'generate'])->middleware('throttle:5,1')->name('recommendations.generate');

        Route::get('/segments', [BusinessCrmSegmentController::class, 'index'])->name('segments');
        Route::post('/segments', [BusinessCrmSegmentController::class, 'store'])->name('segments.store');
        Route::put('/segments/{segment}', [BusinessCrmSegmentController::class, 'update'])->name('segments.update');
        Route::delete('/segments/{segment}', [BusinessCrmSegmentController::class, 'destroy'])->name('segments.destroy');

        Route::get('/campaigns', [BusinessCrmCampaignController::class, 'index'])->name('campaigns');
        Route::get('/campaigns/create', [BusinessCrmCampaignController::class, 'create'])->name('campaigns.create');
        Route::post('/campaigns', [BusinessCrmCampaignController::class, 'store'])->name('campaigns.store');
        Route::get('/campaigns/{campaign}', [BusinessCrmCampaignController::class, 'show'])->name('campaigns.show');
        Route::get('/campaigns/{campaign}/edit', [BusinessCrmCampaignController::class, 'edit'])->name('campaigns.edit');
        Route::put('/campaigns/{campaign}', [BusinessCrmCampaignController::class, 'update'])->name('campaigns.update');
        Route::post('/campaigns/{campaign}/queue', [BusinessCrmCampaignController::class, 'queue'])->name('campaigns.queue');
        Route::delete('/campaigns/{campaign}', [BusinessCrmCampaignController::class, 'destroy'])->name('campaigns.destroy');

        Route::get('/automations', [BusinessCrmAutomationController::class, 'index'])->name('automations');
        Route::post('/automations/run', [BusinessCrmAutomationController::class, 'runNow'])->name('automations.run');
        Route::post('/automations/{automation}/toggle', [BusinessCrmAutomationController::class, 'toggle'])->name('automations.toggle');
        Route::get('/settings', [BusinessCrmSettingsController::class, 'index'])->name('settings');
    });
    
    // Merch Checkout
    Route::get('/merch/checkout/{order}', [MerchController::class, 'checkout'])->name('merch.checkout');
    Route::post('/merch/checkout/{order}/pay', [MerchController::class, 'processPayment'])->name('merch.pay');
    });
});

/*
|--------------------------------------------------------------------------
| Employee Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:employee'])->prefix('employee')->name('employee.')->group(function () {
    Route::get('/redeem', [RedemptionController::class, 'index'])->name('redeem');
    Route::get('/activity', [RedemptionController::class, 'activity'])->name('activity');
});

// Redemption endpoint - accessible by both employees AND business owners
Route::middleware(['auth', 'throttle:redeem-staff'])->post('/employee/redeem/{code}', [RedemptionController::class, 'redeem'])->name('employee.redeem.process');

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    
    // Business Management
    Route::resource('businesses', BusinessManagementController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
    Route::post('/businesses/{business}/toggle', [BusinessManagementController::class, 'toggle'])->name('businesses.toggle');
    Route::post('/businesses/{business}/toggle-testing', [BusinessManagementController::class, 'toggleTestingAccount'])->name('businesses.toggle-testing');
    Route::post('/businesses/{business}/impersonate', [BusinessManagementController::class, 'impersonate'])->name('businesses.impersonate');
    
    // Product Management
    Route::resource('products', ProductController::class)->only(['index', 'edit', 'update']);

    // User Management
    Route::resource('users', UserManagementController::class);
    Route::post('/users/{user}/toggle', [UserManagementController::class, 'toggle'])->name('users.toggle');
    
    // QRcade Admin
    Route::get('/qrcade', [QRcadeAdminController::class, 'index'])->name('qrcade');
    Route::get('/qrcade/games', [QRcadeAdminController::class, 'games'])->name('qrcade.games');
    Route::post('/qrcade/games', [QRcadeAdminController::class, 'storeGame'])->name('qrcade.games.store');
    Route::put('/qrcade/games/{game}', [QRcadeAdminController::class, 'updateGame'])->name('qrcade.games.update');
    Route::get('/qrcade/packs', [QRcadeAdminController::class, 'packs'])->name('qrcade.packs');
    Route::post('/qrcade/packs', [QRcadeAdminController::class, 'storePack'])->name('qrcade.packs.store');
    Route::get('/qrcade/seasonal', [QRcadeAdminController::class, 'seasonal'])->name('qrcade.seasonal');
    Route::get('/qrcade/analytics', [QRcadeAdminController::class, 'analytics'])->name('qrcade.analytics');

    // CRM oversight
    Route::get('/crm', [AdminCrmController::class, 'index'])->name('crm');
    
    // Analytics
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics');
    Route::get('/analytics/revenue', [AdminAnalyticsController::class, 'revenue'])->name('analytics.revenue');
    Route::get('/analytics/usage', [AdminAnalyticsController::class, 'usage'])->name('analytics.usage');

    // Onboarding QR (Portal Join flyer)
    Route::get('/onboarding-qr', [\App\Http\Controllers\Admin\OnboardingQrController::class, 'index'])->name('onboarding-qr');
    
    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/subscriptions', [SettingsController::class, 'subscriptions'])->name('settings.subscriptions');

    // Referral Army Management
    Route::get('/referrals', [App\Http\Controllers\Admin\ReferralManagementController::class, 'index'])->name('referrals');
    Route::get('/referrals/payouts', [App\Http\Controllers\Admin\ReferralManagementController::class, 'payouts'])->name('referrals.payouts');
    Route::post('/referrals/payouts/{payout}/paid', [App\Http\Controllers\Admin\ReferralManagementController::class, 'markPaid'])->name('referrals.payouts.paid');
    Route::post('/referrals/payouts/{payout}/reject', [App\Http\Controllers\Admin\ReferralManagementController::class, 'rejectPayout'])->name('referrals.payouts.reject');
    Route::post('/referrals/payouts/run', [App\Http\Controllers\Admin\ReferralManagementController::class, 'runAutoPayouts'])->name('referrals.payouts.run');
    Route::get('/referrals/referrer/{user}', [App\Http\Controllers\Admin\ReferralManagementController::class, 'showReferrer'])->name('referrals.referrer');
    Route::post('/referrals/approve-commissions', [App\Http\Controllers\Admin\ReferralManagementController::class, 'approveCommissions'])->name('referrals.approve');

    // Business Health & Follow-ups
    Route::get('/business-health', [App\Http\Controllers\Admin\BusinessHealthController::class, 'index'])->name('business-health');
    Route::get('/business-health/{business}', [App\Http\Controllers\Admin\BusinessHealthController::class, 'show'])->name('business-health.show');
    Route::post('/business-health/follow-ups', [App\Http\Controllers\Admin\BusinessHealthController::class, 'logFollowUp'])->name('business-health.follow-ups.log');
    Route::post('/business-health/follow-ups/{followUp}/snooze', [App\Http\Controllers\Admin\BusinessHealthController::class, 'snoozeFollowUp'])->name('business-health.follow-ups.snooze');

    // Merch Management
    Route::get('/merch', [App\Http\Controllers\Admin\MerchManagementController::class, 'index'])->name('merch');
    Route::post('/merch/sync', [App\Http\Controllers\Admin\MerchManagementController::class, 'sync'])->name('merch.sync');
    Route::post('/merch/products', [App\Http\Controllers\Admin\MerchManagementController::class, 'store'])->name('merch.products.store');
    Route::put('/merch/products/{product}', [App\Http\Controllers\Admin\MerchManagementController::class, 'update'])->name('merch.products.update');
    Route::post('/merch/products/{product}/toggle', [App\Http\Controllers\Admin\MerchManagementController::class, 'toggle'])->name('merch.products.toggle');
    Route::post('/merch/products/{product}/image', [App\Http\Controllers\Admin\MerchManagementController::class, 'uploadImage'])->name('merch.products.image');
    Route::delete('/merch/products/{product}/image', [App\Http\Controllers\Admin\MerchManagementController::class, 'removeImage'])->name('merch.products.image.remove');
    Route::delete('/merch/products/{product}', [App\Http\Controllers\Admin\MerchManagementController::class, 'destroy'])->name('merch.products.destroy');
    Route::post('/merch/products/reorder', [App\Http\Controllers\Admin\MerchManagementController::class, 'reorder'])->name('merch.products.reorder');
    Route::get('/merch/orders', [App\Http\Controllers\Admin\MerchManagementController::class, 'orders'])->name('merch.orders');
    Route::get('/merch/orders/{order}', [App\Http\Controllers\Admin\MerchManagementController::class, 'orderShow'])->name('merch.orders.show');
    Route::put('/merch/orders/{order}', [App\Http\Controllers\Admin\MerchManagementController::class, 'orderUpdate'])->name('merch.orders.update');
    Route::post('/merch/orders/{order}/refund', [App\Http\Controllers\Admin\MerchManagementController::class, 'orderRefund'])->name('merch.orders.refund');
});

/*
|--------------------------------------------------------------------------
| API Routes (for AJAX calls within app)
|--------------------------------------------------------------------------
*/

// Business-only API routes
Route::middleware(['auth', 'role:business'])->prefix('api')->group(function () {
    // QR Code Preview
    Route::post('/qr/preview', [QRCodeController::class, 'preview'])->name('api.qr.preview');
    
    // Analytics Data
    Route::get('/analytics/chart-data', [BusinessAnalyticsController::class, 'chartData'])->name('api.analytics.chart');
    
    // AI Insights
    Route::get('/insights', [BusinessAnalyticsController::class, 'insights'])->name('api.insights');
});

// API routes accessible by any authenticated user (e.g. game players)
Route::middleware('auth')->prefix('api')->group(function () {
    // Game Session
    Route::post('/game/verify-location', [PlayController::class, 'verifyLocation'])->name('api.game.verify-location');
});
