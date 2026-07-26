<?php

namespace App\Http\Controllers;

use App\Enums\SmsReceivedAssociationStatus;
use App\Models\Account;
use App\Models\SmsDevice;
use App\Models\SmsReceivedMessage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Sms\SmsReceivedService;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SmsReceivedMessageController extends Controller
{
    public function index(Request $request, SmsReceivedService $receivedService): Response
    {
        /** @var User $user */
        $user = $request->user();

        $query = SmsReceivedMessage::query()->with([
            'account:id,account_number,account_name',
            'activity:id',
        ]);
        $this->applyFilters($query, $request);

        $messages = ListingQuery::paginate(
            $query,
            $request,
            ['sender', 'message', 'device_id'],
            ['id', 'sender', 'device_id', 'received_at', 'association_status', 'created_at'],
            'received_at',
            'desc',
        );

        $messages->getCollection()->transform(
            fn (SmsReceivedMessage $message) => $receivedService->mapForUi($message)
        );

        return Inertia::render('Sms/Received/Index', [
            'messages' => $messages,
            'filters' => $request->only(['search', 'sort', 'direction', 'association_status', 'device_id']),
            'filterOptions' => [
                'associationStatuses' => collect(SmsReceivedAssociationStatus::cases())->map(fn ($s) => [
                    'id' => $s->value,
                    'name' => ucfirst($s->value),
                ])->values()->all(),
                'devices' => SmsDevice::query()
                    ->whereNotNull('runtime_device_id')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(['id', 'name', 'runtime_device_id'])
                    ->map(fn (SmsDevice $d) => [
                        'id' => $d->runtime_device_id,
                        'name' => ($d->name ?: $d->runtime_device_id).' ('.$d->runtime_device_id.')',
                    ])
                    ->values()
                    ->all(),
            ],
            'replyDevices' => $this->replyDeviceOptions(),
            'can' => [
                'export' => $user->hasPermission('sms.export'),
                'associate' => $user->hasPermission('sms.view'),
                'reply' => $user->hasPermission('sms.view'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = SmsReceivedMessage::query()->with(['account:id,account_number,account_name']);
        $this->applyFilters($query, $request);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('sender', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%")
                    ->orWhere('device_id', 'like', "%{$search}%");
            });
        }

        $rows = $query->orderByDesc('received_at')->orderByDesc('id')->get()->map(fn (SmsReceivedMessage $m): array => [
            $m->id,
            $m->sender,
            $m->message,
            $m->device_id,
            $m->received_at?->toDateTimeString(),
            $m->association_status?->value ?? $m->association_status,
            $m->account?->account_number,
            $m->account?->account_name,
            $m->account_activity_id,
            $m->created_at?->toDateTimeString(),
        ]);

        return CsvExporter::download('sms-received.csv', [
            'ID', 'Sender', 'Message', 'Device', 'Received At', 'Association',
            'Account Number', 'Account Name', 'Activity ID', 'Created At',
        ], $rows);
    }

    public function associate(
        Request $request,
        SmsReceivedMessage $smsReceivedMessage,
        SmsReceivedService $receivedService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $data = $request->validate([
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'account_contact_info_id' => ['nullable', 'integer', 'exists:account_contact_infos,id'],
        ]);

        $account = Account::query()->findOrFail($data['account_id']);
        $received = $receivedService->associateToAccount(
            $smsReceivedMessage,
            $account,
            isset($data['account_contact_info_id']) ? (int) $data['account_contact_info_id'] : null,
        );

        $auditLogger->log('sms.received.associated', $account, null, [
            'sms_received_message_id' => $received->id,
            'account_activity_id' => $received->account_activity_id,
            'association_status' => $received->association_status?->value,
        ]);

        return back()->with('success', 'Received SMS associated to account '.$account->account_number.'.');
    }

    public function ignore(
        SmsReceivedMessage $smsReceivedMessage,
        SmsReceivedService $receivedService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $received = $receivedService->ignore($smsReceivedMessage);
        $auditLogger->log('sms.received.ignored', null, null, [
            'sms_received_message_id' => $received->id,
            'sender' => $received->sender,
        ]);

        return back()->with('success', 'Received SMS ignored (hidden from dashboard unmatched list).');
    }

    public function destroy(
        SmsReceivedMessage $smsReceivedMessage,
        SmsReceivedService $receivedService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $id = $smsReceivedMessage->id;
        $sender = $smsReceivedMessage->sender;
        $receivedService->delete($smsReceivedMessage);
        $auditLogger->log('sms.received.deleted', null, null, [
            'sms_received_message_id' => $id,
            'sender' => $sender,
        ]);

        return back()->with('success', 'Received SMS deleted.');
    }

    public function reply(
        Request $request,
        SmsReceivedMessage $smsReceivedMessage,
        SmsReceivedService $receivedService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('agentProfile');

        $data = $request->validate([
            'message' => ['required', 'string', 'max:1600'],
            'runtime_device_id' => ['nullable', 'string', 'max:255'],
        ]);

        $received = $receivedService->reply(
            $smsReceivedMessage,
            $user,
            $data['message'],
            $data['runtime_device_id'] ?? null,
        );

        $auditLogger->log('sms.received.replied', $received->account, null, [
            'sms_received_message_id' => $received->id,
            'runtime_device_id' => $data['runtime_device_id'] ?? $received->device_id,
        ]);

        return back()->with('success', 'Reply queued for sending.');
    }

    public function searchAccounts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 1) {
            return response()->json(['data' => []]);
        }

        $like = '%'.$q.'%';
        $accounts = Account::query()
            ->where(function ($inner) use ($like, $q) {
                $inner->where('account_number', 'like', $like)
                    ->orWhere('account_name', 'like', $like);
                if (ctype_digit($q)) {
                    $inner->orWhere('id', (int) $q);
                }
            })
            ->orderBy('account_number')
            ->limit(20)
            ->get(['id', 'account_number', 'account_name']);

        return response()->json([
            'data' => $accounts->map(fn (Account $a) => [
                'id' => $a->id,
                'label' => trim(($a->account_number ?: '').' — '.($a->account_name ?: '')),
            ])->all(),
        ]);
    }

    protected function applyFilters($query, Request $request): void
    {
        if ($status = $request->string('association_status')->trim()->toString()) {
            $query->where('association_status', $status);
        }

        if ($deviceId = $request->string('device_id')->trim()->toString()) {
            $query->where('device_id', $deviceId);
        }
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
}
