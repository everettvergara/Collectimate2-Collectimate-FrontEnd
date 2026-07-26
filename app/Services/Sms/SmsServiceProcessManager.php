<?php

namespace App\Services\Sms;

use App\Models\SmsSetting;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SmsServiceProcessManager
{
    public function __construct(
        protected SmsServiceClient $client,
    ) {}

    /**
     * @return array{running: bool, health: array<string, mixed>, error: string|null}
     */
    public function status(?SmsSetting $settings = null): array
    {
        // Release session lock before outbound HTTP so other pages are not blocked.
        if (app()->bound('session') && session()->isStarted()) {
            session()->save();
        }

        if ($settings) {
            $this->client->refreshSettings($settings);
        }

        // Fast path: ping only. Avoid stacking a second long health wait when down.
        $ping = $this->client->ping(timeout: 2);
        if (! $ping) {
            return [
                'running' => false,
                'health' => ['ok' => false, 'error' => 'SMS service is not reachable.'],
                'error' => 'SMS service is not reachable.',
            ];
        }

        $health = $this->client->health(timeout: 2);

        if ($settings) {
            if (! $settings->service_started_at) {
                $settings->forceFill([
                    'service_started_at' => now(),
                    'sms_sent_since_start' => 0,
                ])->save();
            }
            $settings->clearAlert('service_down');
        }

        return [
            'running' => true,
            'health' => $health,
            'error' => null,
        ];
    }

    /**
     * @return array{started: bool, already_running: bool, message: string, error: string|null}
     */
    public function ensureStarted(?SmsSetting $settings = null, bool $forceSpawn = false): array
    {
        $settings ??= SmsSetting::current();
        $this->client->refreshSettings($settings);

        $status = $this->status($settings);
        if ($status['running']) {
            if (! $settings->service_started_at) {
                $settings->forceFill([
                    'service_started_at' => now(),
                    'sms_sent_since_start' => 0,
                ])->save();
            }
            $settings->clearAlert('service_down');
            $settings->clearAlert('service_start_failed');

            return [
                'started' => false,
                'already_running' => true,
                'message' => 'SMS service is already running.',
                'error' => null,
            ];
        }

        if (! $forceSpawn) {
            $settings->setAlert('service_down', 'SMS service is not running. Start it manually from the SMS Dashboard.');

            return [
                'started' => false,
                'already_running' => false,
                'message' => 'SMS service is not running.',
                'error' => 'SMS service is not running. Start it manually from the SMS Dashboard.',
            ];
        }

        $exe = trim((string) $settings->service_exe_path);
        if ($exe === '') {
            $message = 'SMS service executable path is not configured.';
            $settings->setAlert('service_not_found', $message);

            return [
                'started' => false,
                'already_running' => false,
                'message' => $message,
                'error' => $message,
            ];
        }

        if (! is_file($exe)) {
            $message = "SMS service executable was not found at: {$exe}";
            $settings->setAlert('service_not_found', $message);

            return [
                'started' => false,
                'already_running' => false,
                'message' => $message,
                'error' => $message,
            ];
        }

        $configPath = $settings->resolvedConfigJsonPath();
        if (! $configPath || ! is_file($configPath)) {
            $message = 'SMS config.json was not found. Save SMS Configuration (sync config.json) before starting the service.'
                .($configPath ? " Expected: {$configPath}" : '');
            $settings->setAlert('service_start_failed', $message);

            return [
                'started' => false,
                'already_running' => false,
                'message' => $message,
                'error' => $message,
            ];
        }

        // Service root = parent of config/ (where `config/config.json` lives relative to README run examples).
        $cwd = dirname(dirname($configPath));
        if (! is_dir($cwd)) {
            $cwd = dirname($exe);
        }

        try {
            $this->spawn($exe, $configPath, $cwd);
        } catch (\Throwable $e) {
            Log::warning('Failed to start SMS service process', [
                'exe' => $exe,
                'config' => $configPath,
                'cwd' => $cwd,
                'error' => $e->getMessage(),
            ]);
            $message = 'Unable to start SMS service: '.$e->getMessage();
            $settings->setAlert('service_start_failed', $message);

            return [
                'started' => false,
                'already_running' => false,
                'message' => $message,
                'error' => $message,
            ];
        }

        // Poll until HTTP is reachable — exe often needs >1.5s to bind (Test ports used to add another 1.2s).
        $deadline = microtime(true) + 5.0;
        $after = ['running' => false, 'health' => [], 'error' => null];

        do {
            usleep(500_000);
            $this->client->refreshSettings($settings->fresh());
            $after = $this->status($settings->fresh());
            if ($after['running']) {
                break;
            }
        } while (microtime(true) < $deadline);

        if (! $after['running']) {
            $message = 'SMS service process was started but health check failed on '
                .($settings->service_base_url ?: 'configured base URL')
                .'. Confirm the exe was launched with config.json and listen_port is free.';
            $settings->setAlert('service_start_failed', $message);

            return [
                'started' => true,
                'already_running' => false,
                'message' => $message,
                'error' => $message,
            ];
        }

        $settings->forceFill([
            'service_started_at' => now(),
            'sms_sent_since_start' => 0,
        ])->save();
        $settings->clearAlert();

        return [
            'started' => true,
            'already_running' => false,
            'message' => 'SMS service started successfully.',
            'error' => null,
        ];
    }

    /**
     * @return array{stopped: bool, message: string, error: string|null}
     */
    public function stop(?SmsSetting $settings = null): array
    {
        $settings ??= SmsSetting::current();
        $this->client->refreshSettings($settings);

        $exe = trim((string) $settings->service_exe_path);
        $basename = $exe !== '' ? basename($exe) : 'collectimate_sms_service.exe';

        $killed = false;
        try {
            if (PHP_OS_FAMILY === 'Windows') {
                $command = 'taskkill /F /IM '.escapeshellarg($basename).' /T';
                $process = Process::fromShellCommandline($command);
                $process->setTimeout(15);
                $process->run();
                $killed = $process->isSuccessful()
                    || str_contains(strtolower($process->getOutput().$process->getErrorOutput()), 'terminated');
            } else {
                $process = Process::fromShellCommandline('pkill -f '.escapeshellarg($basename));
                $process->setTimeout(15);
                $process->run();
                $killed = $process->isSuccessful();
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to stop SMS service process', ['error' => $e->getMessage()]);

            return [
                'stopped' => false,
                'message' => 'Unable to stop SMS service: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }

        usleep(800_000);
        $this->client->refreshSettings($settings->fresh());
        $stillUp = $this->status($settings->fresh())['running'];

        if ($stillUp) {
            $message = $killed
                ? 'Process kill was issued but the SMS HTTP API is still reachable.'
                : 'SMS service process was not matched; HTTP API may still be running under another process.';
            $settings->setAlert('service_down', $message);

            return [
                'stopped' => false,
                'message' => $message,
                'error' => $message,
            ];
        }

        $settings->forceFill([
            'service_started_at' => null,
            'sms_sent_since_start' => 0,
        ])->save();
        $settings->setAlert('service_down', 'SMS service was stopped.');

        return [
            'stopped' => true,
            'message' => 'SMS service stopped.',
            'error' => null,
        ];
    }

    /**
     * @return array{restarted: bool, message: string, error: string|null}
     */
    public function restartProcess(?SmsSetting $settings = null): array
    {
        $settings ??= SmsSetting::current();
        $stop = $this->stop($settings);
        $start = $this->ensureStarted($settings->fresh(), forceSpawn: true);

        if ($start['error']) {
            return [
                'restarted' => false,
                'message' => 'Stop: '.$stop['message'].' Start failed: '.$start['error'],
                'error' => $start['error'],
            ];
        }

        return [
            'restarted' => true,
            'message' => 'SMS service restarted.',
            'error' => null,
        ];
    }

    protected function spawn(string $exe, string $configPath, string $cwd): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Detached start with absolute config path (required by collectimate_sms_service).
            $command = 'cmd /c start "" /B '
                .escapeshellarg($exe).' '
                .escapeshellarg($configPath);
            $process = Process::fromShellCommandline($command, $cwd);
            $process->setTimeout(8);
            $process->run();

            if ($process->getExitCode() !== 0 && $process->getExitCode() !== null) {
                throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'start command failed');
            }

            return;
        }

        $process = new Process([$exe, $configPath], $cwd);
        $process->setTimeout(5);
        $process->start();
    }
}
