<?php

namespace App\Http\Controllers;

use App\Models\AgentProfile;
use App\Models\Campaign;
use App\Models\CampaignAssignment;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CampaignAssignmentController extends Controller
{
    public function store(Request $request, Campaign $campaign, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        $data = $request->validate([
            'agent_profile_id' => ['required', 'exists:agent_profiles,id'],
        ]);

        $assignment = CampaignAssignment::query()->firstOrCreate(
            [
                'campaign_id' => $campaign->id,
                'agent_profile_id' => $data['agent_profile_id'],
            ],
            [
                'assigned_by' => $request->user()->id,
            ],
        );

        $auditLogger->log('campaign.assignment_added', $assignment, $campaign->id, [
            'agent_profile_id' => $data['agent_profile_id'],
        ]);

        return back()->with('success', 'Agent assigned to campaign.');
    }

    public function destroy(Campaign $campaign, AgentProfile $agentProfile, AuditLogger $auditLogger): RedirectResponse
    {
        $this->authorizeCampaign($campaign);

        CampaignAssignment::query()
            ->where('campaign_id', $campaign->id)
            ->where('agent_profile_id', $agentProfile->id)
            ->delete();

        $auditLogger->log('campaign.assignment_removed', null, $campaign->id, [
            'agent_profile_id' => $agentProfile->id,
        ]);

        return back()->with('success', 'Agent removed from campaign.');
    }

    private function authorizeCampaign(Campaign $campaign): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return;
        }

        if (! in_array($campaign->id, $user->allowedCampaignIds(), true)) {
            abort(403);
        }
    }
}
