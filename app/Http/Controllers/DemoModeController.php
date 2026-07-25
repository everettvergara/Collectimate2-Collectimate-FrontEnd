<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\DemoModeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DemoModeController extends Controller
{
    public function index(DemoModeService $demoMode): Response
    {
        return Inertia::render('DemoMode/Index', [
            'summary' => $demoMode->summary(),
        ]);
    }

    public function clear(Request $request, DemoModeService $demoMode, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'confirmation' => ['required', 'string'],
        ]);

        if (trim($data['confirmation']) !== 'CLEAR') {
            throw ValidationException::withMessages([
                'confirmation' => 'Type CLEAR to confirm.',
            ]);
        }

        $result = $demoMode->clearNonTemplateData($request->user());

        $auditLogger->log('demo_mode.cleared', null, null, $result);

        return back()->with(
            'success',
            "Cleared {$result['deleted_entities']} non-template entities and {$result['deleted_demo_users']} demo users (abc/fyd). Template catalogs preserved.",
        );
    }

    public function createDemo(Request $request, DemoModeService $demoMode, AuditLogger $auditLogger): RedirectResponse
    {
        $result = $demoMode->createDemoData($request->user());

        $auditLogger->log('demo_mode.demo_created', null, null, [
            'entities' => $result['entities'],
        ]);

        $createdAccounts = collect($result['entities'])->sum('accounts_created');

        return back()->with(
            'success',
            $result['message'].($createdAccounts > 0 ? " Created {$createdAccounts} accounts." : ''),
        );
    }
}
