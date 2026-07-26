<?php

namespace App\Http\Controllers;

use App\Enums\ActionCodeClassification;
use App\Enums\AgentProfileStatus;
use App\Enums\ContactInfoType;
use App\Enums\SmsBatchSource;
use App\Enums\SmsTargetMode;
use App\Models\Account;
use App\Models\AccountActivity;
use App\Models\AccountActivityFile;
use App\Models\AccountAddress;
use App\Models\AccountContactInfo;
use App\Models\ActivityType;
use App\Models\AddressType;
use App\Models\AgentProfile;
use App\Models\Campaign;
use App\Models\CampaignAssignment;
use App\Models\Entity;
use App\Models\EntityActionCode;
use App\Models\EntityStatus;
use App\Models\EntityTemplate;
use App\Models\SmsDevice;
use App\Models\SmsDeviceGroup;
use App\Models\User;
use App\Services\AccountActivityTotalsSync;
use App\Services\AuditLogger;
use App\Services\Sms\SmsQueueService;
use App\Support\AccountTemplateTokens;
use App\Support\CsvExporter;
use App\Support\FlatJsonObject;
use App\Support\ListingQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $this->listingFilters($request);

        $query = Account::query()
            ->select([
                'id',
                'campaign_id',
                'account_number',
                'account_name',
                'product',
                'entity_status_id',
                'entity_action_code_id',
                'assigned_agent_profile_id',
                'activities_count',
                'non_system_activities_count',
                'last_activity_at',
                'positive_activity_count',
                'negative_activity_count',
                'neutral_activity_count',
                'sms_out_count',
                'sms_in_count',
                'call_success_count',
                'call_failed_count',
                'call_total_count',
                'created_at',
            ])
            ->with([
                'campaign:id,name,entity_id',
                'campaign.entity:id,name',
                'entityStatus:id,name',
                'entityActionCode:id,name',
                'assignedAgentProfile:id,display_name,first_name,last_name,user_id',
                'assignedAgentProfile.user:id,avatar_path',
            ]);

        $this->applyListingFilters($query, $filters, $user);

        $accounts = ListingQuery::paginate(
            $query,
            $request,
            ['account_number', 'account_name', 'product', 'external_reference'],
            [
                'account_number',
                'account_name',
                'product',
                'created_at',
                'id',
                'non_system_activities_count',
                'positive_activity_count',
                'negative_activity_count',
                'neutral_activity_count',
                'sms_out_count',
                'sms_in_count',
                'call_success_count',
                'call_failed_count',
                'call_total_count',
            ],
        );

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'filters' => $filters,
            'filterOptions' => $this->listingFilterOptions($user, $filters),
            'can' => [
                'create' => $user->hasPermission('accounts.create'),
                'export' => $user->hasPermission('accounts.export'),
                'update' => $user->hasPermission('accounts.update'),
            ],
            'activityTypes' => ActivityType::query()
                ->where('is_active', true)
                ->whereIn('code', ActivityType::LOCKED_CODES)
                ->where('code', '!=', 'system')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'actorLabel' => $this->actorLabelForUser($user->loadMissing('agentProfile')),
            ...$this->smsTargetingProps(),
        ]);
    }

    public function create(Request $request): Response
    {
        $campaigns = $this->availableCampaigns($request);

        return Inertia::render('Accounts/Form', [
            'account' => null,
            'campaigns' => $campaigns,
            'assignableAgentsByCampaign' => $this->assignableAgentsByCampaign($campaigns),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeCampaignId($request, (int) $data['campaign_id']);

        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $account = Account::query()->create($data);

        $this->logSystemAccountActivity($account, $request->user(), 'Account created.');

        $auditLogger->log('account.created', $account, $account->campaign_id);

        return redirect()->route('accounts.index')->with('success', 'Account created.');
    }

    public function show(Account $account): Response
    {
        $account->load([
            'campaign.entity',
            'entityStatus',
            'entityActionCode',
            'assignedAgentProfile.user',
            'lastActivityAgentProfile.user',
            'lastActivityUser',
            'lastReferenceContactInfo',
            'lastReferenceAddress',
            'contactInfos',
            'addresses',
        ]);

        $activities = $account->activities()
            ->with([
                'activityType',
                'actorUser',
                'agentProfile.user',
                'entityStatus',
                'entityActionCode',
                'entityTemplate',
                'referenceContactInfo',
                'referenceAddress',
                'files',
            ])
            ->paginate(10, ['*'], 'activities_page')
            ->withQueryString();

        $entityId = $account->campaign?->entity_id;

        /** @var User $user */
        $user = request()->user();
        $user->loadMissing('agentProfile');

        $addressTypes = AddressType::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_default']);

        $contactStats = $this->contactActivityStats($account);
        $account->setRelation(
            'contactInfos',
            $account->contactInfos->map(function (AccountContactInfo $contact) use ($contactStats) {
                $stats = $contactStats[$contact->id] ?? ['success' => 0, 'failed' => 0, 'total' => 0];
                $contact->setAttribute('success_count', $stats['success']);
                $contact->setAttribute('failed_count', $stats['failed']);
                $contact->setAttribute('total_count', $stats['total']);

                return $contact;
            }),
        );

        $this->hydrateLastReferenceRelations($account);

        return Inertia::render('Accounts/Show', [
            'account' => $account,
            'activities' => $activities,
            'accountStats' => $this->accountStats($account),
            'statusExtras' => $this->accountStatusExtras($account),
            'contactTypes' => collect(ContactInfoType::cases())->map(fn (ContactInfoType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values(),
            'addressTypes' => $addressTypes,
            'activityTypes' => ActivityType::query()
                ->where('is_active', true)
                ->whereIn('code', ActivityType::LOCKED_CODES)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'entityStatuses' => $entityId
                ? EntityStatus::query()
                    ->where('entity_id', $entityId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'code', 'color', 'text_color'])
                : collect(),
            'entityActionCodes' => $entityId
                ? EntityActionCode::query()
                    ->where('entity_id', $entityId)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id', 'name', 'code'])
                : collect(),
            'entityTemplates' => $entityId
                ? EntityTemplate::query()
                    ->where('entity_id', $entityId)
                    ->where('is_active', true)
                    ->orderBy('slug')
                    ->get(['id', 'slug', 'types', 'body'])
                : collect(),
            'actorLabel' => $this->actorLabelForUser($user),
            'can' => [
                'update' => $user->hasPermission('accounts.update'),
                'delete' => $user->hasPermission('accounts.delete'),
            ],
            ...$this->smsTargetingProps(),
        ]);
    }

    public function edit(Request $request, Account $account): Response
    {
        $campaigns = $this->availableCampaigns($request);

        return Inertia::render('Accounts/Form', [
            'account' => $account->load(['campaign.entity', 'assignedAgentProfile']),
            'campaigns' => $campaigns,
            'assignableAgentsByCampaign' => $this->assignableAgentsByCampaign($campaigns),
        ]);
    }

    public function update(Request $request, Account $account, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request, $account);
        $this->authorizeCampaignId($request, (int) $data['campaign_id']);

        $data['updated_by'] = $request->user()->id;

        $account->update($data);

        $auditLogger->log('account.updated', $account, $account->campaign_id);

        return redirect()->route('accounts.index')->with('success', 'Account updated.');
    }

    public function destroy(Account $account, AuditLogger $auditLogger): RedirectResponse
    {
        $campaignId = $account->campaign_id;
        $account->delete();

        $auditLogger->log('account.deleted', $account, $campaignId);

        return redirect()->route('accounts.index')->with('success', 'Account deleted.');
    }

    public function updateCustomFields(Request $request, Account $account, AuditLogger $auditLogger): RedirectResponse
    {
        $payload = $request->input('custom_fields');

        if ($request->hasFile('file')) {
            $file = $request->validate([
                'file' => ['required', 'file', 'max:64', 'mimetypes:application/json,text/plain,text/json'],
            ])['file'];
            $payload = $file->get();
        }

        $fields = FlatJsonObject::parse($payload);

        $account->update([
            'custom_fields' => $fields,
            'updated_by' => $request->user()->id,
        ]);

        $auditLogger->log('account.custom_fields.updated', $account, $account->campaign_id);

        return back()->with('success', 'Account fields saved.');
    }

    public function updateStatus(Request $request, Account $account, AuditLogger $auditLogger): RedirectResponse
    {
        $entityId = $account->campaign?->entity_id;

        $data = $request->validate([
            'entity_status_id' => ['nullable', 'exists:entity_statuses,id'],
            'entity_action_code_id' => ['nullable', 'exists:entity_action_codes,id'],
            'last_reference_amount' => ['nullable', 'numeric'],
            'last_reference_date' => ['nullable', 'date'],
            'last_reference_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'last_reference_text' => ['nullable', 'string', 'max:5000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'activity_type_id' => ['nullable', 'exists:activity_types,id'],
        ]);

        $this->assertEntityCatalogIds($entityId, $data['entity_status_id'] ?? null, $data['entity_action_code_id'] ?? null);

        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('agentProfile');

        $typeId = $data['activity_type_id']
            ?? ActivityType::query()->where('code', 'others')->where('is_active', true)->value('id')
            ?? ActivityType::query()
                ->where('is_active', true)
                ->whereIn('code', ActivityType::LOCKED_CODES)
                ->orderBy('sort_order')
                ->value('id');

        abort_unless($typeId, 422, 'No activity type available.');
        abort_unless(
            ActivityType::query()
                ->whereKey($typeId)
                ->where('is_active', true)
                ->whereIn('code', ActivityType::LOCKED_CODES)
                ->exists(),
            422,
        );

        $actionCodeId = $data['entity_action_code_id'] ?? null;

        $activity = $account->activities()->create([
            'occurred_at' => now(),
            'activity_type_id' => $typeId,
            'actor_user_id' => $user->id,
            'agent_profile_id' => $user->agentProfile?->id,
            'assigned_agent_profile_id' => $account->assigned_agent_profile_id,
            'entity_status_id' => $data['entity_status_id'] ?? null,
            'entity_action_code_id' => $actionCodeId,
            'classification' => $this->resolveActivityClassification($actionCodeId),
            'reference_amount' => $data['last_reference_amount'] ?? null,
            'reference_date' => $data['last_reference_date'] ?? null,
            'reference_time' => $data['last_reference_time'] ?? null,
            'reference_text' => $data['last_reference_text'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        $this->syncAccountLastFromActivity($account, $activity, $user);

        $auditLogger->log('account.status.updated', $activity, $account->campaign_id);

        return back()->with('success', 'Account status updated.');
    }

    public function storeContactInfo(Request $request, Account $account, AuditLogger $auditLogger): RedirectResponse
    {
        $type = ContactInfoType::tryFrom((string) $request->input('type'));

        $rules = [
            'type' => ['required', Rule::enum(ContactInfoType::class)],
            'is_primary' => ['boolean'],
            'name' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'value' => ['nullable', 'string', 'max:255'],
        ];

        $isPrimary = $request->boolean('is_primary');

        if (! $isPrimary) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['relationship'] = ['required', 'string', 'max:100'];
        }

        if ($type === ContactInfoType::Email) {
            $rules['value'] = ['required', 'email', 'max:255'];
        } elseif ($type === ContactInfoType::Mobile) {
            $rules['value'] = [
                'required',
                'string',
                'max:20',
                'regex:/^\+?[0-9\s\-()]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $digits = preg_replace('/\D+/', '', (string) $value);
                    if (strlen((string) $digits) < 10) {
                        $fail('The mobile number must contain at least 10 digits.');
                    }
                },
            ];
        } elseif ($type?->isSocial()) {
            $rules['value'] = ['required', 'url', 'max:255'];
        } else {
            $rules['value'] = ['required', 'string', 'max:255'];
        }

        $data = $request->validate($rules);
        $data['is_primary'] = $isPrimary;

        if ($isPrimary) {
            $data['name'] = null;
            $data['relationship'] = null;
        }

        if ($data['is_primary']) {
            $account->contactInfos()->update(['is_primary' => false]);
        }

        $contact = $account->contactInfos()->create([
            'type' => $data['type'],
            'name' => $data['name'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'value' => $data['value'] ?? null,
            'is_primary' => $data['is_primary'],
        ]);

        $auditLogger->log('account.contact_info.created', $contact, $account->campaign_id);

        return back()->with('success', 'Contact info added.');
    }

    public function destroyContactInfo(Account $account, AccountContactInfo $contactInfo, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($contactInfo->account_id === $account->id, 404);

        $contactInfo->delete();

        $auditLogger->log('account.contact_info.deleted', $contactInfo, $account->campaign_id);

        return back()->with('success', 'Contact info removed.');
    }

    public function storeAddress(Request $request, Account $account, AuditLogger $auditLogger): RedirectResponse
    {
        $isPrimary = $request->boolean('is_primary', true);

        $rules = [
            'type' => [
                'required',
                'string',
                'max:100',
                Rule::exists('address_types', 'code')->where(fn ($query) => $query->where('is_active', true)->whereNull('deleted_at')),
            ],
            'name' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'is_primary' => ['boolean'],
        ];

        if (! $isPrimary) {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['relationship'] = ['required', 'string', 'max:100'];
        }

        $data = $request->validate($rules);
        $data['is_primary'] = $isPrimary;

        if ($isPrimary) {
            $data['name'] = null;
            $data['relationship'] = null;
            $account->addresses()->update(['is_primary' => false]);
        }

        $address = $account->addresses()->create($data);

        $auditLogger->log('account.address.created', $address, $account->campaign_id);

        return back()->with('success', 'Address added.');
    }

    public function destroyAddress(Account $account, AccountAddress $address, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($address->account_id === $account->id, 404);

        $address->delete();

        $auditLogger->log('account.address.deleted', $address, $account->campaign_id);

        return back()->with('success', 'Address removed.');
    }

    public function storeActivity(Request $request, Account $account, AuditLogger $auditLogger, SmsQueueService $smsQueue): RedirectResponse
    {
        $entityId = $account->campaign?->entity_id;

        $data = $request->validate([
            'occurred_at' => ['required', 'date'],
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'entity_status_id' => ['required', 'exists:entity_statuses,id'],
            'entity_action_code_id' => ['nullable', 'exists:entity_action_codes,id'],
            'entity_template_id' => ['nullable', 'exists:entity_templates,id'],
            'reference_amount' => ['nullable', 'numeric'],
            'reference_date' => ['nullable', 'date'],
            'reference_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'reference_text' => ['nullable', 'string', 'max:5000'],
            'reference_contact_info_id' => [
                'nullable',
                Rule::exists('account_contact_infos', 'id')->where(fn ($query) => $query->where('account_id', $account->id)->whereNull('deleted_at')),
            ],
            'reference_address_id' => [
                'nullable',
                Rule::exists('account_addresses', 'id')->where(fn ($query) => $query->where('account_id', $account->id)->whereNull('deleted_at')),
            ],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'queue_for_sms' => ['sometimes', 'boolean'],
            'sms_target_mode' => ['nullable', Rule::in(SmsTargetMode::values())],
            'sms_device_group_id' => ['nullable', 'integer', 'exists:sms_device_groups,id'],
            'sms_device_id' => ['nullable', 'integer', 'exists:sms_devices,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:5120'],
        ]);

        $activityType = ActivityType::query()
            ->whereKey($data['activity_type_id'])
            ->where('is_active', true)
            ->whereIn('code', ActivityType::LOCKED_CODES)
            ->first();

        abort_unless($activityType, 422);

        $queueForSms = $request->boolean('queue_for_sms') && $activityType->code === 'sms_send';
        $smsTargeting = null;

        if ($queueForSms) {
            if (empty($data['reference_contact_info_id'])) {
                throw ValidationException::withMessages([
                    'reference_contact_info_id' => 'Select a mobile or landline contact to queue SMS.',
                ]);
            }
            $contact = AccountContactInfo::query()
                ->whereKey($data['reference_contact_info_id'])
                ->where('account_id', $account->id)
                ->first();
            $recipient = $smsQueue->resolveRecipientFromContact($contact);
            if (! $recipient) {
                throw ValidationException::withMessages([
                    'reference_contact_info_id' => 'Reference contact must be a mobile or landline with a phone number.',
                ]);
            }
            if (trim((string) ($data['reference_text'] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'reference_text' => 'Message text is required when queueing SMS.',
                ]);
            }
            $smsTargeting = $this->validatedSmsTargeting($data);
        }

        $this->assertEntityCatalogIds($entityId, $data['entity_status_id'], $data['entity_action_code_id'] ?? null);

        $templateId = $this->resolveActivityTemplateId(
            $entityId,
            $activityType->code,
            $data['entity_template_id'] ?? null,
        );

        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('agentProfile');

        $actionCodeId = $data['entity_action_code_id'] ?? null;

        $activity = $account->activities()->create([
            'occurred_at' => $data['occurred_at'],
            'activity_type_id' => $data['activity_type_id'],
            'actor_user_id' => $user->id,
            'agent_profile_id' => $user->agentProfile?->id,
            'assigned_agent_profile_id' => $account->assigned_agent_profile_id,
            'entity_status_id' => $data['entity_status_id'] ?? null,
            'entity_action_code_id' => $actionCodeId,
            'entity_template_id' => $templateId,
            'classification' => $this->resolveActivityClassification($actionCodeId),
            'reference_amount' => $data['reference_amount'] ?? null,
            'reference_date' => $data['reference_date'] ?? null,
            'reference_time' => $data['reference_time'] ?? null,
            'reference_text' => $data['reference_text'] ?? null,
            'reference_contact_info_id' => $data['reference_contact_info_id'] ?? null,
            'reference_address_id' => $data['reference_address_id'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ]);

        foreach ($request->file('attachments', []) as $uploaded) {
            $path = $uploaded->store("account-activities/{$activity->id}", 'local');
            $activity->files()->create([
                'original_name' => $uploaded->getClientOriginalName(),
                'path' => $path,
                'disk' => 'local',
                'mime' => $uploaded->getClientMimeType(),
                'size' => $uploaded->getSize() ?: 0,
                'uploaded_by' => $user->id,
            ]);
        }

        $this->syncAccountLastFromActivity($account, $activity, $user);

        $auditLogger->log('account.activity.created', $activity, $account->campaign_id);

        $success = 'Activity added.';

        if ($queueForSms) {
            $contact = AccountContactInfo::query()
                ->whereKey($data['reference_contact_info_id'])
                ->where('account_id', $account->id)
                ->first();
            $recipient = $smsQueue->resolveRecipientFromContact($contact);
            $message = (string) ($data['reference_text'] ?? '');

            $batch = $smsQueue->enqueueBatch(
                SmsBatchSource::AccountActivitySingle,
                $user,
                collect([[
                    'account' => $account,
                    'activity' => $activity,
                    'message' => $message,
                    'recipient' => $recipient,
                ]]),
                $message,
                ['account_id' => $account->id],
                $activity,
                $smsTargeting,
            );

            $auditLogger->log('sms.batch.enqueued', $batch, $account->campaign_id, [
                'source' => 'account_activity_single',
                'total' => $batch->total,
            ]);

            $success = "Activity added. Queued {$batch->queued} SMS (batch #{$batch->id}).";
        }

        return back()->with('success', $success);
    }

    public function downloadActivityFile(
        Account $account,
        AccountActivity $accountActivity,
        AccountActivityFile $file,
    ): StreamedResponse {
        abort_unless($accountActivity->account_id === $account->id, 404);
        abort_unless($file->account_activity_id === $accountActivity->id, 404);

        return Storage::disk($file->disk)->download($file->path, $file->original_name);
    }

    public function destroyActivity(Account $account, AccountActivity $accountActivity, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($accountActivity->account_id === $account->id, 404);

        $accountActivity->files()->delete();
        $accountActivity->delete();

        app(AccountActivityTotalsSync::class)->sync($account);

        $auditLogger->log('account.activity.deleted', $accountActivity, $account->campaign_id);

        return back()->with('success', 'Activity removed.');
    }

    public function bulkOptions(Request $request): JsonResponse
    {
        try {
            $query = $this->bulkTargetQuery($request);
        } catch (ValidationException $e) {
            return response()->json([
                'count' => 0,
                'entity' => null,
                'campaign_ids' => [],
                'campaigns' => [],
                'agents_by_campaign' => [],
                'statuses' => [],
                'actions' => [],
                'templates' => [],
                'actor_label' => null,
                'error' => collect($e->errors())->flatten()->first(),
            ]);
        }

        $count = (clone $query)->count();

        if ($count === 0) {
            return response()->json([
                'count' => 0,
                'entity' => null,
                'campaign_ids' => [],
                'campaigns' => [],
                'agents_by_campaign' => [],
                'statuses' => [],
                'actions' => [],
                'templates' => [],
                'actor_label' => null,
                'error' => 'No accounts match the selection.',
            ]);
        }

        try {
            $entityId = $this->assertSingleEntityId($query);
        } catch (ValidationException $e) {
            return response()->json([
                'count' => $count,
                'entity' => null,
                'campaign_ids' => [],
                'campaigns' => [],
                'agents_by_campaign' => [],
                'statuses' => [],
                'actions' => [],
                'templates' => [],
                'actor_label' => null,
                'error' => collect($e->errors())->flatten()->first(),
            ]);
        }

        $campaignIds = (clone $query)->distinct()->orderBy('campaign_id')->pluck('campaign_id');
        $entity = Entity::query()->whereKey($entityId)->first(['id', 'name']);

        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('agentProfile');
        $campaignsQuery = Campaign::query()
            ->where('entity_id', $entityId)
            ->orderBy('name');

        if (! $user->isSuperAdmin()) {
            $allowed = $user->allowedCampaignIds();
            $campaignsQuery->whereIn('id', $allowed === [] ? [-1] : $allowed);
        }

        $campaigns = $campaignsQuery->get(['id', 'name', 'entity_id']);
        $agentsByCampaign = $this->assignableAgentsByCampaign($campaigns);

        $statuses = EntityStatus::query()
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'entity_id']);

        $actions = EntityActionCode::query()
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'entity_id']);

        $templates = EntityTemplate::query()
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->orderBy('slug')
            ->get(['id', 'slug', 'types', 'body']);

        return response()->json([
            'count' => $count,
            'entity' => $entity,
            'campaign_ids' => $campaignIds->values()->all(),
            'campaigns' => $campaigns,
            'agents_by_campaign' => $agentsByCampaign,
            'statuses' => $statuses,
            'actions' => $actions,
            'templates' => $templates,
            'actor_label' => $this->actorLabelForUser($user),
            'error' => null,
        ]);
    }

    public function bulkAssignAssignment(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['selected', 'all'])],
            'account_ids' => ['required_if:scope,selected', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
            'campaign_id' => ['required', 'integer', 'exists:campaigns,id'],
            'entity_status_id' => ['nullable', 'integer', 'exists:entity_statuses,id'],
            'assigned_agent_profile_id' => ['nullable', 'integer', 'exists:agent_profiles,id'],
            'remarks' => ['required', 'string', 'max:5000'],
        ]);

        $this->authorizeCampaignId($request, (int) $data['campaign_id']);

        $targetCampaign = Campaign::query()->findOrFail((int) $data['campaign_id']);
        $query = $this->bulkTargetQuery($request);
        $entityId = $this->assertSingleEntityId($query);

        if ((int) $targetCampaign->entity_id !== $entityId) {
            $this->failBulk('Target campaign must belong to the same Entity as the selected accounts.');
        }

        if ($this->bulkHasDuplicateAccountNumbers($query)) {
            $this->failBulk('Cannot reassign: the selection contains duplicate account numbers.');
        }

        if ($this->bulkHasAccountNumberConflicts($query, (int) $targetCampaign->id)) {
            $this->failBulk('Cannot reassign: one or more account numbers already exist on the target campaign.');
        }

        $statusId = isset($data['entity_status_id']) ? (int) $data['entity_status_id'] : null;
        $agentId = isset($data['assigned_agent_profile_id']) ? (int) $data['assigned_agent_profile_id'] : null;

        if ($statusId) {
            $this->assertEntityCatalogIds($entityId, $statusId, null);
        }

        if ($agentId) {
            $assigned = CampaignAssignment::query()
                ->where('campaign_id', $targetCampaign->id)
                ->where('agent_profile_id', $agentId)
                ->exists();

            if (! $assigned) {
                $this->failBulk('Selected agent must be assigned to the target campaign.');
            }
        }

        $systemTypeId = ActivityType::query()->where('code', 'system')->where('is_active', true)->value('id');
        if (! $systemTypeId) {
            $this->failBulk('System activity type is not available.');
        }

        $targetCampaignName = $targetCampaign->name;
        $targetStatusName = $statusId
            ? (string) EntityStatus::query()->whereKey($statusId)->value('name')
            : null;
        $targetAgentName = $agentId
            ? $this->agentProfileLabel(AgentProfile::query()->find($agentId))
            : null;

        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('agentProfile');
        $filters = $this->resolveListingFilters($request);
        $count = 0;
        $accountIds = [];

        DB::transaction(function () use (
            $query,
            $targetCampaign,
            $targetCampaignName,
            $statusId,
            $targetStatusName,
            $agentId,
            $targetAgentName,
            $systemTypeId,
            $data,
            $user,
            $auditLogger,
            &$count,
            &$accountIds,
        ): void {
            (clone $query)
                ->orderBy('id')
                ->with([
                    'campaign:id,name,entity_id',
                    'entityStatus:id,name',
                    'assignedAgentProfile:id,display_name,first_name,last_name',
                ])
                ->chunkById(100, function (Collection $accounts) use (
                    $targetCampaign,
                    $targetCampaignName,
                    $statusId,
                    $targetStatusName,
                    $agentId,
                    $targetAgentName,
                    $systemTypeId,
                    $data,
                    $user,
                    $auditLogger,
                    &$count,
                    &$accountIds,
                ): void {
                    foreach ($accounts as $account) {
                        /** @var Account $account */
                        $fromCampaignId = (int) $account->campaign_id;
                        $fromCampaignName = $account->campaign?->name ?? "Campaign #{$fromCampaignId}";
                        $fromStatusId = $account->entity_status_id;
                        $fromStatusName = $account->entityStatus?->name;
                        $fromAgentId = $account->assigned_agent_profile_id;
                        $fromAgentName = $this->agentProfileLabel($account->assignedAgentProfile) ?: null;

                        $toAgentId = $agentId;
                        if ($toAgentId === null) {
                            $agentStillValid = $fromAgentId && CampaignAssignment::query()
                                ->where('campaign_id', $targetCampaign->id)
                                ->where('agent_profile_id', $fromAgentId)
                                ->exists();
                            $toAgentId = $agentStillValid ? (int) $fromAgentId : null;
                        }

                        $toStatusId = $statusId ?? $fromStatusId;

                        $account->update([
                            'campaign_id' => $targetCampaign->id,
                            'assigned_agent_profile_id' => $toAgentId,
                            'entity_status_id' => $toStatusId,
                            'updated_by' => $user->id,
                        ]);

                        $changeLines = [];
                        if ($fromCampaignId !== (int) $targetCampaign->id) {
                            $changeLines[] = "Changed campaign to {$targetCampaignName}.";
                        }
                        if ($statusId && (int) $fromStatusId !== $statusId) {
                            $changeLines[] = 'Changed status to '.($targetStatusName ?: "Status #{$statusId}").'.';
                        }
                        if ((int) ($fromAgentId ?? 0) !== (int) ($toAgentId ?? 0)) {
                            $toAgentLabel = $toAgentId
                                ? ($targetAgentName ?: $this->agentProfileLabel(AgentProfile::query()->find($toAgentId)) ?: "Agent #{$toAgentId}")
                                : 'Unassigned';
                            $changeLines[] = "Changed assignment to {$toAgentLabel}.";
                        }

                        $remarks = trim($data['remarks']);
                        if ($changeLines !== []) {
                            $remarks .= ($remarks !== '' ? "\n\n" : '').implode("\n", $changeLines);
                        }

                        $activity = $account->activities()->create([
                            'occurred_at' => now(),
                            'activity_type_id' => $systemTypeId,
                            'actor_user_id' => $user->id,
                            'agent_profile_id' => $user->agentProfile?->id,
                            'assigned_agent_profile_id' => $toAgentId,
                            'entity_status_id' => $toStatusId,
                            'entity_action_code_id' => $account->entity_action_code_id,
                            'classification' => ActionCodeClassification::Neutral,
                            'remarks' => $remarks,
                        ]);

                        $this->syncAccountLastFromActivity($account, $activity, $user);

                        $auditLogger->log('account.bulk.assignment_updated', $activity, $account->campaign_id, [
                            'bulk' => true,
                            'account_id' => $account->id,
                            'from_campaign_id' => $fromCampaignId,
                            'to_campaign_id' => (int) $targetCampaign->id,
                            'from_entity_status_id' => $fromStatusId,
                            'to_entity_status_id' => $toStatusId,
                            'from_assigned_agent_profile_id' => $fromAgentId,
                            'to_assigned_agent_profile_id' => $toAgentId,
                            'from_campaign_name' => $fromCampaignName,
                            'from_status_name' => $fromStatusName,
                            'from_agent_name' => $fromAgentName,
                            'remarks' => $data['remarks'],
                        ]);

                        $count++;
                        $accountIds[] = $account->id;
                    }
                });
        });

        $auditLogger->log('accounts.bulk.assignment_updated', null, (int) $targetCampaign->id, [
            'scope' => $data['scope'],
            'count' => $count,
            'campaign_id' => (int) $targetCampaign->id,
            'entity_status_id' => $statusId,
            'assigned_agent_profile_id' => $agentId,
            'entity_id' => $entityId,
            'remarks' => $data['remarks'],
            'filters' => $filters,
            'account_ids' => $data['scope'] === 'selected' ? ($data['account_ids'] ?? []) : $accountIds,
        ]);

        return redirect()->route('accounts.index', array_filter($filters, fn ($value) => $value !== null && $value !== ''))
            ->with('success', "Updated assignment on {$count} account(s).");
    }

    public function bulkStoreActivity(Request $request, AuditLogger $auditLogger, SmsQueueService $smsQueue): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['selected', 'all'])],
            'account_ids' => ['required_if:scope,selected', 'array'],
            'account_ids.*' => ['integer', 'exists:accounts,id'],
            'occurred_at' => ['required', 'date'],
            'activity_type_id' => ['required', 'integer', 'exists:activity_types,id'],
            'entity_status_id' => ['nullable', 'integer', 'exists:entity_statuses,id'],
            'entity_action_code_id' => ['nullable', 'integer', 'exists:entity_action_codes,id'],
            'entity_template_id' => ['nullable', 'integer', 'exists:entity_templates,id'],
            'reference_amount' => ['nullable', 'numeric'],
            'reference_date' => ['nullable', 'date'],
            'reference_time' => ['nullable', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'reference_text' => ['nullable', 'string', 'max:5000'],
            'remarks' => ['required', 'string', 'max:5000'],
            'queue_for_sms' => ['sometimes', 'boolean'],
            'sms_recipient_scope' => ['nullable', Rule::in(['primary_mobile', 'all_mobiles'])],
            'sms_target_mode' => ['nullable', Rule::in(SmsTargetMode::values())],
            'sms_device_group_id' => ['nullable', 'integer', 'exists:sms_device_groups,id'],
            'sms_device_id' => ['nullable', 'integer', 'exists:sms_devices,id'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:5120'],
        ]);

        $activityType = ActivityType::query()
            ->whereKey($data['activity_type_id'])
            ->where('is_active', true)
            ->whereIn('code', ActivityType::LOCKED_CODES)
            ->where('code', '!=', 'system')
            ->first();

        abort_unless($activityType, 422, 'Invalid activity type.');

        $queueForSms = $request->boolean('queue_for_sms') && $activityType->code === 'sms_send';
        $smsRecipientScope = $queueForSms
            ? ($data['sms_recipient_scope'] ?? 'primary_mobile')
            : null;
        $smsTargeting = $queueForSms ? $this->validatedSmsTargeting($data) : null;

        $query = $this->bulkTargetQuery($request);
        $entityId = $this->assertSingleEntityId($query);
        $this->assertEntityCatalogIds(
            $entityId,
            $data['entity_status_id'] ?? null,
            $data['entity_action_code_id'] ?? null,
        );

        $templateId = $this->resolveActivityTemplateId(
            $entityId,
            $activityType->code,
            $data['entity_template_id'] ?? null,
        );

        $template = $templateId
            ? EntityTemplate::query()->whereKey($templateId)->first()
            : null;

        if ($queueForSms && trim((string) ($data['reference_text'] ?? '')) === '' && ! $template) {
            throw ValidationException::withMessages([
                'reference_text' => 'Message text (or a template) is required when queueing SMS.',
            ]);
        }

        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('agentProfile');
        $filters = $this->resolveListingFilters($request);
        $attachmentBlueprints = collect($request->file('attachments', []))->map(function ($uploaded) {
            $tempPath = $uploaded->store('account-activities/_bulk-tmp', 'local');

            return [
                'temp_path' => $tempPath,
                'original_name' => $uploaded->getClientOriginalName(),
                'mime' => $uploaded->getClientMimeType(),
                'size' => $uploaded->getSize() ?: 0,
            ];
        })->all();

        $count = 0;
        $accountIds = [];
        $smsEntries = collect();
        $skippedNoMobile = 0;

        try {
            DB::transaction(function () use (
                $query,
                $data,
                $user,
                $auditLogger,
                $templateId,
                $template,
                $attachmentBlueprints,
                $queueForSms,
                $smsRecipientScope,
                $smsQueue,
                &$count,
                &$accountIds,
                &$smsEntries,
                &$skippedNoMobile,
            ): void {
                (clone $query)
                    ->with([
                        'campaign.entity',
                        'assignedAgentProfile',
                        'entityStatus',
                        'entityActionCode',
                        'contactInfos',
                    ])
                    ->orderBy('id')
                    ->chunkById(100, function (Collection $accounts) use (
                        $data,
                        $user,
                        $auditLogger,
                        $templateId,
                        $template,
                        $attachmentBlueprints,
                        $queueForSms,
                        $smsRecipientScope,
                        $smsQueue,
                        &$count,
                        &$accountIds,
                        &$smsEntries,
                        &$skippedNoMobile,
                    ): void {
                        foreach ($accounts as $account) {
                            /** @var Account $account */
                            $statusId = $data['entity_status_id'] ?? $account->entity_status_id;
                            $actionCodeId = $data['entity_action_code_id'] ?? $account->entity_action_code_id;

                            if (! $statusId) {
                                $this->failBulk('One or more accounts have no status to inherit. Select a status or set status on those accounts first.');
                            }

                            $referenceText = $data['reference_text'] ?? null;
                            if ($template) {
                                $tokens = AccountTemplateTokens::build(
                                    $account,
                                    AccountTemplateTokens::agentLabel($account->assignedAgentProfile),
                                );
                                $referenceText = AccountTemplateTokens::resolve($template->body, $tokens);
                            }

                            $activity = $account->activities()->create([
                                'occurred_at' => $data['occurred_at'],
                                'activity_type_id' => $data['activity_type_id'],
                                'actor_user_id' => $user->id,
                                'agent_profile_id' => $user->agentProfile?->id,
                                'assigned_agent_profile_id' => $account->assigned_agent_profile_id,
                                'entity_status_id' => $statusId,
                                'entity_action_code_id' => $actionCodeId,
                                'entity_template_id' => $templateId,
                                'classification' => $this->resolveActivityClassification($actionCodeId),
                                'reference_amount' => $data['reference_amount'] ?? null,
                                'reference_date' => $data['reference_date'] ?? null,
                                'reference_time' => $data['reference_time'] ?? null,
                                'reference_text' => $referenceText,
                                'remarks' => $data['remarks'],
                            ]);

                            foreach ($attachmentBlueprints as $blueprint) {
                                $dest = "account-activities/{$activity->id}/".basename($blueprint['temp_path']);
                                Storage::disk('local')->copy($blueprint['temp_path'], $dest);
                                $activity->files()->create([
                                    'original_name' => $blueprint['original_name'],
                                    'path' => $dest,
                                    'disk' => 'local',
                                    'mime' => $blueprint['mime'],
                                    'size' => $blueprint['size'],
                                    'uploaded_by' => $user->id,
                                ]);
                            }

                            $this->syncAccountLastFromActivity($account, $activity, $user);

                            $auditLogger->log('account.bulk.activity_created', $activity, $account->campaign_id, [
                                'bulk' => true,
                                'account_id' => $account->id,
                                'activity_type_id' => $data['activity_type_id'],
                                'entity_status_id' => $statusId,
                                'entity_action_code_id' => $actionCodeId,
                                'entity_template_id' => $templateId,
                                'remarks' => $data['remarks'],
                            ]);

                            if ($queueForSms) {
                                $recipients = $smsQueue->resolveBulkMobileRecipients(
                                    $account,
                                    $smsRecipientScope ?? 'primary_mobile',
                                );

                                if ($recipients->isEmpty()) {
                                    $skippedNoMobile++;
                                } else {
                                    foreach ($recipients as $recipient) {
                                        $smsEntries->push([
                                            'account' => $account,
                                            'activity' => $activity,
                                            'message' => (string) $referenceText,
                                            'recipient' => $recipient,
                                        ]);
                                    }
                                }
                            }

                            $count++;
                            $accountIds[] = $account->id;
                        }
                    });
            });
        } finally {
            foreach ($attachmentBlueprints as $blueprint) {
                Storage::disk('local')->delete($blueprint['temp_path']);
            }
        }

        $auditLogger->log('accounts.bulk.activity_created', null, null, [
            'scope' => $data['scope'],
            'count' => $count,
            'activity_type_id' => $data['activity_type_id'],
            'entity_status_id' => $data['entity_status_id'],
            'entity_action_code_id' => $data['entity_action_code_id'] ?? null,
            'entity_template_id' => $templateId,
            'remarks' => $data['remarks'],
            'filters' => $filters,
            'account_ids' => $data['scope'] === 'selected' ? ($data['account_ids'] ?? []) : $accountIds,
            'queue_for_sms' => $queueForSms,
            'sms_recipient_scope' => $smsRecipientScope,
            'skipped_no_mobile' => $skippedNoMobile,
        ]);

        $success = "Added activity on {$count} account(s).";

        if ($queueForSms && $smsEntries->isNotEmpty()) {
            $batch = $smsQueue->enqueueBatch(
                SmsBatchSource::AccountActivityBulk,
                $user,
                $smsEntries,
                (string) ($data['reference_text'] ?? ''),
                [
                    'scope' => $data['scope'],
                    'filters' => $filters,
                    'account_ids' => $data['scope'] === 'selected' ? ($data['account_ids'] ?? []) : $accountIds,
                    'sms_recipient_scope' => $smsRecipientScope,
                    'skipped_no_mobile' => $skippedNoMobile,
                ],
                null,
                $smsTargeting,
            );

            $auditLogger->log('sms.batch.enqueued', $batch, null, [
                'source' => 'account_activity_bulk',
                'total' => $batch->total,
                'queued' => $batch->queued,
                'failed' => $batch->failed,
                'sms_recipient_scope' => $smsRecipientScope,
            ]);

            $success .= " Queued {$batch->queued} SMS (batch #{$batch->id}).";
        }

        if ($queueForSms && $skippedNoMobile > 0) {
            $success .= " Skipped {$skippedNoMobile} account(s) with no valid mobile.";
        }

        return redirect()->route('accounts.index', array_filter($filters, fn ($value) => $value !== null && $value !== ''))
            ->with('success', $success);
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        $query = $this->filteredAccountsQuery($request)->with([
            'campaign.entity',
            'entityStatus',
            'entityActionCode',
            'assignedAgentProfile',
        ]);

        $rows = $query->orderBy('id')->get()->map(function (Account $account): array {
            $lastAt = $account->last_activity_at;

            return [
                $account->account_number,
                $account->account_name,
                $account->campaign?->entity?->name,
                $account->campaign?->name,
                $this->agentProfileLabel($account->assignedAgentProfile),
                $account->product,
                $account->non_system_activities_count,
                $account->positive_activity_count,
                $account->negative_activity_count,
                $account->neutral_activity_count,
                $account->sms_out_count,
                $account->sms_in_count,
                $account->call_success_count,
                $account->call_failed_count,
                $account->call_total_count,
                $lastAt ? Carbon::parse($lastAt)->toDateString() : '',
                $account->entityStatus?->name,
                $account->entityActionCode?->name,
            ];
        });

        $auditLogger->log('accounts.exported');

        return CsvExporter::download('accounts.csv', [
            'Account #',
            'Account name',
            'Entity',
            'Campaign',
            'Assigned agent',
            'Product',
            'Activities',
            '+Pos',
            '-Neg',
            '~Neutral',
            'SMS out',
            'SMS in',
            'Call success',
            'Call failed',
            'Call total',
            'Last activity',
            'Status',
            'Action',
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Account $account = null): array
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'exists:campaigns,id'],
            'account_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('accounts', 'account_number')
                    ->where(fn ($query) => $query->where('campaign_id', $request->input('campaign_id')))
                    ->ignore($account?->id),
            ],
            'account_name' => ['required', 'string', 'max:255'],
            'date_acquired' => ['nullable', 'date'],
            'assigned_agent_profile_id' => ['nullable', 'exists:agent_profiles,id'],
            'notes' => ['nullable', 'string'],
        ]);

        if (blank($data['account_number'] ?? null)) {
            if ($account === null) {
                $data['account_number'] = $this->generateAccountNumber((int) $data['campaign_id']);
            } else {
                unset($data['account_number']);
            }
        }

        if (! empty($data['assigned_agent_profile_id'])) {
            $assigned = CampaignAssignment::query()
                ->where('campaign_id', $data['campaign_id'])
                ->where('agent_profile_id', $data['assigned_agent_profile_id'])
                ->exists();
            abort_unless($assigned, 422, 'Assigned agent must be assigned to the selected campaign.');
        }

        return $data;
    }

    private function generateAccountNumber(int $campaignId): string
    {
        $base = substr(hash('sha256', now()->format('Y-m-d H:i:s.u')), 0, 16);
        $candidate = $base;
        $attempt = 0;

        while (
            Account::query()
                ->where('campaign_id', $campaignId)
                ->where('account_number', $candidate)
                ->exists()
        ) {
            $attempt++;
            $candidate = substr($base.$attempt, 0, 100);
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function accountStats(Account $account): array
    {
        $byCode = DB::table('account_activities')
            ->join('activity_types', 'activity_types.id', '=', 'account_activities.activity_type_id')
            ->where('account_activities.account_id', $account->id)
            ->whereNull('account_activities.deleted_at')
            ->selectRaw('activity_types.code, COUNT(*) as aggregate')
            ->groupBy('activity_types.code')
            ->pluck('aggregate', 'code');

        $counts = [];
        foreach (ActivityType::LOCKED_CODES as $code) {
            $counts[$code] = (int) ($byCode[$code] ?? 0);
        }

        $incomingTotal = 0;
        foreach (ActivityType::INCOMING_CODES as $code) {
            $incomingTotal += (int) ($counts[$code] ?? 0);
        }

        $total = max(0, array_sum($counts) - $incomingTotal);
        $excludeSystem = max(0, $total - (int) ($counts['system'] ?? 0));

        return [
            'activity_counts' => $counts,
            'activity_total' => $total,
            'activity_total_excluding_system' => $excludeSystem,
        ];
    }

    /**
     * @return array<int, array{success: int, failed: int, total: int}>
     */
    private function contactActivityStats(Account $account): array
    {
        $rows = DB::table('account_activities')
            ->join('activity_types', 'activity_types.id', '=', 'account_activities.activity_type_id')
            ->where('account_activities.account_id', $account->id)
            ->whereNull('account_activities.deleted_at')
            ->whereNotNull('account_activities.reference_contact_info_id')
            ->selectRaw('account_activities.reference_contact_info_id as contact_id, activity_types.code, COUNT(*) as aggregate')
            ->groupBy('account_activities.reference_contact_info_id', 'activity_types.code')
            ->get();

        $stats = [];
        foreach ($rows as $row) {
            $contactId = (int) $row->contact_id;
            if (! isset($stats[$contactId])) {
                $stats[$contactId] = ['success' => 0, 'failed' => 0, 'total' => 0];
            }

            $count = (int) $row->aggregate;
            $stats[$contactId]['total'] += $count;

            if (in_array($row->code, ActivityType::SUCCESS_CODES, true)) {
                $stats[$contactId]['success'] += $count;
            }
            if (in_array($row->code, ActivityType::FAILED_CODES, true)) {
                $stats[$contactId]['failed'] += $count;
            }
        }

        return $stats;
    }

    private function syncAccountLastFromActivity(Account $account, AccountActivity $activity, User $user): void
    {
        $activity->loadMissing('activityType:id,code');
        $isSystem = ($activity->activityType?->code ?? null) === 'system';

        if (! $isSystem) {
            $updates = [
                'updated_by' => $user->id,
                'last_activity_type_id' => $activity->activity_type_id,
                'last_activity_user_id' => $activity->actor_user_id,
                'last_activity_agent_profile_id' => $activity->agent_profile_id,
                'entity_status_id' => $activity->entity_status_id,
            ];

            if ($activity->entity_action_code_id) {
                $updates['entity_action_code_id'] = $activity->entity_action_code_id;
            }
            if ($activity->reference_amount !== null) {
                $updates['last_reference_amount'] = $activity->reference_amount;
            }
            if ($activity->reference_date !== null) {
                $updates['last_reference_date'] = $activity->reference_date;
            }
            if ($activity->reference_time !== null) {
                $updates['last_reference_time'] = $activity->reference_time;
            }
            if ($activity->reference_text !== null) {
                $updates['last_reference_text'] = $activity->reference_text;
            }
            if ($activity->reference_contact_info_id) {
                $updates['last_reference_contact_info_id'] = $activity->reference_contact_info_id;
            }
            if ($activity->reference_address_id) {
                $updates['last_reference_address_id'] = $activity->reference_address_id;
            }

            $account->update($updates);
        }

        app(AccountActivityTotalsSync::class)->sync($account);
    }

    private function resolveActivityClassification(null|int|string $entityActionCodeId): ActionCodeClassification
    {
        if (! $entityActionCodeId) {
            return ActionCodeClassification::Neutral;
        }

        $raw = EntityActionCode::query()->whereKey($entityActionCodeId)->value('classification');

        if ($raw instanceof ActionCodeClassification) {
            return $raw;
        }

        return ActionCodeClassification::tryFrom((string) $raw) ?? ActionCodeClassification::Neutral;
    }

    private function hydrateLastReferenceRelations(Account $account): void
    {
        if (! $account->last_reference_contact_info_id) {
            $contactId = $account->activities()
                ->whereNotNull('reference_contact_info_id')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->value('reference_contact_info_id');

            if ($contactId) {
                $account->setRelation(
                    'lastReferenceContactInfo',
                    AccountContactInfo::query()->find($contactId),
                );
            }
        }

        if (! $account->last_reference_address_id) {
            $addressId = $account->activities()
                ->whereNotNull('reference_address_id')
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->value('reference_address_id');

            if ($addressId) {
                $account->setRelation(
                    'lastReferenceAddress',
                    AccountAddress::query()->find($addressId),
                );
            }
        }
    }

    /**
     * @return array{
     *     last_attachments: list<array{id: int, activity_id: int, original_name: string, mime: ?string}>,
     *     last_comment_agent: ?array{agent_profile: mixed, actor_user: mixed, remarks: string},
     *     last_activity_at: ?string,
     *     status_timeline: list<array{status_id: int, name: string, from: string, to: string, days: int, is_current: bool}>
     * }
     */
    private function accountStatusExtras(Account $account): array
    {
        $lastWithFiles = $account->activities()
            ->whereHas('files')
            ->with(['files' => fn ($query) => $query->orderBy('id')])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        $lastComment = $account->activities()
            ->whereNotNull('remarks')
            ->where('remarks', '!=', '')
            ->with([
                'agentProfile:id,display_name,first_name,last_name,user_id',
                'agentProfile.user:id,avatar_path',
                'actorUser:id,name,first_name,last_name,username,email,avatar_path',
            ])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->first();

        $lastActivityAt = $account->activities()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->value('occurred_at');

        return [
            'last_attachments' => $lastWithFiles
                ? $lastWithFiles->files->map(fn (AccountActivityFile $file): array => [
                    'id' => $file->id,
                    'activity_id' => $lastWithFiles->id,
                    'original_name' => $file->original_name,
                    'mime' => $file->mime,
                ])->values()->all()
                : [],
            'last_comment_agent' => $lastComment ? [
                'agent_profile' => $lastComment->agentProfile,
                'actor_user' => $lastComment->actorUser,
                'remarks' => (string) $lastComment->remarks,
            ] : null,
            'last_activity_at' => $lastActivityAt
                ? (string) Carbon::parse($lastActivityAt)->toIso8601String()
                : null,
            'status_timeline' => $this->accountStatusTimeline($account),
        ];
    }

    /**
     * Distinct entity-status segments from account activities (consecutive same status collapsed).
     *
     * @return list<array{
     *     status_id: int,
     *     name: string,
     *     color: ?string,
     *     text_color: ?string,
     *     from: string,
     *     to: string,
     *     days: int,
     *     is_current: bool,
     *     agents: list<array{agent_profile: mixed, actor_user: mixed}>
     * }>
     */
    private function accountStatusTimeline(Account $account): array
    {
        $statusRows = $account->activities()
            ->whereNotNull('entity_status_id')
            ->with('entityStatus:id,name,color,text_color')
            ->orderBy('occurred_at', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'entity_status_id', 'occurred_at']);

        /** @var list<array{status_id: int, name: string, color: ?string, text_color: ?string, from: Carbon}> $starts */
        $starts = [];
        foreach ($statusRows as $row) {
            $statusId = (int) $row->entity_status_id;
            $last = $starts === [] ? null : $starts[array_key_last($starts)];
            if ($last !== null && $last['status_id'] === $statusId) {
                continue;
            }
            $starts[] = [
                'status_id' => $statusId,
                'name' => $row->entityStatus?->name ?? '—',
                'color' => $row->entityStatus?->color,
                'text_color' => $row->entityStatus?->text_color ?: '#ffffff',
                'from' => Carbon::parse($row->occurred_at),
            ];
        }

        if ($starts === [] && $account->entity_status_id) {
            $account->loadMissing('entityStatus:id,name,color,text_color');
            $starts[] = [
                'status_id' => (int) $account->entity_status_id,
                'name' => $account->entityStatus?->name ?? '—',
                'color' => $account->entityStatus?->color,
                'text_color' => $account->entityStatus?->text_color ?: '#ffffff',
                'from' => $account->created_at
                    ? Carbon::parse($account->created_at)
                    : now(),
            ];
        }

        if ($starts === []) {
            return [];
        }

        $assignedActivities = $account->activities()
            ->with([
                'assignedAgentProfile:id,display_name,first_name,last_name,user_id',
                'assignedAgentProfile.user:id,avatar_path',
            ])
            ->orderBy('occurred_at', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'occurred_at', 'assigned_agent_profile_id']);

        // Oldest → latest (left → right in the UI).
        usort(
            $starts,
            fn (array $a, array $b): int => $a['from']->timestamp <=> $b['from']->timestamp
                ?: ($a['status_id'] <=> $b['status_id']),
        );

        $now = now();
        $count = count($starts);
        $timeline = [];

        foreach ($starts as $i => $seg) {
            $from = $seg['from'];
            $to = $i + 1 < $count ? $starts[$i + 1]['from'] : $now;
            $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay());

            $agentsByKey = [];
            foreach ($assignedActivities as $activity) {
                $at = Carbon::parse($activity->occurred_at);
                if ($at->lt($from)) {
                    continue;
                }
                if ($i + 1 < $count && $at->gte($to)) {
                    continue;
                }

                $profile = $activity->assignedAgentProfile;
                if (! $profile) {
                    continue;
                }

                $key = 'p:'.$profile->id;
                if (isset($agentsByKey[$key])) {
                    continue;
                }

                $agentsByKey[$key] = [
                    'agent_profile' => $profile,
                    'actor_user' => $profile->user,
                ];
            }

            // Current segment with no snapshot yet: fall back to account's current assigned agent.
            if ($agentsByKey === [] && $i === $count - 1 && $account->assigned_agent_profile_id) {
                $account->loadMissing([
                    'assignedAgentProfile:id,display_name,first_name,last_name,user_id',
                    'assignedAgentProfile.user:id,avatar_path',
                ]);
                if ($account->assignedAgentProfile) {
                    $agentsByKey['p:'.$account->assignedAgentProfile->id] = [
                        'agent_profile' => $account->assignedAgentProfile,
                        'actor_user' => $account->assignedAgentProfile->user,
                    ];
                }
            }

            $timeline[] = [
                'status_id' => $seg['status_id'],
                'name' => $seg['name'],
                'color' => $seg['color'],
                'text_color' => $seg['text_color'] ?? '#ffffff',
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'days' => max(0, $days),
                'is_current' => $i === $count - 1,
                'agents' => array_values($agentsByKey),
            ];
        }

        return $timeline;
    }

    private function assertEntityCatalogIds(?int $entityId, mixed $statusId, mixed $actionId): void
    {
        if ($statusId) {
            abort_unless(
                $entityId && EntityStatus::query()->whereKey($statusId)->where('entity_id', $entityId)->exists(),
                422,
            );
        }

        if ($actionId) {
            abort_unless(
                $entityId && EntityActionCode::query()->whereKey($actionId)->where('entity_id', $entityId)->exists(),
                422,
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function sendActivityTemplateChannels(): array
    {
        return [
            'sms_send' => 'sms',
            'email_send' => 'email',
            'chat_send' => 'chat',
        ];
    }

    private function resolveActivityTemplateId(?int $entityId, string $activityTypeCode, mixed $templateId): ?int
    {
        $channel = $this->sendActivityTemplateChannels()[$activityTypeCode] ?? null;

        if (! $channel || ! $templateId) {
            return null;
        }

        $template = EntityTemplate::query()
            ->whereKey($templateId)
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->first();

        abort_unless(
            $template && is_array($template->types) && in_array($channel, $template->types, true),
            422,
        );

        return (int) $template->id;
    }

    private function logSystemAccountActivity(Account $account, User $user, string $remarks): void
    {
        $typeId = ActivityType::query()->where('code', 'system')->where('is_active', true)->value('id');
        if (! $typeId) {
            return;
        }

        $user->loadMissing('agentProfile');

        $activity = $account->activities()->create([
            'occurred_at' => now(),
            'activity_type_id' => $typeId,
            'actor_user_id' => $user->id,
            'agent_profile_id' => $user->agentProfile?->id,
            'assigned_agent_profile_id' => $account->assigned_agent_profile_id,
            'classification' => ActionCodeClassification::Neutral,
            'remarks' => $remarks,
        ]);

        $this->syncAccountLastFromActivity($account, $activity, $user);
    }

    private function actorLabelForUser(User $user): string
    {
        return $this->agentProfileLabel($user->agentProfile) ?: $this->userLabel($user);
    }

    private function agentProfileLabel(?AgentProfile $profile): string
    {
        if (! $profile) {
            return '';
        }

        return $profile->display_name
            ?: trim("{$profile->first_name} {$profile->last_name}")
            ?: '';
    }

    private function userLabel(User $user): string
    {
        if ($user->first_name || $user->last_name) {
            return trim("{$user->first_name} {$user->last_name}");
        }

        return $user->name ?: $user->username ?: $user->email ?: 'System';
    }

    /**
     * @param  Collection<int, Campaign>  $campaigns
     * @return array<int, list<array{id: int, name: string}>>
     */
    private function assignableAgentsByCampaign(Collection $campaigns): array
    {
        $campaignIds = $campaigns->pluck('id')->all();
        if ($campaignIds === []) {
            return [];
        }

        $assignments = CampaignAssignment::query()
            ->with('agentProfile:id,display_name,first_name,last_name,status')
            ->whereIn('campaign_id', $campaignIds)
            ->get();

        $map = [];
        foreach ($assignments as $assignment) {
            $profile = $assignment->agentProfile;
            if (! $profile || $profile->status !== AgentProfileStatus::Active) {
                continue;
            }
            $map[$assignment->campaign_id][] = [
                'id' => $profile->id,
                'name' => $this->agentProfileLabel($profile) ?: "Agent #{$profile->id}",
            ];
        }

        return $map;
    }

    /**
     * @return array{
     *     search: ?string,
     *     sort: ?string,
     *     direction: ?string,
     *     entity_id: ?int,
     *     campaign_id: ?int,
     *     entity_status_id: ?int,
     *     entity_action_code_id: ?int,
     *     last_activity_agent_profile_id: ?int
     * }
     */
    private function listingFilters(Request $request): array
    {
        return [
            'search' => $request->string('search')->toString() ?: null,
            'sort' => $request->string('sort')->toString() ?: null,
            'direction' => $request->string('direction')->toString() ?: null,
            'entity_id' => $request->integer('entity_id') ?: null,
            'campaign_id' => $request->integer('campaign_id') ?: null,
            'entity_status_id' => $request->integer('entity_status_id') ?: null,
            'entity_action_code_id' => $request->integer('entity_action_code_id') ?: null,
            'last_activity_agent_profile_id' => $request->integer('last_activity_agent_profile_id') ?: null,
        ];
    }

    /**
     * @param  array{
     *     entity_id: ?int,
     *     campaign_id: ?int,
     *     entity_status_id: ?int,
     *     entity_action_code_id: ?int,
     *     last_activity_agent_profile_id: ?int
     * }  $filters
     */
    private function applyListingFilters(Builder $query, array $filters, User $user): void
    {
        $allowedCampaignIds = $user->allowedCampaignIds();

        if (! $user->isSuperAdmin()) {
            if ($allowedCampaignIds === []) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereIn('campaign_id', $allowedCampaignIds);
        }

        if ($filters['entity_id']) {
            $entityCampaignIds = Campaign::query()
                ->where('entity_id', $filters['entity_id'])
                ->pluck('id')
                ->all();

            $query->whereIn('campaign_id', $entityCampaignIds === [] ? [-1] : $entityCampaignIds);

            if ($filters['campaign_id']) {
                if (! $user->isSuperAdmin() && ! in_array($filters['campaign_id'], $allowedCampaignIds, true)) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where('campaign_id', $filters['campaign_id']);
            }

            if ($filters['entity_status_id']) {
                $query->where('entity_status_id', $filters['entity_status_id']);
            }

            if ($filters['entity_action_code_id']) {
                $query->where('entity_action_code_id', $filters['entity_action_code_id']);
            }

            if ($filters['last_activity_agent_profile_id']) {
                $query->where('last_activity_agent_profile_id', $filters['last_activity_agent_profile_id']);
            }
        }
    }

    /**
     * @param  array{
     *     entity_id: ?int,
     *     campaign_id: ?int,
     *     entity_status_id: ?int,
     *     entity_action_code_id: ?int,
     *     last_activity_agent_profile_id: ?int
     * }  $filters
     * @return array{
     *     entities: Collection<int, Entity>,
     *     campaigns: Collection<int, Campaign>,
     *     statuses: Collection<int, EntityStatus>,
     *     actions: Collection<int, EntityActionCode>,
     *     lastAgents: Collection<int, array{id: int, name: string}>
     * }
     */
    private function listingFilterOptions(User $user, array $filters): array
    {
        $entitiesQuery = Entity::query()->orderBy('name');

        if (! $user->isSuperAdmin()) {
            $assignedEntityIds = Campaign::query()
                ->whereIn('id', $user->allowedCampaignIds() === [] ? [-1] : $user->allowedCampaignIds())
                ->pluck('entity_id')
                ->unique()
                ->filter()
                ->values();

            $entitiesQuery->whereIn('id', $assignedEntityIds->isEmpty() ? [-1] : $assignedEntityIds->all());
        }

        $entities = $entitiesQuery->get(['id', 'name', 'entity_code']);

        $campaigns = collect();
        $statuses = collect();
        $actions = collect();
        $lastAgents = collect();

        if ($filters['entity_id']) {
            $campaignQuery = Campaign::query()
                ->where('entity_id', $filters['entity_id'])
                ->orderBy('name');

            if (! $user->isSuperAdmin()) {
                $allowedCampaignIds = $user->allowedCampaignIds();
                $campaignQuery->whereIn('id', $allowedCampaignIds === [] ? [-1] : $allowedCampaignIds);
            }

            $campaigns = $campaignQuery->get(['id', 'name', 'entity_id']);

            $statuses = EntityStatus::query()
                ->where('entity_id', $filters['entity_id'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'entity_id']);

            $actions = EntityActionCode::query()
                ->where('entity_id', $filters['entity_id'])
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'entity_id']);

            $agentCampaignIds = $campaigns->pluck('id')->all();
            if ($agentCampaignIds !== []) {
                $agentIds = CampaignAssignment::query()
                    ->whereIn('campaign_id', $agentCampaignIds)
                    ->pluck('agent_profile_id')
                    ->unique()
                    ->filter()
                    ->values();

                $lastAgents = AgentProfile::query()
                    ->whereIn('id', $agentIds->isEmpty() ? [-1] : $agentIds->all())
                    ->orderBy('display_name')
                    ->orderBy('first_name')
                    ->get(['id', 'display_name', 'first_name', 'last_name'])
                    ->map(fn (AgentProfile $profile) => [
                        'id' => $profile->id,
                        'name' => $this->agentProfileLabel($profile) ?: "Agent #{$profile->id}",
                    ])
                    ->values();
            }
        }

        return [
            'entities' => $entities,
            'campaigns' => $campaigns,
            'statuses' => $statuses,
            'actions' => $actions,
            'lastAgents' => $lastAgents,
        ];
    }

    private function availableCampaigns(Request $request): Collection
    {
        /** @var User $user */
        $user = $request->user();

        $query = Campaign::query()->with('entity:id,name')->orderBy('name');

        if (! $user->isSuperAdmin()) {
            $allowedIds = $user->allowedCampaignIds();
            $query->whereIn('id', $allowedIds === [] ? [-1] : $allowedIds);
        }

        return $query->get(['id', 'name', 'entity_id']);
    }

    private function authorizeCampaignId(Request $request, int $campaignId): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return;
        }

        if (! in_array($campaignId, $user->allowedCampaignIds(), true)) {
            abort(403);
        }
    }

    private function filteredAccountsQuery(Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();
        $filters = $this->resolveListingFilters($request);

        $query = Account::query();
        $this->applyListingFilters($query, $filters, $user);

        if ($search = $filters['search'] ?? null) {
            $search = trim((string) $search);
            if ($search !== '') {
                $query->where(function (Builder $builder) use ($search): void {
                    foreach (['account_number', 'account_name', 'product', 'external_reference'] as $column) {
                        $builder->orWhere($column, 'like', "%{$search}%");
                    }
                });
            }
        }

        return $query;
    }

    /**
     * Bulk posts may send listing state under `filters` so form fields (e.g. entity_status_id)
     * do not collide with listing filter query params.
     *
     * @return array{
     *     search: ?string,
     *     sort: ?string,
     *     direction: ?string,
     *     entity_id: ?int,
     *     campaign_id: ?int,
     *     entity_status_id: ?int,
     *     entity_action_code_id: ?int,
     *     last_activity_agent_profile_id: ?int
     * }
     */
    private function resolveListingFilters(Request $request): array
    {
        if (is_array($request->input('filters'))) {
            $nested = $request->input('filters');

            return [
                'search' => isset($nested['search']) && $nested['search'] !== '' ? (string) $nested['search'] : null,
                'sort' => isset($nested['sort']) && $nested['sort'] !== '' ? (string) $nested['sort'] : null,
                'direction' => isset($nested['direction']) && $nested['direction'] !== '' ? (string) $nested['direction'] : null,
                'entity_id' => isset($nested['entity_id']) ? (int) $nested['entity_id'] ?: null : null,
                'campaign_id' => isset($nested['campaign_id']) ? (int) $nested['campaign_id'] ?: null : null,
                'entity_status_id' => isset($nested['entity_status_id']) ? (int) $nested['entity_status_id'] ?: null : null,
                'entity_action_code_id' => isset($nested['entity_action_code_id']) ? (int) $nested['entity_action_code_id'] ?: null : null,
                'last_activity_agent_profile_id' => isset($nested['last_activity_agent_profile_id']) ? (int) $nested['last_activity_agent_profile_id'] ?: null : null,
            ];
        }

        return $this->listingFilters($request);
    }

    private function bulkTargetQuery(Request $request): Builder
    {
        $scope = $request->input('scope');
        if (! in_array($scope, ['selected', 'all'], true)) {
            $this->failBulk('Invalid bulk scope.');
        }

        $query = $this->filteredAccountsQuery($request);

        if ($scope === 'selected') {
            $ids = collect($request->input('account_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($ids === []) {
                $this->failBulk('No accounts selected.');
            }

            $query->whereIn('accounts.id', $ids);
        }

        return $query;
    }

    private function assertSingleEntityId(Builder $query): int
    {
        // Resolve via subquery so joins never make accounts.id / campaigns.id ambiguous.
        $entityIds = Campaign::query()
            ->whereIn('id', (clone $query)->select('accounts.campaign_id'))
            ->distinct()
            ->pluck('entity_id')
            ->filter()
            ->values();

        if ($entityIds->isEmpty()) {
            $this->failBulk('No accounts match the selection.');
        }

        if ($entityIds->count() !== 1) {
            $this->failBulk('Selected accounts must belong to the same Entity.');
        }

        return (int) $entityIds->first();
    }

    private function bulkHasDuplicateAccountNumbers(Builder $query): bool
    {
        return (clone $query)
            ->select('accounts.account_number')
            ->groupBy('accounts.account_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    private function bulkHasAccountNumberConflicts(Builder $query, int $targetCampaignId): bool
    {
        return (clone $query)
            ->whereExists(function ($builder) use ($targetCampaignId): void {
                $builder->selectRaw('1')
                    ->from('accounts as other_accounts')
                    ->whereColumn('other_accounts.account_number', 'accounts.account_number')
                    ->where('other_accounts.campaign_id', $targetCampaignId)
                    ->whereNull('other_accounts.deleted_at')
                    ->whereColumn('other_accounts.id', '!=', 'accounts.id');
            })
            ->exists();
    }

    /**
     * @param  Collection<int, int>|list<int>  $campaignIds
     * @return list<array{id: int, name: string}>
     */
    private function agentsAssignedToAllCampaigns(Collection|array $campaignIds): array
    {
        $ids = collect($campaignIds)->map(fn ($id) => (int) $id)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $required = $ids->count();
        $agentIds = CampaignAssignment::query()
            ->whereIn('campaign_id', $ids->all())
            ->select('agent_profile_id')
            ->groupBy('agent_profile_id')
            ->havingRaw('COUNT(DISTINCT campaign_id) = ?', [$required])
            ->pluck('agent_profile_id');

        if ($agentIds->isEmpty()) {
            return [];
        }

        return AgentProfile::query()
            ->whereIn('id', $agentIds)
            ->where('status', AgentProfileStatus::Active)
            ->orderBy('display_name')
            ->orderBy('first_name')
            ->get(['id', 'display_name', 'first_name', 'last_name'])
            ->map(fn (AgentProfile $profile) => [
                'id' => $profile->id,
                'name' => $this->agentProfileLabel($profile) ?: "Agent #{$profile->id}",
            ])
            ->values()
            ->all();
    }

    private function failBulk(string $message, string $key = 'bulk'): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }

    /**
     * @return array{smsDeviceGroups: list<array{id: int, name: string}>, smsDevices: list<array{id: int, name: string, group_name: ?string}>}
     */
    private function smsTargetingProps(): array
    {
        $groups = SmsDeviceGroup::query()
            ->where('enabled', true)
            ->whereHas('devices', fn ($q) => $q->where('enabled', true)->whereNotNull('runtime_device_id'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (SmsDeviceGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
            ])
            ->values()
            ->all();

        $devices = SmsDevice::query()
            ->with('group:id,name')
            ->where('enabled', true)
            ->whereNotNull('runtime_device_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'sms_device_group_id', 'runtime_device_id'])
            ->map(fn (SmsDevice $d) => [
                'id' => $d->id,
                'name' => $d->group?->name
                    ? "{$d->name} ({$d->group->name})"
                    : $d->name,
                'group_name' => $d->group?->name,
            ])
            ->values()
            ->all();

        return [
            'smsDeviceGroups' => $groups,
            'smsDevices' => $devices,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{mode: SmsTargetMode, sms_device_group_id: ?int, sms_device_id: ?int}
     */
    private function validatedSmsTargeting(array $data): array
    {
        $modeValue = $data['sms_target_mode'] ?? null;
        $mode = is_string($modeValue) ? SmsTargetMode::tryFrom($modeValue) : null;

        if (! $mode) {
            throw ValidationException::withMessages([
                'sms_target_mode' => 'Choose round robin to a group or a specific device.',
            ]);
        }

        if ($mode === SmsTargetMode::GroupRoundRobin) {
            $groupId = (int) ($data['sms_device_group_id'] ?? 0);
            $group = SmsDeviceGroup::query()
                ->whereKey($groupId)
                ->where('enabled', true)
                ->whereHas('devices', fn ($q) => $q->where('enabled', true)->whereNotNull('runtime_device_id'))
                ->first();

            if (! $group) {
                throw ValidationException::withMessages([
                    'sms_device_group_id' => 'Select an enabled device group with at least one enabled device.',
                ]);
            }

            return [
                'mode' => $mode,
                'sms_device_group_id' => $group->id,
                'sms_device_id' => null,
            ];
        }

        $deviceId = (int) ($data['sms_device_id'] ?? 0);
        $device = SmsDevice::query()
            ->whereKey($deviceId)
            ->where('enabled', true)
            ->whereNotNull('runtime_device_id')
            ->first();

        if (! $device) {
            throw ValidationException::withMessages([
                'sms_device_id' => 'Select an enabled SMS device with a runtime ID.',
            ]);
        }

        return [
            'mode' => $mode,
            'sms_device_group_id' => null,
            'sms_device_id' => $device->id,
        ];
    }
}
