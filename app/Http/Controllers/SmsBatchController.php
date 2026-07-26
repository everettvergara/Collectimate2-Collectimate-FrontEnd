<?php

namespace App\Http\Controllers;

use App\Enums\SmsBatchSource;
use App\Enums\SmsBatchStatus;
use App\Enums\SmsQueueItemStatus;
use App\Models\SmsBatch;
use App\Models\SmsQueueItem;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Sms\SmsQueueService;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SmsBatchController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $query = SmsBatch::query()->with(['createdBy:id,username', 'agentProfile:id,display_name,first_name,last_name']);
        $this->applyBatchFilters($query, $request);

        $batches = ListingQuery::paginate(
            $query,
            $request,
            ['message_body', 'id'],
            ['id', 'status', 'priority', 'source', 'total', 'queued', 'sending', 'sent', 'failed', 'cancelled', 'created_at'],
            'id',
            'desc',
        );

        $batches->getCollection()->transform(fn (SmsBatch $batch) => $this->mapBatch($batch));

        return Inertia::render('Sms/Batches/Index', [
            'batches' => $batches,
            'filters' => $request->only(['search', 'sort', 'direction', 'status', 'source']),
            'filterOptions' => [
                'statuses' => collect(SmsBatchStatus::cases())->map(fn ($s) => [
                    'id' => $s->value,
                    'name' => ucfirst($s->value),
                ])->values()->all(),
                'sources' => collect(SmsBatchSource::cases())->map(fn ($s) => [
                    'id' => $s->value,
                    'name' => str_replace('_', ' ', $s->value),
                ])->values()->all(),
            ],
            'can' => [
                'manage' => $user->hasPermission('sms.manage'),
                'cancel' => $user->hasPermission('sms.queue.cancel'),
                'export' => $user->hasPermission('sms.export'),
            ],
        ]);
    }

    public function show(Request $request, SmsBatch $smsBatch): Response
    {
        /** @var User $user */
        $user = $request->user();

        $smsBatch->load(['createdBy:id,username', 'agentProfile:id,display_name,first_name,last_name']);

        $itemsQuery = SmsQueueItem::query()
            ->with(['account:id,account_number,account_name', 'assignedDevice:id,name,runtime_device_id'])
            ->where('sms_batch_id', $smsBatch->id);

        if ($itemStatus = $request->string('item_status')->trim()->toString()) {
            $itemsQuery->where('status', $itemStatus);
        }

        $items = ListingQuery::paginate(
            $itemsQuery,
            $request,
            ['recipient', 'message', 'reference', 'error_message'],
            ['id', 'status', 'recipient', 'sent_at', 'failed_at', 'created_at'],
            'id',
            'asc',
        );

        $items->getCollection()->transform(fn (SmsQueueItem $item) => $this->mapItem($item));

        return Inertia::render('Sms/Batches/Show', [
            'batch' => $this->mapBatch($smsBatch, detailed: true),
            'items' => $items,
            'filters' => $request->only(['search', 'sort', 'direction', 'item_status']),
            'filterOptions' => [
                'itemStatuses' => collect(SmsQueueItemStatus::cases())->map(fn ($s) => [
                    'id' => $s->value,
                    'name' => ucfirst($s->value),
                ])->values()->all(),
            ],
            'can' => [
                'manage' => $user->hasPermission('sms.manage'),
                'cancel' => $user->hasPermission('sms.queue.cancel'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = SmsBatch::query();
        $this->applyBatchFilters($query, $request);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('message_body', 'like', "%{$search}%");
                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $rows = $query->orderByDesc('id')->get()->map(fn (SmsBatch $batch): array => [
            $batch->id,
            $batch->status instanceof SmsBatchStatus ? $batch->status->value : (string) $batch->status,
            $batch->source instanceof SmsBatchSource ? $batch->source->value : (string) $batch->source,
            $batch->priority,
            $batch->total,
            $batch->queued,
            $batch->sending,
            $batch->sent,
            $batch->failed,
            $batch->cancelled,
            $batch->message_body,
            $batch->created_at?->toDateTimeString(),
        ]);

        return CsvExporter::download('sms-batches.csv', [
            'ID', 'Status', 'Source', 'Priority', 'Total', 'Queued', 'Sending', 'Sent', 'Failed', 'Cancelled',
            'Message', 'Created At',
        ], $rows);
    }

    public function update(Request $request, SmsBatch $smsBatch, SmsQueueService $queue, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $batch = $queue->setPriority($smsBatch, (int) $data['priority']);
        $auditLogger->log('sms.batch.priority_updated', $batch, null, [
            'priority' => $batch->priority,
        ]);

        return back()->with('success', "Batch #{$batch->id} priority set to {$batch->priority}.");
    }

    public function updateItem(
        Request $request,
        SmsBatch $smsBatch,
        SmsQueueItem $item,
        SmsQueueService $queue,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless((int) $item->sms_batch_id === (int) $smsBatch->id, 404);

        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:64'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $updated = $queue->updateQueuedItem($item, $data);
        $auditLogger->log('sms.queue_item.updated', $updated, null, [
            'batch_id' => $smsBatch->id,
            'recipient' => $updated->recipient,
        ]);

        return back()->with('success', "Queue item #{$updated->id} updated.");
    }

    public function cancelItem(
        SmsBatch $smsBatch,
        SmsQueueItem $item,
        SmsQueueService $queue,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless((int) $item->sms_batch_id === (int) $smsBatch->id, 404);

        $result = $queue->cancelItem($item);
        $auditLogger->log('sms.queue_item.cancelled', $result['item'], null, [
            'batch_id' => $smsBatch->id,
            'activities_deleted' => $result['activities_deleted'],
        ]);

        return back()->with(
            'success',
            "Cancelled queue item #{$result['item']->id}; deleted {$result['activities_deleted']} activity(ies).",
        );
    }

    public function destroy(SmsBatch $smsBatch, SmsQueueService $queue, AuditLogger $auditLogger): RedirectResponse
    {
        $batchId = $smsBatch->id;
        $result = $queue->deleteBatch($smsBatch);
        $auditLogger->log('sms.batch.deleted', null, null, [
            'batch_id' => $batchId,
            'activities_deleted' => $result['activities_deleted'],
        ]);

        return redirect()
            ->route('sms.batches.index')
            ->with('success', "Batch #{$batchId} deleted.");
    }

    public function pause(SmsBatch $smsBatch, SmsQueueService $queue, AuditLogger $auditLogger): RedirectResponse
    {
        $batch = $queue->pauseBatch($smsBatch);
        $auditLogger->log('sms.batch.paused', $batch, null);

        return back()->with('success', "Batch #{$batch->id} paused.");
    }

    public function resume(SmsBatch $smsBatch, SmsQueueService $queue, AuditLogger $auditLogger): RedirectResponse
    {
        $batch = $queue->resumeBatch($smsBatch);
        $auditLogger->log('sms.batch.resumed', $batch, null);

        return back()->with('success', "Batch #{$batch->id} resumed.");
    }

    public function bumpPriority(Request $request, SmsBatch $smsBatch, SmsQueueService $queue, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $batch = $queue->bumpPriority($smsBatch, $data['direction']);
        $auditLogger->log('sms.batch.priority_updated', $batch, null, [
            'priority' => $batch->priority,
            'direction' => $data['direction'],
        ]);

        return back()->with('success', "Batch #{$batch->id} priority set to {$batch->priority}.");
    }

    public function cancel(SmsBatch $smsBatch, SmsQueueService $queue, AuditLogger $auditLogger): RedirectResponse
    {
        $result = $queue->cancelBatch($smsBatch);
        $batch = $result['batch'];
        $auditLogger->log('sms.batch.cancelled', $batch, null, [
            'cancelled' => $batch->cancelled,
            'activities_deleted' => $result['activities_deleted'],
        ]);

        return back()->with(
            'success',
            "Batch #{$batch->id}: cancelled {$batch->cancelled} queued item(s); deleted {$result['activities_deleted']} activity(ies).",
        );
    }

    protected function applyBatchFilters($query, Request $request): void
    {
        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        if ($source = $request->string('source')->trim()->toString()) {
            $query->where('source', $source);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapBatch(SmsBatch $batch, bool $detailed = false): array
    {
        $status = $batch->status instanceof SmsBatchStatus ? $batch->status->value : (string) $batch->status;
        $source = $batch->source instanceof SmsBatchSource ? $batch->source->value : (string) $batch->source;

        $payload = [
            'id' => $batch->id,
            'status' => $status,
            'source' => $source,
            'priority' => $batch->priority,
            'total' => $batch->total,
            'queued' => $batch->queued,
            'sending' => $batch->sending,
            'sent' => $batch->sent,
            'failed' => $batch->failed,
            'cancelled' => $batch->cancelled,
            'message_body' => $batch->message_body,
            'message_preview' => $this->preview($batch->message_body, 80),
            'created_at' => $batch->created_at?->toDateTimeString(),
            'created_by' => $batch->createdBy?->username,
            'agent' => $batch->agentProfile?->display_name
                ?: trim(($batch->agentProfile?->first_name.' '.$batch->agentProfile?->last_name) ?? ''),
            'can_pause' => $status !== 'paused' && (int) $batch->queued > 0 && ! in_array($status, ['cancelled', 'completed', 'failed'], true),
            'can_resume' => $status === 'paused',
            'can_priority' => (int) $batch->queued > 0,
            'can_cancel' => (int) $batch->queued > 0,
            'can_delete' => (int) $batch->sending === 0,
        ];

        if ($detailed) {
            $payload['metadata'] = $batch->metadata;
            $payload['account_activity_id'] = $batch->account_activity_id;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapItem(SmsQueueItem $item): array
    {
        $status = $item->status instanceof SmsQueueItemStatus ? $item->status->value : (string) $item->status;

        return [
            'id' => $item->id,
            'status' => $status,
            'recipient' => $item->recipient,
            'message' => $item->message,
            'message_preview' => $this->preview($item->message, 80),
            'reference' => $item->reference,
            'error_code' => $item->error_code,
            'error_message' => $item->error_message,
            'runtime_device_id' => $item->runtime_device_id,
            'assigned_device' => $item->assignedDevice?->name,
            'account_id' => $item->account_id,
            'account_label' => $item->account
                ? trim(($item->account->account_number ?? '').' '.($item->account->account_name ?? ''))
                : null,
            'sent_at' => $item->sent_at?->toDateTimeString(),
            'failed_at' => $item->failed_at?->toDateTimeString(),
            'created_at' => $item->created_at?->toDateTimeString(),
            'can_edit' => $status === 'queued',
            'can_cancel' => $status === 'queued',
        ];
    }

    protected function preview(?string $value, int $max): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '—';
        }

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
    }
}
