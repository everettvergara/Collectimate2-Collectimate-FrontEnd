<?php

namespace App\Http\Controllers;

use App\Enums\ContactInfoType;
use App\Models\Account;
use App\Models\AccountAddress;
use App\Models\AccountContactInfo;
use App\Models\AccountSecondaryContact;
use App\Models\AccountSocialLink;
use App\Models\Campaign;
use App\Models\Status;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $accounts = ListingQuery::paginate(
            Account::query()->with([
                'campaign:id,name,entity_id',
                'campaign.entity:id,name',
                'status:id,name,color',
            ]),
            $request,
            ['account_number', 'product', 'external_reference'],
            ['account_number', 'product', 'balance', 'due_date', 'created_at', 'id'],
        );

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'create' => $request->user()->hasPermission('accounts.create'),
                'export' => $request->user()->hasPermission('accounts.export'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Accounts/Form', [
            'account' => null,
            'campaigns' => $this->availableCampaigns($request),
            'statuses' => Status::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'contactTypes' => array_column(ContactInfoType::cases(), 'value'),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request);
        $this->authorizeCampaignId($request, (int) $data['campaign_id']);

        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $account = Account::query()->create($data);

        $auditLogger->log('account.created', $account, $account->campaign_id);

        return redirect()->route('accounts.index')->with('success', 'Account created.');
    }

    public function show(Account $account): Response
    {
        $account->load([
            'campaign.entity',
            'status',
            'contactInfos',
            'addresses',
            'secondaryContacts',
            'socialLinks',
        ]);

        return Inertia::render('Accounts/Show', [
            'account' => $account,
            'contactTypes' => array_column(ContactInfoType::cases(), 'value'),
            'can' => [
                'update' => request()->user()->hasPermission('accounts.update'),
                'delete' => request()->user()->hasPermission('accounts.delete'),
            ],
        ]);
    }

    public function edit(Request $request, Account $account): Response
    {
        return Inertia::render('Accounts/Form', [
            'account' => $account->load(['campaign.entity']),
            'campaigns' => $this->availableCampaigns($request),
            'statuses' => Status::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'contactTypes' => array_column(ContactInfoType::cases(), 'value'),
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

    public function storeContactInfo(Request $request, Account $account, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(ContactInfoType::class)],
            'value' => ['required', 'string', 'max:255'],
            'label' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $contact = $account->contactInfos()->create($data);

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
        $data = $request->validate([
            'type' => ['nullable', 'string', 'max:50'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['boolean'],
        ]);

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

    public function storeSecondaryContact(Request $request, Account $account, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $contact = $account->secondaryContacts()->create($data);

        $auditLogger->log('account.secondary_contact.created', $contact, $account->campaign_id);

        return back()->with('success', 'Secondary contact added.');
    }

    public function destroySecondaryContact(Account $account, AccountSecondaryContact $secondaryContact, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($secondaryContact->account_id === $account->id, 404);

        $secondaryContact->delete();

        $auditLogger->log('account.secondary_contact.deleted', $secondaryContact, $account->campaign_id);

        return back()->with('success', 'Secondary contact removed.');
    }

    public function storeSocialLink(Request $request, Account $account, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:500'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $link = $account->socialLinks()->create($data);

        $auditLogger->log('account.social_link.created', $link, $account->campaign_id);

        return back()->with('success', 'Social link added.');
    }

    public function destroySocialLink(Account $account, AccountSocialLink $socialLink, AuditLogger $auditLogger): RedirectResponse
    {
        abort_unless($socialLink->account_id === $account->id, 404);

        $socialLink->delete();

        $auditLogger->log('account.social_link.deleted', $socialLink, $account->campaign_id);

        return back()->with('success', 'Social link removed.');
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        $query = Account::query()->with(['campaign.entity', 'status']);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                foreach (['account_number', 'product', 'external_reference'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $rows = $query->orderBy('id')->get()->map(fn (Account $account): array => [
            $account->account_number,
            $account->campaign?->entity?->name,
            $account->campaign?->name,
            $account->product,
            $account->balance,
            $account->status?->name,
        ]);

        $auditLogger->log('accounts.exported');

        return CsvExporter::download('accounts.csv', [
            'Account #', 'Entity', 'Campaign', 'Product', 'Balance', 'Status',
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Account $account = null): array
    {
        return $request->validate([
            'campaign_id' => ['required', 'exists:campaigns,id'],
            'account_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('accounts', 'account_number')
                    ->where(fn ($query) => $query->where('campaign_id', $request->input('campaign_id')))
                    ->ignore($account?->id),
            ],
            'product' => ['nullable', 'string', 'max:255'],
            'balance' => ['nullable', 'numeric'],
            'due_date' => ['nullable', 'date'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'status_id' => ['nullable', 'exists:statuses,id'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function availableCampaigns(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        $query = Campaign::query()->with('entity:id,name')->orderBy('name');

        if (! $user->isSuperAdmin()) {
            $query->whereIn('id', $user->allowedCampaignIds());
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
}
