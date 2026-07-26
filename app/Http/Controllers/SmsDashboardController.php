<?php

namespace App\Http\Controllers;

use App\Enums\SmsBatchStatus;
use App\Enums\SmsQueueItemStatus;
use App\Enums\SmsReceivedAssociationStatus;
use App\Models\SmsBatch;
use App\Models\SmsCallbackEvent;
use App\Models\SmsDevice;
use App\Models\SmsDeviceGroup;
use App\Models\SmsQueueItem;
use App\Models\SmsReceivedMessage;
use App\Models\SmsSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Sms\SmsDeviceSyncService;
use App\Services\Sms\SmsDispatchService;
use App\Services\Sms\SmsQueueService;
use App\Services\Sms\SmsReceivedService;
use App\Services\Sms\SmsServiceClient;
use App\Services\Sms\SmsServiceProcessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SmsDashboardController extends Controller
{
    public function index(Request $request, SmsReceivedService $receivedService): Response
    {
        /** @var User $user */
        $user = $request->user();
        $settings = SmsSetting::current();
        $q = trim((string) $request->query('q', ''));

        $service = [
            'running' => null,
            'health' => ['ok' => null, 'error' => null],
            'error' => null,
        ];

        return Inertia::render('Sms/Dashboard', [
            'filters' => ['q' => $q !== '' ? $q : null],
            'service' => $this->servicePayload($settings, $service),
            'health_poll_seconds' => $settings->resolvedDashboardHealthPollSeconds(),
            'list_poll_seconds' => $settings->resolvedDashboardListPollSeconds(),
            'alert' => [
                'code' => $settings->last_alert,
                'message' => $settings->last_alert_message,
                'at' => $settings->last_alert_at?->toIso8601String(),
            ],
            'auto_device_recovery' => (bool) $settings->auto_device_recovery,
            'devices_available' => null,
            ...$this->deviceDashboardPayload(),
            'recent_batches' => $this->batches($q),
            'received_sms' => $this->receivedSms($receivedService),
            'reply_devices' => $this->replyDeviceOptions(),
            'recovery_events' => $this->recoveryEvents(),
            'can' => [
                'manage' => $user->hasPermission('sms.manage'),
                'cancel' => $user->hasPermission('sms.queue.cancel'),
                'associate' => $user->hasPermission('sms.view'),
                'reply' => $user->hasPermission('sms.view'),
            ],
        ]);
    }

    public function startService(SmsServiceProcessManager $processManager, AuditLogger $auditLogger): RedirectResponse
    {
        $result = $processManager->ensureStarted(forceSpawn: true);
        $auditLogger->log('sms.service.start_attempted', null, null, $result);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['message']);
    }

    public function stopService(SmsServiceProcessManager $processManager, AuditLogger $auditLogger): RedirectResponse
    {
        $result = $processManager->stop();
        $auditLogger->log('sms.service.stop_attempted', null, null, $result);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['message']);
    }

    public function restartService(SmsServiceProcessManager $processManager, AuditLogger $auditLogger): RedirectResponse
    {
        $result = $processManager->restartProcess();
        $auditLogger->log('sms.service.restart_attempted', null, null, $result);

        if ($result['error']) {
            return back()->with('error', $result['error']);
        }

        return back()->with('success', $result['message']);
    }

    public function refreshHealth(
        SmsServiceClient $client,
        SmsDeviceSyncService $deviceSync,
        SmsServiceProcessManager $processManager,
    ): RedirectResponse {
        $settings = SmsSetting::current();
        $client->refreshSettings($settings);
        $status = $processManager->status($settings);

        if (! $status['running']) {
            return back()->with('error', $status['error'] ?? 'SMS service is not reachable.');
        }

        try {
            $deviceSync->syncFromService($client);
        } catch (Throwable $e) {
            return back()->with('error', 'Health refresh failed: '.$e->getMessage());
        }

        return back()->with('success', 'Device health refreshed.');
    }

    public function refreshDevices(
        SmsServiceClient $client,
        SmsDeviceSyncService $deviceSync,
        SmsServiceProcessManager $processManager,
    ): RedirectResponse {
        $settings = SmsSetting::current();
        $client->refreshSettings($settings);
        $status = $processManager->status($settings);

        if (! $status['running']) {
            return back()->with('error', $status['error'] ?? 'SMS service is not reachable.');
        }

        try {
            $remote = $deviceSync->syncFromService($client);
        } catch (Throwable $e) {
            return back()->with('error', 'Device refresh failed: '.$e->getMessage());
        }

        return back()->with('success', 'Devices available refreshed ('.count($remote).').');
    }

    public function updateAutoRecovery(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'auto_device_recovery' => ['required', 'boolean'],
        ]);

        $settings = SmsSetting::current();
        $settings->forceFill([
            'auto_device_recovery' => (bool) $data['auto_device_recovery'],
        ])->save();

        $auditLogger->log('sms.auto_recovery.updated', null, null, $data);

        return back()->with('success', 'Automatic device recovery '.($settings->auto_device_recovery ? 'enabled' : 'disabled').'.');
    }

    public function cancelBatch(SmsBatch $smsBatch, SmsQueueService $queue, AuditLogger $auditLogger): RedirectResponse
    {
        $result = $queue->cancelBatch($smsBatch);
        $batch = $result['batch'];
        $auditLogger->log('sms.batch.cancelled', $batch, null, [
            'cancelled' => $batch->cancelled,
            'activities_deleted' => $result['activities_deleted'],
        ]);

        return back()->with(
            'success',
            "Batch {$batch->id}: cancelled {$batch->cancelled} queued item(s); deleted {$result['activities_deleted']} activity(ies).",
        );
    }

    public function pauseBatch(SmsBatch $smsBatch, SmsQueueService $queue, AuditLogger $auditLogger): RedirectResponse
    {
        $batch = $queue->pauseBatch($smsBatch);
        $auditLogger->log('sms.batch.paused', $batch, null);

        return back()->with('success', "Batch {$batch->id} paused.");
    }

    public function resumeBatch(SmsBatch $smsBatch, SmsQueueService $queue, AuditLogger $auditLogger): RedirectResponse
    {
        $batch = $queue->resumeBatch($smsBatch);
        $auditLogger->log('sms.batch.resumed', $batch, null);

        return back()->with('success', "Batch {$batch->id} resumed.");
    }

    public function bumpBatchPriority(Request $request, SmsBatch $smsBatch, SmsQueueService $queue, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $batch = $queue->bumpPriority($smsBatch, $data['direction']);
        $auditLogger->log('sms.batch.priority_updated', $batch, null, [
            'priority' => $batch->priority,
            'direction' => $data['direction'],
        ]);

        return back()->with('success', "Batch {$batch->id} priority set to {$batch->priority}.");
    }

    public function probe(
        Request $request,
        SmsServiceClient $client,
        SmsDeviceSyncService $deviceSync,
    ): JsonResponse {
        $data = $request->validate([
            'action' => ['required', Rule::in(['ping', 'health', 'info', 'devices', 'restart', 'start', 'delete'])],
            'device_id' => [
                Rule::requiredIf(fn () => in_array($request->input('action'), ['restart', 'start', 'delete'], true)),
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $settings = SmsSetting::current();
        $client->refreshSettings($settings);

        try {
            $payload = match ($data['action']) {
                'ping' => ['ok' => $client->ping()],
                'health' => $client->health(),
                'info' => $client->info(),
                'devices' => ['devices' => $client->listDevices()],
                'restart' => $client->restartDevice((string) $data['device_id']),
                'start' => $client->startDevice((string) $data['device_id']),
                'delete' => $client->deleteDevice((string) $data['device_id']),
            };

            if (in_array($data['action'], ['health', 'devices'], true)) {
                try {
                    $deviceSync->syncFromService($client);
                } catch (Throwable) {
                    // Probe result still returned; sync best-effort.
                }
            }

            return response()->json([
                'ok' => true,
                'action' => $data['action'],
                'data' => $payload,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'action' => $data['action'],
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function restartDevice(Request $request, SmsServiceClient $client, AuditLogger $auditLogger): RedirectResponse
    {
        return $this->deviceAction($request, $client, $auditLogger, 'restart');
    }

    public function startDevice(Request $request, SmsServiceClient $client, AuditLogger $auditLogger): RedirectResponse
    {
        return $this->deviceAction($request, $client, $auditLogger, 'start');
    }

    public function deleteDeviceRuntime(Request $request, SmsServiceClient $client, AuditLogger $auditLogger): RedirectResponse
    {
        return $this->deviceAction($request, $client, $auditLogger, 'delete');
    }

    public function poll(
        Request $request,
        SmsServiceClient $client,
        SmsServiceProcessManager $processManager,
        SmsDeviceSyncService $deviceSync,
        SmsReceivedService $receivedService,
    ): JsonResponse {
        $request->session()->save();

        $settings = SmsSetting::current();
        $client->refreshSettings($settings);
        $checkService = $request->boolean('check_service');
        $servicePayload = null;
        $devicesAvailable = null;

        if ($checkService) {
            $service = $processManager->status($settings);
            $settings->refresh();
            $servicePayload = $this->servicePayload($settings, $service);

            if ($service['running']) {
                $remote = $deviceSync->syncFromService($client);
                $devicesAvailable = $this->countRemoteDevices($remote);
                // Transient listDevices failure (empty payload): fall back to registry so KPI does not blank.
                if ($devicesAvailable === 0 && $remote === []) {
                    $devicesAvailable = SmsDevice::query()
                        ->whereNotNull('runtime_device_id')
                        ->count();
                }
            } else {
                $devicesAvailable = 0;
            }
        }

        $q = trim((string) $request->query('q', ''));

        $payload = [
            'service' => $servicePayload,
            'health_poll_seconds' => $settings->resolvedDashboardHealthPollSeconds(),
            'list_poll_seconds' => $settings->resolvedDashboardListPollSeconds(),
            'alert' => [
                'code' => $settings->last_alert,
                'message' => $settings->last_alert_message,
                'at' => $settings->last_alert_at?->toIso8601String(),
            ],
            'auto_device_recovery' => (bool) $settings->auto_device_recovery,
            ...$this->deviceDashboardPayload(),
            'recent_batches' => $this->batches($q),
            'received_sms' => $this->receivedSms($receivedService),
            'reply_devices' => $this->replyDeviceOptions(),
            'recovery_events' => $this->recoveryEvents(),
        ];

        if ($devicesAvailable !== null) {
            $payload['devices_available'] = $devicesAvailable;
        }

        return response()->json($payload);
    }

    public function dispatchTick(SmsDispatchService $dispatch): RedirectResponse
    {
        $result = $dispatch->tick();

        if ($result['alert']) {
            return back()->with('error', $result['message']);
        }

        return back()->with('success', $result['message']);
    }

    protected function deviceAction(
        Request $request,
        SmsServiceClient $client,
        AuditLogger $auditLogger,
        string $action,
    ): RedirectResponse {
        $data = $request->validate([
            'device_id' => ['required', 'string', 'max:255'],
        ]);

        $settings = SmsSetting::current();
        $client->refreshSettings($settings);
        $deviceId = $data['device_id'];

        try {
            match ($action) {
                'restart' => $client->restartDevice($deviceId),
                'start' => $client->startDevice($deviceId),
                'delete' => $client->deleteDevice($deviceId),
            };
        } catch (Throwable $e) {
            return back()->with('error', ucfirst($action)." device failed: {$e->getMessage()}");
        }

        $auditLogger->log('sms.device.'.$action, null, null, ['device_id' => $deviceId]);

        return back()->with('success', ucfirst($action)." issued for {$deviceId}.");
    }

    /**
     * @param  array{running: bool|null, health: array<string, mixed>, error: string|null}  $service
     * @return array<string, mixed>
     */
    protected function servicePayload(SmsSetting $settings, array $service): array
    {
        return [
            'running' => $service['running'],
            'health' => $service['health'],
            'error' => $service['error'],
            'started_at' => $settings->service_started_at?->toIso8601String(),
            'uptime_seconds' => $settings->service_started_at
                ? now()->diffInSeconds($settings->service_started_at)
                : null,
            'sms_sent_since_start' => (int) $settings->sms_sent_since_start,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $remote
     */
    protected function countRemoteDevices(array $remote): int
    {
        $count = 0;
        foreach ($remote as $device) {
            $id = (string) ($device['device_id'] ?? $device['id'] ?? '');
            if ($id !== '') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function batches(string $q): array
    {
        $query = SmsBatch::query()
            ->whereIn('status', [
                SmsBatchStatus::Pending->value,
                SmsBatchStatus::Processing->value,
                SmsBatchStatus::Paused->value,
            ])
            ->orderBy('priority')
            ->orderByDesc('id');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like, $q) {
                $inner->where('id', $q)
                    ->orWhere('message_body', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereHas('items', function ($items) use ($like) {
                        $items->where('recipient', 'like', $like)
                            ->orWhere('message', 'like', $like)
                            ->orWhereHas('account', function ($accountQuery) use ($like) {
                                $accountQuery->where('account_number', 'like', $like)
                                    ->orWhere('account_name', 'like', $like);
                            });
                    });
            });
        }

        return $query->limit(25)->get()->map(fn (SmsBatch $batch) => [
            'id' => $batch->id,
            'source' => $batch->source?->value ?? $batch->source,
            'status' => $batch->status?->value ?? $batch->status,
            'priority' => (int) $batch->priority,
            'total' => $batch->total,
            'queued' => $batch->queued,
            'sending' => $batch->sending,
            'sent' => $batch->sent,
            'failed' => $batch->failed,
            'cancelled' => $batch->cancelled,
            'created_at' => $batch->created_at?->toIso8601String(),
            'message_body' => $batch->message_body
                ? \Illuminate\Support\Str::limit($batch->message_body, 80)
                : null,
        ])->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function receivedSms(SmsReceivedService $receivedService): array
    {
        return SmsReceivedMessage::query()
            ->with(['account:id,account_number,account_name', 'activity:id'])
            ->where('association_status', SmsReceivedAssociationStatus::Unmatched)
            ->whereNull('account_id')
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get()
            ->map(fn (SmsReceivedMessage $m) => $receivedService->mapForUi($m))
            ->all();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    protected function replyDeviceOptions(): array
    {
        return SmsDevice::query()
            ->whereNotNull('runtime_device_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['name', 'runtime_device_id'])
            ->map(fn (SmsDevice $d) => [
                'id' => (string) $d->runtime_device_id,
                'name' => ($d->name ?: $d->runtime_device_id).' ('.$d->runtime_device_id.')',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function recoveryEvents(): array
    {
        return SmsCallbackEvent::query()
            ->where(function ($q) {
                $q->whereIn('response_type', ['RestartDevice', 'DeleteDevice'])
                    ->orWhere('event_type', 'DeviceError');
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get()
            ->map(fn (SmsCallbackEvent $e) => [
                'id' => $e->id,
                'event_type' => $e->event_type,
                'response_type' => $e->response_type,
                'device_id' => $e->device_id,
                'processed_at' => $e->processed_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array{device_groups: list<array<string, mixed>>, devices: list<array<string, mixed>>}
     */
    protected function deviceDashboardPayload(): array
    {
        $groups = $this->deviceGroupPanels();

        return [
            'device_groups' => $groups,
            'devices' => $this->flatDevicesFromGroups($groups),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return list<array<string, mixed>>
     */
    protected function flatDevicesFromGroups(array $groups): array
    {
        $flat = [];
        foreach ($groups as $group) {
            foreach ($group['devices'] ?? [] as $device) {
                $flat[] = $device;
            }
        }

        return $flat;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function deviceGroupPanels(): array
    {
        $devices = SmsDevice::query()
            ->with('group:id,name,enabled,sort_order')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $deviceIds = $devices->pluck('id')->all();
        $sentCounts = [];
        $failedCounts = [];

        if ($deviceIds !== []) {
            $sentCounts = SmsQueueItem::query()
                ->selectRaw('assigned_sms_device_id, COUNT(*) as aggregate')
                ->whereIn('assigned_sms_device_id', $deviceIds)
                ->where('status', SmsQueueItemStatus::Sent)
                ->groupBy('assigned_sms_device_id')
                ->pluck('aggregate', 'assigned_sms_device_id')
                ->all();

            $failedCounts = SmsQueueItem::query()
                ->selectRaw('assigned_sms_device_id, COUNT(*) as aggregate')
                ->whereIn('assigned_sms_device_id', $deviceIds)
                ->where('status', SmsQueueItemStatus::Failed)
                ->groupBy('assigned_sms_device_id')
                ->pluck('aggregate', 'assigned_sms_device_id')
                ->all();
        }

        $sendingByRuntime = SmsQueueItem::query()
            ->with(['account:id,account_number,account_name'])
            ->where('status', SmsQueueItemStatus::Sending)
            ->whereNotNull('runtime_device_id')
            ->orderByDesc('id')
            ->get()
            ->keyBy('runtime_device_id');

        $mappedDevices = $devices->map(function (SmsDevice $device) use ($sentCounts, $failedCounts, $sendingByRuntime) {
            $runtimeId = $device->runtime_device_id;
            $sending = $runtimeId ? ($sendingByRuntime->get($runtimeId) ?? null) : null;

            return [
                'id' => $device->id,
                'sms_device_group_id' => $device->sms_device_group_id,
                'group_name' => $device->group?->name,
                'type' => $device->type?->value ?? $device->type,
                'name' => $device->name,
                'enabled' => (bool) $device->enabled,
                'runtime_device_id' => $runtimeId,
                'health' => $device->health,
                'state' => $device->state,
                'last_error' => $device->last_error,
                'last_health_at' => $device->last_health_at?->toIso8601String(),
                'is_healthy' => $device->isHealthy(),
                'sent_count' => (int) ($sentCounts[$device->id] ?? 0),
                'failed_count' => (int) ($failedCounts[$device->id] ?? 0),
                'sending' => $sending ? $this->mapQueueItem($sending) : null,
            ];
        });

        $groups = SmsDeviceGroup::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byGroup = $mappedDevices->groupBy('sms_device_group_id');

        $result = $groups->map(function (SmsDeviceGroup $group) use ($byGroup) {
            $devicesInGroup = ($byGroup->get($group->id) ?? collect())->values()->all();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'enabled' => (bool) $group->enabled,
                'sort_order' => $group->sort_order,
                'device_count' => count($devicesInGroup),
                'devices' => $devicesInGroup,
            ];
        })->values()->all();

        $ungrouped = ($byGroup->get(null) ?? collect())->values()->all();
        if ($ungrouped !== []) {
            $result[] = [
                'id' => null,
                'name' => 'Ungrouped',
                'enabled' => true,
                'sort_order' => 9999,
                'device_count' => count($ungrouped),
                'devices' => $ungrouped,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapQueueItem(SmsQueueItem $item): array
    {
        return [
            'id' => $item->id,
            'batch_id' => $item->sms_batch_id,
            'batch_priority' => $item->batch?->priority,
            'account_id' => $item->account_id,
            'account_number' => $item->account?->account_number,
            'account_name' => $item->account?->account_name,
            'recipient' => $item->recipient,
            'message' => \Illuminate\Support\Str::limit($item->message, 100),
            'reference' => $item->reference,
            'runtime_device_id' => $item->runtime_device_id,
            'status' => $item->status?->value ?? $item->status,
            'error_message' => $item->error_message,
            'sent_at' => $item->sent_at?->toIso8601String(),
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }
}
