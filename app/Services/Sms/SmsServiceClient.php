<?php

namespace App\Services\Sms;

use App\Models\SmsSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsServiceClient
{
    protected SmsSetting $settings;

    public function __construct()
    {
        // Avoid container injecting an empty SmsSetting model.
        $this->settings = SmsSetting::current();
    }

    public function settings(): SmsSetting
    {
        return $this->settings;
    }

    public function refreshSettings(?SmsSetting $settings = null): void
    {
        $this->settings = $settings ?? SmsSetting::current();
    }

    public function ping(int $timeout = 2): bool
    {
        try {
            $response = $this->rawGet('/ping', timeout: $timeout);

            return $response->successful() && str_contains(strtolower($response->body()), 'pong');
        } catch (ConnectionException|RuntimeException) {
            return false;
        }
    }

    /**
     * @return array{ok: bool, status?: string, raw?: mixed, error?: string}
     */
    public function health(int $timeout = 2): array
    {
        try {
            $response = $this->get('/health', timeout: $timeout);
            $data = $response['data'] ?? $response;

            return [
                'ok' => true,
                'status' => is_array($data) ? ($data['status'] ?? 'Unknown') : (string) $data,
                'raw' => $response,
            ];
        } catch (ConnectionException|RuntimeException $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function info(): array
    {
        return $this->get('/info');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listDevices(): array
    {
        $response = $this->get('/devices');
        $data = $response['data'] ?? $response;

        if (! is_array($data)) {
            return [];
        }

        // Normalize list vs wrapped payload.
        if (array_is_list($data)) {
            return $data;
        }

        if (isset($data['devices']) && is_array($data['devices'])) {
            return array_values($data['devices']);
        }

        return array_values($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDevice(string $deviceId): array
    {
        return $this->get('/devices/'.rawurlencode($deviceId));
    }

    /**
     * @return array<string, mixed>
     */
    public function sendSms(string $deviceId, string $recipient, string $message, string $reference): array
    {
        return $this->post('/sms/send', [
            'device_id' => $deviceId,
            'recipient' => $recipient,
            'message' => $message,
            'reference' => $reference,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function restartDevice(string $deviceId): array
    {
        return $this->post('/devices/'.rawurlencode($deviceId).'/restart');
    }

    /**
     * @return array<string, mixed>
     */
    public function deleteDevice(string $deviceId): array
    {
        return $this->delete('/devices/'.rawurlencode($deviceId));
    }

    /**
     * @return array<string, mixed>
     */
    public function startDevice(string $deviceId): array
    {
        return $this->post('/devices/'.rawurlencode($deviceId).'/start');
    }

    /**
     * Probe host:port for SMS service ping/health.
     *
     * @param  array{api_key?: string|null, service_base_url?: string|null}  $overrides
     * @return array{port: int, host: string, url: string, reachable: bool, ping: bool, health: string|null, error: string|null}
     */
    public function testPort(int $port, array $overrides = []): array
    {
        $baseUrl = (string) ($overrides['service_base_url'] ?? $this->settings->service_base_url ?: 'http://127.0.0.1:8080/api/v1');
        $parts = parse_url($baseUrl);
        $host = $parts['host'] ?? '127.0.0.1';
        $scheme = $parts['scheme'] ?? 'http';
        $base = sprintf('%s://%s:%d/api/v1', $scheme, $host, $port);
        $apiKey = array_key_exists('api_key', $overrides)
            ? (string) ($overrides['api_key'] ?? '')
            : (string) ($this->settings->api_key ?? '');

        $headers = array_filter([
            'X-API-Key' => $apiKey !== '' ? $apiKey : null,
        ]);

        try {
            $ping = Http::connectTimeout(1)
                ->timeout(2)
                ->withHeaders($headers)
                ->get($base.'/ping');

            $body = trim((string) $ping->body());
            $pingOk = $ping->successful() && str_contains(strtolower($body), 'pong');

            if ($ping->status() === 401) {
                return [
                    'port' => $port,
                    'host' => $host,
                    'url' => $base.'/ping',
                    'reachable' => true,
                    'ping' => false,
                    'health' => null,
                    'error' => 'Unauthorized (HTTP 401) — API key does not match the SMS service callbacks.api_key.',
                ];
            }

            $healthStatus = null;
            $healthError = null;

            try {
                $health = Http::connectTimeout(1)
                    ->timeout(2)
                    ->acceptJson()
                    ->withHeaders($headers)
                    ->get($base.'/health');

                if ($health->successful()) {
                    $json = $health->json();
                    $healthStatus = data_get($json, 'data.status')
                        ?? data_get($json, 'status')
                        ?? 'Unknown';
                } elseif ($health->status() === 401) {
                    $healthError = 'Unauthorized (HTTP 401) on /health — check API key.';
                } else {
                    $healthError = 'Health HTTP '.$health->status();
                }
            } catch (ConnectionException $e) {
                $healthError = $this->friendlyConnectionError($e, $host, $port);
            }

            $ok = $pingOk || ($healthStatus !== null);

            return [
                'port' => $port,
                'host' => $host,
                'url' => $base.'/ping',
                'reachable' => $ping->successful() || $ok || $ping->status() > 0,
                'ping' => $pingOk,
                'health' => $healthStatus,
                'error' => $ok
                    ? null
                    : ($healthError
                        ?? ($ping->successful()
                            ? 'Unexpected ping body: '.mb_substr($body, 0, 120)
                            : 'HTTP '.$ping->status().($body !== '' ? ' — '.mb_substr($body, 0, 120) : ''))),
            ];
        } catch (ConnectionException $e) {
            return [
                'port' => $port,
                'host' => $host,
                'url' => $base.'/ping',
                'reachable' => false,
                'ping' => false,
                'health' => null,
                'error' => $this->friendlyConnectionError($e, $host, $port),
            ];
        }
    }

    protected function friendlyConnectionError(ConnectionException $e, string $host, int $port): string
    {
        $raw = $e->getMessage();

        if (str_contains(strtolower($raw), 'failed to connect')
            || str_contains(strtolower($raw), 'connection refused')
            || str_contains($raw, 'HTTP_CODE:000')
            || str_contains(strtolower($raw), 'couldn\'t connect')) {
            return "Connection refused on {$host}:{$port} — SMS service is not listening. Start the executable or use Start service on the SMS Dashboard.";
        }

        return $raw;
    }

    protected function hostFromBaseUrl(): string
    {
        $parts = parse_url($this->settings->service_base_url ?: 'http://127.0.0.1:8080/api/v1');

        return $parts['host'] ?? '127.0.0.1';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function post(string $path, array $body = []): array
    {
        return $this->json($this->http()->post($this->url($path), $body));
    }

    /**
     * @return array<string, mixed>
     */
    protected function get(string $path, int $timeout = 10): array
    {
        return $this->json($this->http(timeout: $timeout)->get($this->url($path)));
    }

    /**
     * @return array<string, mixed>
     */
    protected function delete(string $path): array
    {
        return $this->json($this->http()->delete($this->url($path)));
    }

    protected function rawGet(string $path, int $timeout = 10): Response
    {
        return $this->http(timeout: $timeout, acceptJson: false)->get($this->url($path));
    }

    protected function http(int $timeout = 15, bool $acceptJson = true): PendingRequest
    {
        $connect = min(2, max(1, (int) ceil($timeout / 2)));
        $request = Http::connectTimeout($connect)
            ->timeout($timeout)
            ->withHeaders($this->headers());

        if ($acceptJson) {
            $request = $request->acceptJson();
        }

        return $request;
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        $key = (string) ($this->settings->api_key ?? '');

        return array_filter([
            'X-API-Key' => $key !== '' ? $key : null,
        ]);
    }

    protected function url(string $path): string
    {
        $base = rtrim((string) $this->settings->service_base_url, '/');
        if ($base === '') {
            throw new RuntimeException('SMS service base URL is not configured.');
        }

        return $base.'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(Response $response): array
    {
        if ($response->failed()) {
            throw new RuntimeException(
                'SMS service error HTTP '.$response->status().': '.$response->body()
            );
        }

        $json = $response->json();

        return is_array($json) ? $json : ['data' => $json];
    }
}
