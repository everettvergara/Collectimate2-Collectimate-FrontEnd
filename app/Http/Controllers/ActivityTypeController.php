<?php

namespace App\Http\Controllers;

use App\Models\ActivityType;
use App\Services\AuditLogger;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $activityTypes = ListingQuery::paginate(
            ActivityType::query()->whereIn('code', ActivityType::LOCKED_CODES),
            $request,
            ['name', 'code'],
            ['name', 'code', 'sort_order', 'is_active', 'id'],
        );

        return Inertia::render('ActivityTypes/Index', [
            'activityTypes' => $activityTypes,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'export' => $request->user()->hasPermission('activity_types.export'),
            ],
        ]);
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        $query = ActivityType::query()->whereIn('code', ActivityType::LOCKED_CODES);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                foreach (['name', 'code'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $rows = $query->orderBy('sort_order')->get()->map(fn (ActivityType $type): array => [
            $type->name,
            $type->code,
            $type->is_default ? 'yes' : 'no',
            $type->is_active ? 'yes' : 'no',
            $type->sort_order,
        ]);

        $auditLogger->log('activity_types.exported');

        return CsvExporter::download('activity-types.csv', [
            'Name', 'Code', 'Default', 'Active', 'Order',
        ], $rows);
    }
}
