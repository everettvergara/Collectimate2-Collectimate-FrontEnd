<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\Entity;
use App\Models\User;
use App\Support\CampaignScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $campaignQuery = Campaign::query();
        $entityQuery = Entity::query();
        $accountQuery = Account::query();

        if (! $user->isSuperAdmin()) {
            $campaignQuery->whereIn('id', $user->allowedCampaignIds());
        }

        CampaignScope::applyToEntity($entityQuery);
        CampaignScope::apply($accountQuery);

        return Inertia::render('Reports/Index', [
            'summaries' => [
                'campaigns' => $campaignQuery->count(),
                'entities' => $entityQuery->count(),
                'accounts' => $accountQuery->count(),
                'total_balance' => (float) $accountQuery->sum('balance'),
            ],
        ]);
    }
}
