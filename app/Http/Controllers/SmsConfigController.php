<?php

namespace App\Http\Controllers;

use App\Enums\SmsDeviceType;
use App\Models\SmsDevice;
use App\Models\SmsDeviceGroup;
use App\Models\SmsSetting;
use App\Services\AuditLogger;
use App\Services\Sms\SmsConfigJsonWriter;
use App\Services\Sms\SmsServiceClient;
use App\Services\Sms\SmsServiceProcessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SmsConfigController extends Controller
{
    public function index(Request $request, SmsServiceProcessManager $processManager): Response
    {
        $request->session()->save();

        $settings = SmsSetting::current();
        $status = $processManager->status($settings);
        $callbacks = $settings->resolvedConfigCallbacks();

        $defaults = SmsSetting::defaults();

        return Inertia::render('Sms/Config', [
            'defaults' => [
                'service_base_url' => $defaults['service_base_url'],
                'api_key' => $defaults['api_key'],
                'callback_base_url' => $defaults['callback_base_url'],
                'service_exe_path' => $defaults['service_exe_path'],
                'config_json_path' => $defaults['config_json_path'],
                'http_ports_to_test' => $defaults['http_ports_to_test'],
                'dashboard_health_poll_seconds' => $defaults['dashboard_health_poll_seconds'] ?? 300,
                'dashboard_list_poll_seconds' => $defaults['dashboard_list_poll_seconds'] ?? 8,
                'config_service' => SmsSetting::defaultConfigService(),
                'config_logging' => SmsSetting::defaultConfigLogging(),
                'config_http' => SmsSetting::defaultConfigHttp(),
                'config_callbacks' => SmsSetting::defaultConfigCallbacks(),
                'config_queue' => SmsSetting::defaultConfigQueue(),
            ],
            'settings' => [
                'service_base_url' => $settings->service_base_url,
                'api_key' => $settings->api_key,
                'callback_base_url' => $settings->callback_base_url,
                'service_exe_path' => $settings->service_exe_path,
                'config_json_path' => $settings->config_json_path,
                'auto_detect_at_ports' => (bool) $settings->auto_detect_at_ports,
                'dashboard_health_poll_seconds' => $settings->resolvedDashboardHealthPollSeconds(),
                'dashboard_list_poll_seconds' => $settings->resolvedDashboardListPollSeconds(),
                'http_ports_to_test' => $settings->http_ports_to_test ?? [8080],
                'resolved_config_json_path' => $settings->resolvedConfigJsonPath(),
                'resolved_callback_url' => $this->resolvedCallbackUrl($settings),
                'config_service' => $settings->resolvedConfigService(),
                'config_logging' => $settings->resolvedConfigLogging(),
                'config_http' => $settings->resolvedConfigHttp(),
                'config_callbacks' => $callbacks,
                'config_queue' => $settings->resolvedConfigQueue(),
            ],
            'service' => [
                'running' => $status['running'],
                'error' => $status['error'],
                'health' => $status['health'],
            ],
            'deviceGroups' => SmsDeviceGroup::query()
                ->withCount('devices')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (SmsDeviceGroup $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'enabled' => (bool) $g->enabled,
                    'sort_order' => $g->sort_order,
                    'devices_count' => (int) $g->devices_count,
                    'is_default' => $g->name === SmsDeviceGroup::DEFAULT_NAME,
                ]),
            'devices' => SmsDevice::query()
                ->with('group:id,name')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (SmsDevice $d) => [
                    'id' => $d->id,
                    'sms_device_group_id' => $d->sms_device_group_id,
                    'group_name' => $d->group?->name,
                    'type' => $d->type?->value ?? $d->type,
                    'name' => $d->name,
                    'enabled' => (bool) $d->enabled,
                    'sort_order' => $d->sort_order,
                    'config' => $d->config ?? [],
                    'runtime_device_id' => $d->runtime_device_id,
                    'health' => $d->health,
                    'state' => $d->state,
                    'last_error' => $d->last_error,
                    'last_health_at' => $d->last_health_at?->toIso8601String(),
                ]),
            'deviceTypes' => collect(SmsDeviceType::cases())->map(fn (SmsDeviceType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
            'can' => [
                'manage' => true,
            ],
        ]);
    }

    public function update(Request $request, AuditLogger $auditLogger, SmsConfigJsonWriter $writer): RedirectResponse
    {
        $data = $request->validate([
            'service_base_url' => ['required', 'string', 'max:500'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'callback_base_url' => ['nullable', 'string', 'max:500'],
            'service_exe_path' => ['nullable', 'string', 'max:1000'],
            'config_json_path' => ['required', 'string', 'max:1000'],
            'auto_detect_at_ports' => ['boolean'],
            'dashboard_health_poll_seconds' => ['required', 'integer', 'min:30', 'max:3600'],
            'dashboard_list_poll_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'http_ports_to_test' => ['nullable', 'array'],
            'http_ports_to_test.*' => ['integer', 'min:1', 'max:65535'],
            'sync_config_json' => ['boolean'],
            'config_service' => ['nullable', 'array'],
            'config_service.service_id' => ['required', 'string', 'max:255'],
            'config_service.friendly_name' => ['nullable', 'string', 'max:255'],
            'config_service.listen_address' => ['nullable', 'string', 'max:255'],
            'config_service.listen_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'config_service.timezone' => ['nullable', 'string', 'max:100'],
            'config_service.shutdown_timeout_seconds' => ['nullable', 'integer', 'min:1'],
            'config_service.auto_detect_at_devices' => ['boolean'],
            'config_logging' => ['nullable', 'array'],
            'config_logging.level' => ['nullable', 'string', 'max:32'],
            'config_logging.directory' => ['required', 'string', 'max:500'],
            'config_logging.max_file_size_mb' => ['nullable', 'integer', 'min:1'],
            'config_logging.max_files' => ['nullable', 'integer', 'min:1'],
            'config_logging.console' => ['boolean'],
            'config_http' => ['nullable', 'array'],
            'config_http.request_timeout_seconds' => ['nullable', 'integer', 'min:1'],
            'config_http.worker_threads' => ['nullable', 'integer', 'min:1'],
            'config_http.keep_alive' => ['boolean'],
            'config_callbacks' => ['nullable', 'array'],
            'config_callbacks.base_url' => ['required', 'string', 'max:500'],
            'config_callbacks.api_key' => ['required', 'string', 'max:255'],
            'config_callbacks.retry_attempts' => ['nullable', 'integer', 'min:0'],
            'config_callbacks.retry_delay_seconds' => ['nullable', 'integer', 'min:0'],
            'config_callbacks.connect_timeout_seconds' => ['nullable', 'integer', 'min:1'],
            'config_callbacks.request_timeout_seconds' => ['nullable', 'integer', 'min:1'],
            'config_queue' => ['nullable', 'array'],
            'config_queue.max_size' => ['nullable', 'integer', 'min:1'],
            'config_queue.worker_threads' => ['nullable', 'integer', 'min:1'],
        ]);

        $serviceSection = array_merge(SmsSetting::defaultConfigService(), $data['config_service'] ?? []);
        $loggingSection = array_merge(SmsSetting::defaultConfigLogging(), $data['config_logging'] ?? []);
        $httpSection = array_merge(SmsSetting::defaultConfigHttp(), $data['config_http'] ?? []);
        $callbacksSection = array_merge(SmsSetting::defaultConfigCallbacks(), $data['config_callbacks'] ?? []);
        $queueSection = array_merge(SmsSetting::defaultConfigQueue(), $data['config_queue'] ?? []);

        $autoDetect = (bool) ($data['auto_detect_at_ports']
            ?? $serviceSection['auto_detect_at_devices']
            ?? true);
        $serviceSection['auto_detect_at_devices'] = $autoDetect;

        $ports = $data['http_ports_to_test'] ?? [8080];
        if (isset($serviceSection['listen_port'])) {
            $ports[0] = (int) $serviceSection['listen_port'];
        } elseif (is_array($ports) && isset($ports[0])) {
            $serviceSection['listen_port'] = (int) $ports[0];
        }

        $apiKey = (string) ($callbacksSection['api_key'] ?? $data['api_key'] ?? '');
        $callbackBase = (string) ($data['callback_base_url'] ?? '');
        if ($callbackBase === '' && ! empty($callbacksSection['base_url'])) {
            $callbackBase = preg_replace('#/api/sms/callback$#', '', (string) $callbacksSection['base_url']) ?: '';
        }

        $settings = SmsSetting::current();
        $settings->fill([
            'service_base_url' => $data['service_base_url'],
            'api_key' => $apiKey !== '' ? $apiKey : ($data['api_key'] ?? ''),
            'callback_base_url' => $callbackBase !== '' ? $callbackBase : ($data['callback_base_url'] ?? null),
            'auto_start_enabled' => false,
            'service_exe_path' => $data['service_exe_path'] ?? null,
            'config_json_path' => $data['config_json_path'],
            'auto_detect_at_ports' => $autoDetect,
            'dashboard_health_poll_seconds' => (int) $data['dashboard_health_poll_seconds'],
            'dashboard_list_poll_seconds' => (int) $data['dashboard_list_poll_seconds'],
            'http_ports_to_test' => $ports,
            'config_service' => $serviceSection,
            'config_logging' => $loggingSection,
            'config_http' => $httpSection,
            'config_callbacks' => $callbacksSection,
            'config_queue' => $queueSection,
        ])->save();

        $syncMessage = '';
        if ($request->boolean('sync_config_json', true)) {
            try {
                $result = $writer->write($settings->fresh());
                $syncMessage = " Config.json written ({$result['devices']} device(s)) at {$result['path']}.";
            } catch (Throwable $e) {
                $auditLogger->log('sms.config.updated', null, null, [
                    'sync_failed' => $e->getMessage(),
                ]);

                return back()->with('success', 'SMS settings saved.'.$syncMessage)
                    ->with('error', 'Config.json sync failed: '.$e->getMessage());
            }
        }

        $auditLogger->log('sms.config.updated', null, null, [
            'auto_detect_at_ports' => $settings->auto_detect_at_ports,
            'config_json_path' => $settings->config_json_path,
        ]);

        return back()->with('success', 'SMS settings saved.'.$syncMessage);
    }

    public function storeDeviceGroup(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validateDeviceGroup($request);

        $group = SmsDeviceGroup::query()->create([
            'name' => $data['name'],
            'enabled' => (bool) ($data['enabled'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ]);

        $auditLogger->log('sms.device_group.created', $group, null);

        return back()->with('success', 'Device group created.');
    }

    public function updateDeviceGroup(Request $request, SmsDeviceGroup $smsDeviceGroup, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validateDeviceGroup($request, $smsDeviceGroup);

        if ($smsDeviceGroup->name === SmsDeviceGroup::DEFAULT_NAME
            && $data['name'] !== SmsDeviceGroup::DEFAULT_NAME) {
            throw ValidationException::withMessages([
                'name' => 'The Default device group cannot be renamed.',
            ]);
        }

        $smsDeviceGroup->fill([
            'name' => $data['name'],
            'enabled' => (bool) ($data['enabled'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ])->save();

        $auditLogger->log('sms.device_group.updated', $smsDeviceGroup, null);

        return back()->with('success', 'Device group updated.');
    }

    public function destroyDeviceGroup(SmsDeviceGroup $smsDeviceGroup, AuditLogger $auditLogger): RedirectResponse
    {
        if ($smsDeviceGroup->devices()->exists()) {
            throw ValidationException::withMessages([
                'group' => 'Move or delete devices in this group before removing it.',
            ]);
        }

        if ($smsDeviceGroup->name === SmsDeviceGroup::DEFAULT_NAME) {
            throw ValidationException::withMessages([
                'group' => 'The Default device group cannot be deleted.',
            ]);
        }

        $id = $smsDeviceGroup->id;
        $smsDeviceGroup->delete();
        $auditLogger->log('sms.device_group.deleted', null, null, ['id' => $id]);

        return back()->with('success', 'Device group removed.');
    }

    public function storeDevice(Request $request, AuditLogger $auditLogger, SmsConfigJsonWriter $writer): RedirectResponse
    {
        $data = $this->validateDevice($request);

        $device = SmsDevice::query()->create([
            'sms_device_group_id' => $data['sms_device_group_id'],
            'type' => $data['type'],
            'name' => $data['name'],
            'enabled' => (bool) ($data['enabled'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'config' => $this->normalizeDeviceConfig($data['config'] ?? [], $data['type']),
            'runtime_device_id' => $data['runtime_device_id'] ?? null,
        ]);

        $this->tryWriteConfig($writer);

        $auditLogger->log('sms.device.created', $device, null);

        return back()->with('success', 'SMS device added.');
    }

    public function updateDevice(Request $request, SmsDevice $smsDevice, AuditLogger $auditLogger, SmsConfigJsonWriter $writer): RedirectResponse
    {
        $data = $this->validateDevice($request);

        $smsDevice->fill([
            'sms_device_group_id' => $data['sms_device_group_id'],
            'type' => $data['type'],
            'name' => $data['name'],
            'enabled' => (bool) ($data['enabled'] ?? true),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'config' => $this->normalizeDeviceConfig($data['config'] ?? [], $data['type']),
            'runtime_device_id' => $data['runtime_device_id'] ?? $smsDevice->runtime_device_id,
        ])->save();

        $this->tryWriteConfig($writer);

        $auditLogger->log('sms.device.updated', $smsDevice, null);

        return back()->with('success', 'SMS device updated.');
    }

    public function destroyDevice(SmsDevice $smsDevice, AuditLogger $auditLogger, SmsConfigJsonWriter $writer): RedirectResponse
    {
        $smsDevice->delete();
        $this->tryWriteConfig($writer);
        $auditLogger->log('sms.device.deleted', null, null, ['id' => $smsDevice->id]);

        return back()->with('success', 'SMS device removed.');
    }

    public function testPorts(Request $request, SmsServiceClient $client): JsonResponse
    {
        $settings = SmsSetting::current();
        $client->refreshSettings($settings);

        $data = $request->validate([
            'ports' => ['nullable', 'array'],
            'ports.*' => ['integer', 'min:1', 'max:65535'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'service_base_url' => ['nullable', 'string', 'max:500'],
        ]);

        $ports = $data['ports'] ?? $settings->http_ports_to_test ?? [8080];
        if (! is_array($ports) || $ports === []) {
            $ports = [8080];
        }

        $overrides = [
            'api_key' => $data['api_key'] ?? $settings->api_key,
            'service_base_url' => $data['service_base_url'] ?? $settings->service_base_url,
        ];

        $probeSettings = $settings->replicate();
        $probeSettings->api_key = $overrides['api_key'];
        $probeSettings->service_base_url = $overrides['service_base_url'];
        $client->refreshSettings($probeSettings);

        $results = [];
        foreach ($ports as $port) {
            $results[] = $client->testPort((int) $port, $overrides);
        }

        return response()->json([
            'results' => $results,
        ]);
    }

    public function syncConfig(SmsConfigJsonWriter $writer, AuditLogger $auditLogger): RedirectResponse
    {
        try {
            $result = $writer->write();
            $auditLogger->log('sms.config.synced', null, null, $result);

            return back()->with('success', "Full config.json written ({$result['devices']} device(s)) at {$result['path']}.");
        } catch (Throwable $e) {
            return back()->with('error', 'Config.json sync failed: '.$e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateDeviceGroup(Request $request, ?SmsDeviceGroup $group = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sms_device_groups', 'name')->ignore($group?->id),
            ],
            'enabled' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateDevice(Request $request): array
    {
        $type = (string) $request->input('type');

        return $request->validate([
            'sms_device_group_id' => ['required', 'exists:sms_device_groups,id'],
            'type' => ['required', Rule::in(SmsDeviceType::values())],
            'name' => ['required', 'string', 'max:255'],
            'enabled' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'runtime_device_id' => ['nullable', 'string', 'max:255'],
            'config' => ['nullable', 'array'],
            'config.port' => ['required', 'string', 'max:64'],
            'config.baud_rate' => ['nullable', 'integer', 'min:1'],
            'config.baud' => ['nullable', 'integer', 'min:1'],
            'config.com_port' => ['nullable', 'string', 'max:64'],
            'config.demo_send_success_rate' => [
                Rule::requiredIf($type === SmsDeviceType::Demo->value),
                'nullable',
                'numeric',
                'min:0',
                'max:1',
            ],
            'config.demo_receive_interval_seconds' => [
                Rule::requiredIf($type === SmsDeviceType::Demo->value),
                'nullable',
                'integer',
                'min:1',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function normalizeDeviceConfig(array $config, string $type): array
    {
        $port = trim((string) ($config['port'] ?? $config['com_port'] ?? ''));
        if ($port === '' && $type === SmsDeviceType::Demo->value) {
            $port = 'COM99';
        }

        $baud = (int) ($config['baud_rate'] ?? $config['baud'] ?? 115200);
        if ($baud <= 0) {
            $baud = 115200;
        }

        $normalized = [
            'port' => $port,
            'baud_rate' => $baud,
        ];

        foreach ([
            'encoding',
            'auto_delete',
            'poll_interval_ms',
            'health_check_interval_seconds',
            'command_timeout_ms',
            'prompt_timeout_ms',
            'operation_timeout_seconds',
            'sync_timeout_seconds',
            'send_retry_count',
            'failure_retry_count',
            'restart_retry_count',
            'receive_enabled',
            'send_enabled',
            'recipient_format',
        ] as $key) {
            if (array_key_exists($key, $config) && $config[$key] !== null && $config[$key] !== '') {
                $normalized[$key] = $config[$key];
            }
        }

        if ($type === SmsDeviceType::Demo->value) {
            $rate = $config['demo_send_success_rate'] ?? SmsDevice::DEFAULT_DEMO_SEND_SUCCESS_RATE;
            $normalized['demo_send_success_rate'] = max(0.0, min(1.0, (float) $rate));
            $interval = $config['demo_receive_interval_seconds'] ?? SmsDevice::DEFAULT_DEMO_RECEIVE_INTERVAL_SECONDS;
            $normalized['demo_receive_interval_seconds'] = max(1, (int) $interval);
        }

        return $normalized;
    }

    protected function tryWriteConfig(SmsConfigJsonWriter $writer): void
    {
        try {
            $writer->write();
        } catch (Throwable) {
            // Settings/devices still saved; config path may be unset.
        }
    }

    protected function resolvedCallbackUrl(SmsSetting $settings): string
    {
        $callbacks = $settings->resolvedConfigCallbacks();
        if (! empty($callbacks['base_url'])) {
            return (string) $callbacks['base_url'];
        }

        $base = rtrim((string) ($settings->callback_base_url ?: url('/')), '/');

        return str_ends_with($base, '/api/sms/callback')
            ? $base
            : $base.'/api/sms/callback';
    }
}
