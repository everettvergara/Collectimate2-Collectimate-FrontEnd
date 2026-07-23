<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = ListingQuery::paginate(
            AuditLog::query()->with(['user:id,username,email', 'campaign:id,name']),
            $request,
            ['action'],
            ['action', 'created_at', 'id'],
        );

        return Inertia::render('AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'export' => $request->user()->hasPermission('audit_logs.export'),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = AuditLog::query()->with(['user', 'campaign']);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where('action', 'like', "%{$search}%");
        }

        $rows = $query->orderByDesc('id')->get()->map(fn (AuditLog $log): array => [
            $log->created_at?->toDateTimeString(),
            $log->user?->username,
            $log->action,
            $log->subject_type,
            $log->subject_id,
            $log->campaign?->name,
            $log->ip,
        ]);

        return CsvExporter::download('audit-logs.csv', [
            'Date', 'User', 'Action', 'Subject Type', 'Subject ID', 'Campaign', 'IP',
        ], $rows);
    }
}
