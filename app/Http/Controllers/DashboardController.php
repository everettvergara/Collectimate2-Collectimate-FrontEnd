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

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();

        $campaignQuery = Campaign::query();
        if (! $user->isSuperAdmin()) {
            $campaignIds = $user->allowedCampaignIds();
            $campaignQuery->whereIn('id', $campaignIds);
        }

        $entityQuery = Entity::query();
        CampaignScope::applyToEntity($entityQuery);

        $accountQuery = Account::query();
        CampaignScope::apply($accountQuery);

        $usersCount = $user->hasPermission('users.view')
            ? User::query()->count()
            : 0;

        return Inertia::render('Dashboard', [
            'stats' => [
                'campaigns' => $campaignQuery->count(),
                'entities' => $entityQuery->count(),
                'accounts' => $accountQuery->count(),
                'users' => $usersCount,
            ],
        ]);
    }
}
