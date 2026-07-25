<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ActivityTypeController;
use App\Http\Controllers\AddressTypeController;
use App\Http\Controllers\AgentProfileController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CampaignAssignmentController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoModeController;
use App\Http\Controllers\EntityActionCodeController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\EntityStatusController;
use App\Http\Controllers\EntityTemplateController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UsersController::class, 'index'])->name('users.index');
        Route::get('/users/export', [UsersController::class, 'export'])->middleware('permission:users.export')->name('users.export');
    });
    Route::middleware('permission:users.create')->group(function () {
        Route::get('/users/create', [UsersController::class, 'create'])->name('users.create');
        Route::post('/users', [UsersController::class, 'store'])->name('users.store');
    });
    Route::middleware('permission:users.update')->group(function () {
        Route::get('/users/{user}/edit', [UsersController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update');
    });

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/roles', [RolesController::class, 'index'])->name('roles.index');
        Route::get('/roles/{role}/edit', [RolesController::class, 'edit'])->name('roles.edit');
        Route::put('/roles/{role}', [RolesController::class, 'update'])->name('roles.update');
    });

    Route::middleware('permission:agent_profiles.view')->group(function () {
        Route::get('/agent-profiles', [AgentProfileController::class, 'index'])->name('agent-profiles.index');
        Route::get('/agent-profiles/export', [AgentProfileController::class, 'export'])->middleware('permission:agent_profiles.export')->name('agent-profiles.export');
    });
    Route::middleware('permission:agent_profiles.create')->group(function () {
        Route::get('/agent-profiles/create', [AgentProfileController::class, 'create'])->name('agent-profiles.create');
        Route::post('/agent-profiles', [AgentProfileController::class, 'store'])->name('agent-profiles.store');
    });
    Route::middleware('permission:agent_profiles.view')->group(function () {
        Route::get('/agent-profiles/{agentProfile}', [AgentProfileController::class, 'show'])->name('agent-profiles.show');
    });
    Route::middleware('permission:agent_profiles.update')->group(function () {
        Route::get('/agent-profiles/{agentProfile}/edit', [AgentProfileController::class, 'edit'])->name('agent-profiles.edit');
        Route::put('/agent-profiles/{agentProfile}', [AgentProfileController::class, 'update'])->name('agent-profiles.update');
    });
    Route::delete('/agent-profiles/{agentProfile}', [AgentProfileController::class, 'destroy'])
        ->middleware('permission:agent_profiles.delete')
        ->name('agent-profiles.destroy');

    Route::middleware('permission:campaigns.view')->group(function () {
        Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campaigns/export', [CampaignController::class, 'export'])->middleware('permission:campaigns.export')->name('campaigns.export');
    });
    Route::middleware('permission:campaigns.create')->group(function () {
        Route::get('/campaigns/create', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    });
    Route::middleware('permission:campaigns.view')->group(function () {
        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
    });
    Route::middleware('permission:campaigns.update')->group(function () {
        Route::get('/campaigns/{campaign}/edit', [CampaignController::class, 'edit'])->name('campaigns.edit');
        Route::put('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
    });
    Route::post('/campaigns/{campaign}/archive', [CampaignController::class, 'archive'])
        ->middleware('permission:campaigns.archive')
        ->name('campaigns.archive');
    Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])
        ->middleware('permission:campaigns.delete')
        ->name('campaigns.destroy');

    Route::middleware('permission:campaign_assignments.manage')->group(function () {
        Route::post('/campaigns/{campaign}/assignments', [CampaignAssignmentController::class, 'store'])->name('campaigns.assignments.store');
        Route::delete('/campaigns/{campaign}/assignments/{agentProfile}', [CampaignAssignmentController::class, 'destroy'])->name('campaigns.assignments.destroy');
    });

    Route::middleware('permission:activity_types.view')->group(function () {
        Route::get('/activity-types', [ActivityTypeController::class, 'index'])->name('activity-types.index');
        Route::get('/activity-types/export', [ActivityTypeController::class, 'export'])->middleware('permission:activity_types.export')->name('activity-types.export');
    });

    Route::middleware('permission:contact_types.view')->group(function () {
        Route::get('/contact-types', [ContactTypeController::class, 'index'])->name('contact-types.index');
        Route::get('/contact-types/export', [ContactTypeController::class, 'export'])->middleware('permission:contact_types.export')->name('contact-types.export');
    });

    Route::middleware('permission:address_types.view')->group(function () {
        Route::get('/address-types', [AddressTypeController::class, 'index'])->name('address-types.index');
        Route::get('/address-types/export', [AddressTypeController::class, 'export'])->middleware('permission:address_types.export')->name('address-types.export');
    });
    Route::middleware('permission:address_types.manage')->group(function () {
        Route::get('/address-types/create', [AddressTypeController::class, 'create'])->name('address-types.create');
        Route::post('/address-types', [AddressTypeController::class, 'store'])->name('address-types.store');
        Route::get('/address-types/{addressType}/edit', [AddressTypeController::class, 'edit'])->name('address-types.edit');
        Route::put('/address-types/{addressType}', [AddressTypeController::class, 'update'])->name('address-types.update');
        Route::delete('/address-types/{addressType}', [AddressTypeController::class, 'destroy'])->name('address-types.destroy');
    });

    Route::middleware('permission:entities.view')->group(function () {
        Route::get('/entities', [EntityController::class, 'index'])->name('entities.index');
        Route::get('/entities/export', [EntityController::class, 'export'])->middleware('permission:entities.export')->name('entities.export');
    });
    Route::middleware('permission:entities.create')->group(function () {
        Route::get('/entities/create', [EntityController::class, 'create'])->name('entities.create');
        Route::post('/entities', [EntityController::class, 'store'])->name('entities.store');
    });
    Route::middleware('permission:entities.view')->group(function () {
        Route::get('/entities/{entity}', [EntityController::class, 'show'])->name('entities.show');
    });
    Route::middleware('permission:entities.update')->group(function () {
        Route::get('/entities/{entity}/edit', [EntityController::class, 'edit'])->name('entities.edit');
        Route::put('/entities/{entity}', [EntityController::class, 'update'])->name('entities.update');
        Route::post('/entities/{entity}/statuses', [EntityStatusController::class, 'store'])->name('entities.statuses.store');
        Route::post('/entities/{entity}/statuses/copy', [EntityStatusController::class, 'copy'])->name('entities.statuses.copy');
        Route::put('/entities/{entity}/statuses/{entityStatus}', [EntityStatusController::class, 'update'])->name('entities.statuses.update');
        Route::delete('/entities/{entity}/statuses/{entityStatus}', [EntityStatusController::class, 'destroy'])->name('entities.statuses.destroy');
        Route::post('/entities/{entity}/action-codes', [EntityActionCodeController::class, 'store'])->name('entities.action-codes.store');
        Route::post('/entities/{entity}/action-codes/copy', [EntityActionCodeController::class, 'copy'])->name('entities.action-codes.copy');
        Route::put('/entities/{entity}/action-codes/{entityActionCode}', [EntityActionCodeController::class, 'update'])->name('entities.action-codes.update');
        Route::delete('/entities/{entity}/action-codes/{entityActionCode}', [EntityActionCodeController::class, 'destroy'])->name('entities.action-codes.destroy');
        Route::post('/entities/{entity}/templates', [EntityTemplateController::class, 'store'])->name('entities.templates.store');
        Route::put('/entities/{entity}/templates/{entityTemplate}', [EntityTemplateController::class, 'update'])->name('entities.templates.update');
        Route::delete('/entities/{entity}/templates/{entityTemplate}', [EntityTemplateController::class, 'destroy'])->name('entities.templates.destroy');
    });
    Route::delete('/entities/{entity}', [EntityController::class, 'destroy'])
        ->middleware('permission:entities.delete')
        ->name('entities.destroy');

    Route::middleware('permission:accounts.view')->group(function () {
        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/export', [AccountController::class, 'export'])->middleware('permission:accounts.export')->name('accounts.export');
    });
    Route::middleware('permission:accounts.create')->group(function () {
        Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    });
    Route::middleware('permission:accounts.update')->group(function () {
        Route::get('/accounts/bulk/options', [AccountController::class, 'bulkOptions'])->name('accounts.bulk.options');
        Route::post('/accounts/bulk/campaign', [AccountController::class, 'bulkAssignCampaign'])->name('accounts.bulk.campaign');
        Route::post('/accounts/bulk/agent', [AccountController::class, 'bulkAssignAgent'])->name('accounts.bulk.agent');
        Route::post('/accounts/bulk/status', [AccountController::class, 'bulkAssignStatus'])->name('accounts.bulk.status');
        Route::post('/accounts/bulk/activity', [AccountController::class, 'bulkStoreActivity'])->name('accounts.bulk.activity');
    });
    Route::middleware('permission:accounts.view')->group(function () {
        Route::get('/accounts/{account}', [AccountController::class, 'show'])->name('accounts.show');
        Route::get(
            '/accounts/{account}/activities/{accountActivity}/files/{file}/download',
            [AccountController::class, 'downloadActivityFile'],
        )->name('accounts.activities.files.download');
    });
    Route::middleware('permission:accounts.update')->group(function () {
        Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
        Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::put('/accounts/{account}/custom-fields', [AccountController::class, 'updateCustomFields'])->name('accounts.custom-fields.update');
        Route::put('/accounts/{account}/status', [AccountController::class, 'updateStatus'])->name('accounts.status.update');
        Route::post('/accounts/{account}/contact-infos', [AccountController::class, 'storeContactInfo'])->name('accounts.contact-infos.store');
        Route::delete('/accounts/{account}/contact-infos/{contactInfo}', [AccountController::class, 'destroyContactInfo'])->name('accounts.contact-infos.destroy');
        Route::post('/accounts/{account}/addresses', [AccountController::class, 'storeAddress'])->name('accounts.addresses.store');
        Route::delete('/accounts/{account}/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('accounts.addresses.destroy');
        Route::post('/accounts/{account}/activities', [AccountController::class, 'storeActivity'])->name('accounts.activities.store');
        Route::delete('/accounts/{account}/activities/{accountActivity}', [AccountController::class, 'destroyActivity'])->name('accounts.activities.destroy');
    });
    Route::middleware('permission:accounts.delete')->group(function () {
        Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    });

    Route::middleware('permission:reports.view')->get('/reports', [ReportController::class, 'index'])->name('reports.index');

    Route::middleware('permission:imports.run')->group(function () {
        Route::get('/imports', [ImportController::class, 'index'])->name('imports.index');
        Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    });

    Route::middleware('permission:audit_logs.view')->group(function () {
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->middleware('permission:audit_logs.export')->name('audit-logs.export');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    });

    Route::middleware('permission:demo_mode.manage')->group(function () {
        Route::get('/demo-mode', [DemoModeController::class, 'index'])->name('demo-mode.index');
        Route::post('/demo-mode/clear', [DemoModeController::class, 'clear'])->name('demo-mode.clear');
        Route::post('/demo-mode/create-demo', [DemoModeController::class, 'createDemo'])->name('demo-mode.create-demo');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/password', [ProfileController::class, 'editPassword'])->name('profile.password.edit');
});

require __DIR__.'/auth.php';
