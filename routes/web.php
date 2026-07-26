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
use App\Http\Controllers\EntityKnowledgeGroupController;
use App\Http\Controllers\EntityKnowledgeItemController;
use App\Http\Controllers\EntityStatusController;
use App\Http\Controllers\EntityTemplateController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SmsBatchController;
use App\Http\Controllers\SmsCallbackEventController;
use App\Http\Controllers\SmsConfigController;
use App\Http\Controllers\SmsDashboardController;
use App\Http\Controllers\SmsReceivedMessageController;
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
        Route::get(
            '/entities/{entity}/knowledge-groups/{entityKnowledgeGroup}',
            [EntityKnowledgeGroupController::class, 'show'],
        )->name('entities.knowledge-groups.show');
        Route::get(
            '/entities/{entity}/knowledge/{entityKnowledgeItem}/download',
            [EntityKnowledgeItemController::class, 'download'],
        )->name('entities.knowledge.download');
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
        Route::post('/entities/{entity}/knowledge-groups', [EntityKnowledgeGroupController::class, 'store'])->name('entities.knowledge-groups.store');
        Route::put('/entities/{entity}/knowledge-groups/{entityKnowledgeGroup}', [EntityKnowledgeGroupController::class, 'update'])->name('entities.knowledge-groups.update');
        Route::delete('/entities/{entity}/knowledge-groups/{entityKnowledgeGroup}', [EntityKnowledgeGroupController::class, 'destroy'])->name('entities.knowledge-groups.destroy');
        Route::post('/entities/{entity}/knowledge', [EntityKnowledgeItemController::class, 'store'])->name('entities.knowledge.store');
        Route::put('/entities/{entity}/knowledge/{entityKnowledgeItem}', [EntityKnowledgeItemController::class, 'update'])->name('entities.knowledge.update');
        Route::delete('/entities/{entity}/knowledge/{entityKnowledgeItem}', [EntityKnowledgeItemController::class, 'destroy'])->name('entities.knowledge.destroy');
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
        Route::post('/accounts/bulk/assignment', [AccountController::class, 'bulkAssignAssignment'])->name('accounts.bulk.assignment');
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

    Route::middleware('permission:sms.view')->group(function () {
        Route::get('/sms', [SmsDashboardController::class, 'index'])->name('sms.dashboard');
        Route::get('/sms/poll', [SmsDashboardController::class, 'poll'])->name('sms.poll');
        Route::get('/sms/batches', [SmsBatchController::class, 'index'])->name('sms.batches.index');
        Route::get('/sms/batches/export', [SmsBatchController::class, 'export'])
            ->middleware('permission:sms.export')
            ->name('sms.batches.export');
        Route::get('/sms/batches/{smsBatch}', [SmsBatchController::class, 'show'])->name('sms.batches.show');
        Route::get('/sms/received', [SmsReceivedMessageController::class, 'index'])->name('sms.received.index');
        Route::get('/sms/received/export', [SmsReceivedMessageController::class, 'export'])
            ->middleware('permission:sms.export')
            ->name('sms.received.export');
        Route::get('/sms/received/account-search', [SmsReceivedMessageController::class, 'searchAccounts'])->name('sms.received.account-search');
        Route::post('/sms/received/{smsReceivedMessage}/associate', [SmsReceivedMessageController::class, 'associate'])->name('sms.received.associate');
        Route::post('/sms/received/{smsReceivedMessage}/reply', [SmsReceivedMessageController::class, 'reply'])->name('sms.received.reply');
        Route::post('/sms/received/{smsReceivedMessage}/ignore', [SmsReceivedMessageController::class, 'ignore'])->name('sms.received.ignore');
        Route::delete('/sms/received/{smsReceivedMessage}', [SmsReceivedMessageController::class, 'destroy'])->name('sms.received.destroy');
        Route::get('/sms/callbacks', [SmsCallbackEventController::class, 'index'])->name('sms.callbacks.index');
        Route::get('/sms/callbacks/export', [SmsCallbackEventController::class, 'export'])
            ->middleware('permission:sms.export')
            ->name('sms.callbacks.export');
    });
    Route::middleware('permission:sms.manage')->group(function () {
        Route::get('/sms/config', [SmsConfigController::class, 'index'])->name('sms.config');
        Route::put('/sms/config', [SmsConfigController::class, 'update'])->name('sms.config.update');
        Route::post('/sms/config/test-ports', [SmsConfigController::class, 'testPorts'])->name('sms.config.test-ports');
        Route::post('/sms/config/sync', [SmsConfigController::class, 'syncConfig'])->name('sms.config.sync');
        Route::post('/sms/device-groups', [SmsConfigController::class, 'storeDeviceGroup'])->name('sms.device-groups.store');
        Route::put('/sms/device-groups/{smsDeviceGroup}', [SmsConfigController::class, 'updateDeviceGroup'])->name('sms.device-groups.update');
        Route::delete('/sms/device-groups/{smsDeviceGroup}', [SmsConfigController::class, 'destroyDeviceGroup'])->name('sms.device-groups.destroy');
        Route::post('/sms/devices', [SmsConfigController::class, 'storeDevice'])->name('sms.devices.store');
        Route::put('/sms/devices/{smsDevice}', [SmsConfigController::class, 'updateDevice'])->name('sms.devices.update');
        Route::delete('/sms/devices/{smsDevice}', [SmsConfigController::class, 'destroyDevice'])->name('sms.devices.destroy');
        Route::post('/sms/service/start', [SmsDashboardController::class, 'startService'])->name('sms.service.start');
        Route::post('/sms/service/stop', [SmsDashboardController::class, 'stopService'])->name('sms.service.stop');
        Route::post('/sms/service/restart', [SmsDashboardController::class, 'restartService'])->name('sms.service.restart');
        Route::post('/sms/health/refresh', [SmsDashboardController::class, 'refreshHealth'])->name('sms.health.refresh');
        Route::post('/sms/devices/refresh', [SmsDashboardController::class, 'refreshDevices'])->name('sms.devices.refresh');
        Route::post('/sms/dispatch', [SmsDashboardController::class, 'dispatchTick'])->name('sms.dispatch');
        Route::post('/sms/probe', [SmsDashboardController::class, 'probe'])->name('sms.probe');
        Route::post('/sms/auto-recovery', [SmsDashboardController::class, 'updateAutoRecovery'])->name('sms.auto-recovery');
        Route::post('/sms/runtime-devices/restart', [SmsDashboardController::class, 'restartDevice'])->name('sms.runtime-devices.restart');
        Route::post('/sms/runtime-devices/start', [SmsDashboardController::class, 'startDevice'])->name('sms.runtime-devices.start');
        Route::post('/sms/runtime-devices/delete', [SmsDashboardController::class, 'deleteDeviceRuntime'])->name('sms.runtime-devices.delete');
        Route::put('/sms/batches/{smsBatch}', [SmsBatchController::class, 'update'])->name('sms.batches.update');
        Route::delete('/sms/batches/{smsBatch}', [SmsBatchController::class, 'destroy'])->name('sms.batches.destroy');
        Route::put('/sms/batches/{smsBatch}/items/{item}', [SmsBatchController::class, 'updateItem'])->name('sms.batches.items.update');
        Route::post('/sms/batches/{smsBatch}/pause', [SmsBatchController::class, 'pause'])->name('sms.batches.pause');
        Route::post('/sms/batches/{smsBatch}/resume', [SmsBatchController::class, 'resume'])->name('sms.batches.resume');
        Route::post('/sms/batches/{smsBatch}/priority', [SmsBatchController::class, 'bumpPriority'])->name('sms.batches.priority');
    });
    Route::middleware('permission:sms.queue.cancel')->group(function () {
        Route::post('/sms/batches/{smsBatch}/cancel', [SmsBatchController::class, 'cancel'])->name('sms.batches.cancel');
        Route::post('/sms/batches/{smsBatch}/items/{item}/cancel', [SmsBatchController::class, 'cancelItem'])->name('sms.batches.items.cancel');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/password', [ProfileController::class, 'editPassword'])->name('profile.password.edit');
});

require __DIR__.'/auth.php';
