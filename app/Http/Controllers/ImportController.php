<?php

namespace App\Http\Controllers;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Services\AuditLogger;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        $batches = ListingQuery::paginate(
            ImportBatch::query()->with(['campaign:id,name', 'importer:id,username']),
            $request,
            ['module', 'filename'],
            ['module', 'filename', 'status', 'created_at', 'id'],
        );

        return Inertia::render('Imports/Index', [
            'batches' => $batches,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'run' => $request->user()->hasPermission('imports.run'),
            ],
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'module' => ['required', 'string', 'max:50'],
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
        ]);

        $path = $request->file('file')->store('imports');

        $batch = ImportBatch::query()->create([
            'module' => $data['module'],
            'filename' => $request->file('file')->getClientOriginalName(),
            'status' => ImportBatchStatus::Pending,
            'campaign_id' => $data['campaign_id'] ?? null,
            'imported_by' => $request->user()->id,
        ]);

        $auditLogger->log('import.uploaded', $batch, $batch->campaign_id, [
            'path' => $path,
            'module' => $data['module'],
        ]);

        return back()->with('success', 'Import file uploaded. Processing will be implemented in a later slice.');
    }
}
