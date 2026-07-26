<?php

namespace App\Http\Controllers;

use App\Models\SmsCallbackEvent;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SmsCallbackEventController extends Controller
{
    public function index(Request $request): Response
    {
        $query = SmsCallbackEvent::query();
        $this->applyFilters($query, $request);

        $events = ListingQuery::paginate(
            $query,
            $request,
            ['event_id', 'event_type', 'response_type', 'device_id'],
            ['id', 'event_id', 'event_type', 'response_type', 'device_id', 'event_timestamp', 'processed_at', 'created_at'],
        );

        $events->getCollection()->transform(function (SmsCallbackEvent $event): array {
            return [
                'id' => $event->id,
                'event_id' => $event->event_id,
                'event_type' => $event->event_type,
                'response_type' => $event->response_type,
                'device_id' => $event->device_id,
                'payload' => $event->payload,
                'payload_preview' => $this->payloadPreview($event->payload),
                'event_timestamp' => $event->event_timestamp?->toDateTimeString(),
                'processed_at' => $event->processed_at?->toDateTimeString(),
                'created_at' => $event->created_at?->toDateTimeString(),
            ];
        });

        return Inertia::render('Sms/Callbacks', [
            'events' => $events,
            'filters' => $request->only(['search', 'sort', 'direction', 'event_type', 'response_type', 'device_id']),
            'filterOptions' => [
                'eventTypes' => $this->distinctOptions('event_type'),
                'responseTypes' => $this->distinctOptions('response_type'),
                'devices' => $this->distinctOptions('device_id'),
            ],
            'can' => [
                'export' => $request->user()->hasPermission('sms.export'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = SmsCallbackEvent::query();
        $this->applyFilters($query, $request);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                foreach (['event_id', 'event_type', 'response_type', 'device_id'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $rows = $query->orderByDesc('id')->get()->map(fn (SmsCallbackEvent $event): array => [
            $event->id,
            $event->event_id,
            $event->event_type,
            $event->response_type,
            $event->device_id,
            $event->event_timestamp?->toDateTimeString(),
            $event->processed_at?->toDateTimeString(),
            $event->created_at?->toDateTimeString(),
            $event->payload ? json_encode($event->payload, JSON_UNESCAPED_UNICODE) : '',
        ]);

        return CsvExporter::download('sms-callbacks.csv', [
            'ID', 'Event ID', 'Event Type', 'Response Type', 'Device ID',
            'Event Timestamp', 'Processed At', 'Created At', 'Payload',
        ], $rows);
    }

    protected function applyFilters($query, Request $request): void
    {
        if ($eventType = $request->string('event_type')->trim()->toString()) {
            $query->where('event_type', $eventType);
        }

        if ($responseType = $request->string('response_type')->trim()->toString()) {
            $query->where('response_type', $responseType);
        }

        if ($deviceId = $request->string('device_id')->trim()->toString()) {
            $query->where('device_id', $deviceId);
        }
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    protected function distinctOptions(string $column): array
    {
        return SmsCallbackEvent::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->limit(100)
            ->pluck($column)
            ->map(fn ($value) => ['id' => (string) $value, 'name' => (string) $value])
            ->values()
            ->all();
    }

    protected function payloadPreview(mixed $payload): string
    {
        if ($payload === null || $payload === []) {
            return '—';
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (! is_string($json) || $json === '') {
            return '—';
        }

        return mb_strlen($json) > 120 ? mb_substr($json, 0, 117).'…' : $json;
    }
}
