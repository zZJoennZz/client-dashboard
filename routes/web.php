<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessMediaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MemberAccountController;
use App\Http\Controllers\MemberActivityController;
use App\Http\Controllers\MemberBusinessProfileController;
use App\Http\Controllers\MemberCommunicationPreferenceController;
use App\Http\Controllers\MemberEventController;
use App\Http\Controllers\MemberFileController;
use App\Http\Controllers\MemberManagementController;
use App\Http\Controllers\MemberReportController;
use App\Http\Controllers\MemberResourceController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\SuperAdminBusinessController;
use App\Http\Controllers\SuperAdminCommunicationController;
use App\Http\Controllers\SuperAdminContentController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdminEngagementController;
use App\Http\Controllers\SuperAdminEventController;
use App\Http\Controllers\SuperAdminExportController;
use App\Http\Controllers\SuperAdminMarketingController;
use App\Http\Controllers\SuperAdminMembershipController;
use App\Http\Controllers\SuperAdminOrderServiceController;
use App\Http\Controllers\SuperAdminPlatformSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PublicPageController::class, 'home'])->name('home');
Route::get('/benefits', [PublicPageController::class, 'benefits'])->name('benefits');
Route::get('/businesses', [BusinessController::class, 'index'])->name('businesses.index');
Route::get('/businesses/{slug}', [BusinessController::class, 'show'])->name('businesses.show');
Route::get('/business-media/images/{image}', [BusinessMediaController::class, 'image'])->name('business-media.images');
Route::get('/business-media/videos/{video}', [BusinessMediaController::class, 'video'])->name('business-media.videos');
Route::get('/pricing', [PublicPageController::class, 'pricing'])->name('pricing');
Route::get('/contact', [PublicPageController::class, 'contact'])->name('contact');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard/data', [DashboardController::class, 'data'])->name('dashboard.data');
    Route::get('/dashboard/api/members', [MemberManagementController::class, 'index'])
        ->middleware('dashboard.module:users')->name('dashboard.members.index');
    Route::put('/dashboard/api/members/{user}', [MemberManagementController::class, 'update'])
        ->middleware('dashboard.module:users,write')->name('dashboard.members.update');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::put('/dashboard/businesses/{business}/profile', [MemberBusinessProfileController::class, 'update'])
        ->middleware('dashboard.module:business,write')->name('dashboard.businesses.profile.update');
    Route::patch('/dashboard/businesses/{business}/publication', [MemberBusinessProfileController::class, 'publish'])
        ->middleware('dashboard.module:business,write')->name('dashboard.businesses.publication');
    Route::post('/dashboard/businesses/{business}/images', [MemberBusinessProfileController::class, 'uploadImages'])
        ->middleware('dashboard.module:business,write')->name('dashboard.businesses.images.store');
    Route::put('/dashboard/email-preferences', [MemberCommunicationPreferenceController::class, 'update'])
        ->middleware('dashboard.module:settings,write')->name('dashboard.email-preferences');
    Route::put('/dashboard/account/profile', [MemberAccountController::class, 'updateProfile'])
        ->middleware('dashboard.module:settings,write')->name('dashboard.account.profile.update');
    Route::put('/dashboard/account/password', [MemberAccountController::class, 'updatePassword'])
        ->middleware('dashboard.module:settings,write')->name('dashboard.account.password.update');
    Route::post('/dashboard/events', [MemberEventController::class, 'storeEvent'])
        ->middleware('dashboard.module:events,write')->name('dashboard.events.store');
    Route::put('/dashboard/events/{event}', [MemberEventController::class, 'updateEvent'])
        ->middleware('dashboard.module:events,write')->name('dashboard.events.update');
    Route::post('/dashboard/events/{event}/registrations', [MemberEventController::class, 'store'])
        ->middleware('dashboard.module:events,write')->name('dashboard.events.register');
    Route::delete('/dashboard/event-registrations/{registration}', [MemberEventController::class, 'cancel'])
        ->middleware('dashboard.module:events,write')->name('dashboard.events.cancel');
    Route::post('/dashboard/activity/reports/{report}/viewed', [MemberActivityController::class, 'reportViewed'])
        ->middleware('dashboard.module:reports')->name('dashboard.activity.reports.viewed');
    Route::post('/dashboard/activity/resources/{resource}/viewed', [MemberActivityController::class, 'resourceViewed'])
        ->middleware('dashboard.module:resources')->name('dashboard.activity.resources.viewed');
    Route::post('/dashboard/resources/{resource}/acknowledge', [MemberResourceController::class, 'acknowledge'])
        ->middleware('dashboard.module:resources,write')->name('dashboard.resources.acknowledge');
    Route::get('/dashboard/files/resources/{resource}', [MemberFileController::class, 'resource'])
        ->middleware('dashboard.module:resources')->name('dashboard.files.resources');
    Route::get('/dashboard/files/reports/{report}', [MemberFileController::class, 'report'])
        ->middleware('dashboard.module:reports')->name('dashboard.files.reports');
    Route::post('/dashboard/reports/submissions', [MemberReportController::class, 'store'])
        ->middleware('dashboard.module:reports,write')->name('dashboard.reports.submissions.store');
    Route::get('/dashboard/reports/submissions/{report}/summary', [MemberReportController::class, 'summary'])
        ->middleware('dashboard.module:reports')->name('dashboard.reports.submissions.summary');
    Route::get('/dashboard/reports/submissions/{report}/download', [MemberReportController::class, 'download'])
        ->middleware('dashboard.module:reports')->name('dashboard.reports.submissions.download');
    Route::get('/dashboard/{path?}', DashboardController::class)
        ->where('path', '.*')
        ->middleware('dashboard.module:path')
        ->name('dashboard.spa');
});

Route::middleware(['auth', 'can:system-admin', 'system.module'])
    ->prefix('super-admin')
    ->name('super-admin.')
    ->group(function (): void {
        Route::get('/api/overview', [SuperAdminController::class, 'overview'])->name('overview');
        Route::get('/api/settings', [SuperAdminPlatformSettingsController::class, 'show'])->name('settings.show');
        Route::put('/api/settings/membership-pricing', [SuperAdminPlatformSettingsController::class, 'updateMembershipPricing'])
            ->name('settings.membership-pricing.update');
        Route::get('/api/engagement', SuperAdminEngagementController::class)->name('engagement');
        Route::get('/api/businesses', [SuperAdminController::class, 'businesses'])->name('businesses.index');
        Route::post('/api/businesses', [SuperAdminBusinessController::class, 'store'])->name('businesses.store');
        Route::get('/api/businesses/{business}', [SuperAdminController::class, 'businessDetails'])->name('businesses.show');
        Route::get('/businesses/{business}/preview', [BusinessController::class, 'preview'])->name('businesses.preview');
        Route::put('/api/businesses/{business}', [SuperAdminBusinessController::class, 'update'])->name('businesses.update');
        Route::post('/api/businesses/{business}/images', [SuperAdminBusinessController::class, 'uploadImages'])->name('businesses.images.store');
        Route::put('/api/businesses/{business}/images/{image}', [SuperAdminBusinessController::class, 'updateImage'])->name('businesses.images.update');
        Route::delete('/api/businesses/{business}/images/{image}', [SuperAdminBusinessController::class, 'deleteImage'])->name('businesses.images.destroy');
        Route::post('/api/businesses/{business}/video', [SuperAdminBusinessController::class, 'saveVideo'])->name('businesses.video.update');
        Route::delete('/api/businesses/{business}/video', [SuperAdminBusinessController::class, 'deleteVideo'])->name('businesses.video.destroy');
        Route::patch('/api/businesses/{business}/publication', [SuperAdminController::class, 'updateBusinessPublication'])
            ->name('businesses.publication');
        Route::get('/api/memberships/{membership}', [SuperAdminController::class, 'membershipDetails'])->name('memberships.show');
        Route::patch('/api/memberships/{membership}', [SuperAdminMembershipController::class, 'update'])->name('memberships.update');
        Route::post('/api/memberships/{membership}/complimentary-extension', [SuperAdminMembershipController::class, 'extend'])->name('memberships.extend');
        Route::post('/api/resources', [SuperAdminContentController::class, 'storeResource'])->name('resources.store');
        Route::put('/api/resources/{resource}', [SuperAdminContentController::class, 'updateResource'])->name('resources.update');
        Route::delete('/api/resources/{resource}', [SuperAdminContentController::class, 'archiveResource'])->name('resources.archive');
        Route::get('/api/resources/{resource}/download', [SuperAdminContentController::class, 'downloadResource'])->name('resources.download');
        Route::get('/api/resources/{resource}/versions/{version}/download', [SuperAdminContentController::class, 'downloadResourceVersion'])->name('resources.versions.download');
        Route::post('/api/reports', [SuperAdminContentController::class, 'storeReport'])->name('reports.store');
        Route::patch('/api/reports/{report}/review', [SuperAdminContentController::class, 'reviewReport'])->name('reports.review');
        Route::post('/api/reports/{report}/ai-summary/regenerate', [SuperAdminContentController::class, 'regenerateReportAiSummary'])->name('reports.ai-summary.regenerate');
        Route::put('/api/reports/{report}', [SuperAdminContentController::class, 'updateReport'])->name('reports.update');
        Route::delete('/api/reports/{report}', [SuperAdminContentController::class, 'archiveReport'])->name('reports.archive');
        Route::get('/api/reports/{report}/download', [SuperAdminContentController::class, 'downloadReport'])->name('reports.download');
        Route::post('/api/events', [SuperAdminEventController::class, 'store'])->name('events.store');
        Route::put('/api/events/{event}', [SuperAdminEventController::class, 'update'])->name('events.update');
        Route::patch('/api/events/{event}/status', [SuperAdminEventController::class, 'setStatus'])->name('events.status');
        Route::get('/api/events/{event}/registrations', [SuperAdminEventController::class, 'registrations'])->name('events.registrations');
        Route::patch('/api/event-registrations/{registration}', [SuperAdminEventController::class, 'updateRegistration'])->name('event-registrations.update');
        Route::post('/api/marketing', [SuperAdminMarketingController::class, 'store'])->name('marketing.store');
        Route::put('/api/marketing/{activity}', [SuperAdminMarketingController::class, 'update'])->name('marketing.update');
        Route::post('/api/marketing/{activity}/duplicate', [SuperAdminMarketingController::class, 'duplicate'])->name('marketing.duplicate');
        Route::patch('/api/marketing/{activity}/status', [SuperAdminMarketingController::class, 'setStatus'])->name('marketing.status');
        Route::delete('/api/marketing/{activity}', [SuperAdminMarketingController::class, 'archive'])->name('marketing.archive');
        Route::post('/api/services', [SuperAdminOrderServiceController::class, 'storeService'])->name('services.store');
        Route::put('/api/services/{service}', [SuperAdminOrderServiceController::class, 'updateService'])->name('services.update');
        Route::patch('/api/orders/{order}/fulfillment', [SuperAdminOrderServiceController::class, 'updateOrder'])->name('orders.fulfillment');
        Route::get('/api/announcements', [SuperAdminCommunicationController::class, 'index'])->name('announcements.index');
        Route::post('/api/announcements/preview', [SuperAdminCommunicationController::class, 'preview'])->name('announcements.preview');
        Route::post('/api/announcements', [SuperAdminCommunicationController::class, 'store'])->name('announcements.store');
        Route::put('/api/announcements/{announcement}', [SuperAdminCommunicationController::class, 'update'])->name('announcements.update');
        Route::post('/api/announcements/{announcement}/send', [SuperAdminCommunicationController::class, 'send'])->name('announcements.send');
        Route::post('/api/exports/{type}', [SuperAdminExportController::class, 'export'])->name('exports');
        Route::get('/api/accounts', [SuperAdminController::class, 'accounts'])->name('accounts.index');
        Route::post('/api/accounts', [SuperAdminController::class, 'storeAccount'])->name('accounts.store');
        Route::put('/api/accounts/{user}', [SuperAdminController::class, 'updateAccount'])->name('accounts.update');
        Route::get('/api/workspace/{section}', [SuperAdminController::class, 'workspaceData'])
            ->whereIn('section', ['memberships', 'content', 'reports', 'events', 'marketing', 'orders', 'services', 'billing', 'analytics', 'audit'])
            ->name('workspace');
        Route::get('/{path?}', SuperAdminController::class)
            ->where('path', '(?!api(?:/|$)).*')
            ->name('spa');
    });
