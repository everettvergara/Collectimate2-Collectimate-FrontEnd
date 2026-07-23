<?php

namespace App\Http\Controllers;

use App\Enums\AgentProfileStatus;
use App\Models\AgentProfile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgentProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $profiles = ListingQuery::paginate(
            AgentProfile::query()->with('user'),
            $request,
            ['employee_number', 'first_name', 'last_name', 'display_name', 'email', 'department'],
            ['employee_number', 'first_name', 'last_name', 'display_name', 'status', 'created_at', 'id'],
        );

        return Inertia::render('AgentProfiles/Index', [
            'profiles' => $profiles,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'create' => $request->user()->hasPermission('agent_profiles.create'),
                'export' => $request->user()->hasPermission('agent_profiles.export'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('AgentProfiles/Form', [
            'profile' => null,
            'users' => User::query()->whereDoesntHave('agentProfile')->orderBy('username')->get(['id', 'username', 'email']),
            'statuses' => array_column(AgentProfileStatus::cases(), 'value'),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        $profile = AgentProfile::query()->create($data);

        $auditLogger->log('agent_profile.created', $profile);

        return redirect()->route('agent-profiles.index')->with('success', 'Agent profile created.');
    }

    public function show(AgentProfile $agentProfile): Response
    {
        $agentProfile->load(['user', 'campaigns']);

        return Inertia::render('AgentProfiles/Show', [
            'profile' => $agentProfile,
            'can' => [
                'update' => request()->user()->hasPermission('agent_profiles.update'),
            ],
        ]);
    }

    public function edit(AgentProfile $agentProfile): Response
    {
        return Inertia::render('AgentProfiles/Form', [
            'profile' => $agentProfile->load('user'),
            'users' => User::query()
                ->where(function ($query) use ($agentProfile): void {
                    $query->whereDoesntHave('agentProfile')
                        ->orWhere('id', $agentProfile->user_id);
                })
                ->orderBy('username')
                ->get(['id', 'username', 'email']),
            'statuses' => array_column(AgentProfileStatus::cases(), 'value'),
        ]);
    }

    public function update(Request $request, AgentProfile $agentProfile, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request, $agentProfile);
        $data['updated_by'] = $request->user()->id;

        $agentProfile->update($data);

        $auditLogger->log('agent_profile.updated', $agentProfile);

        return redirect()->route('agent-profiles.index')->with('success', 'Agent profile updated.');
    }

    public function destroy(AgentProfile $agentProfile, AuditLogger $auditLogger): RedirectResponse
    {
        $agentProfile->delete();

        $auditLogger->log('agent_profile.deleted', $agentProfile);

        return redirect()->route('agent-profiles.index')->with('success', 'Agent profile deleted.');
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        $query = AgentProfile::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                foreach (['employee_number', 'first_name', 'last_name', 'display_name', 'email'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $rows = $query->orderBy('id')->get()->map(fn (AgentProfile $profile): array => [
            $profile->employee_number,
            $profile->display_name,
            $profile->email,
            $profile->department,
            $profile->status?->value,
        ]);

        $auditLogger->log('agent_profiles.exported');

        return CsvExporter::download('agent-profiles.csv', [
            'Employee #', 'Display Name', 'Email', 'Department', 'Status',
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AgentProfile $profile = null): array
    {
        return $request->validate([
            'employee_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('agent_profiles', 'employee_number')->ignore($profile?->id),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::enum(AgentProfileStatus::class)],
            'notes' => ['nullable', 'string'],
            'user_id' => ['nullable', 'exists:users,id'],
        ]);
    }
}
