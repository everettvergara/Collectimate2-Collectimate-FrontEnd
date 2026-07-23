<?php

namespace App\Http\Controllers;

use App\Models\Status;
use App\Services\AuditLogger;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatusController extends Controller
{
    public function index(Request $request): Response
    {
        $statuses = ListingQuery::paginate(
            Status::query(),
            $request,
            ['name', 'slug', 'category'],
            ['name', 'slug', 'category', 'sort_order', 'is_active', 'id'],
        );

        return Inertia::render('Statuses/Index', [
            'statuses' => $statuses,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'manage' => $request->user()->hasPermission('statuses.manage'),
                'export' => $request->user()->hasPermission('statuses.export'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Statuses/Form', [
            'status' => null,
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $status = Status::query()->create($this->validated($request));

        $auditLogger->log('status.created', $status);

        return redirect()->route('statuses.index')->with('success', 'Status created.');
    }

    public function edit(Status $status): Response
    {
        return Inertia::render('Statuses/Form', [
            'status' => $status,
        ]);
    }

    public function update(Request $request, Status $status, AuditLogger $auditLogger): RedirectResponse
    {
        $status->update($this->validated($request, $status));

        $auditLogger->log('status.updated', $status);

        return redirect()->route('statuses.index')->with('success', 'Status updated.');
    }

    public function destroy(Status $status, AuditLogger $auditLogger): RedirectResponse
    {
        $status->delete();

        $auditLogger->log('status.deleted', $status);

        return redirect()->route('statuses.index')->with('success', 'Status deleted.');
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        $query = Status::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                foreach (['name', 'slug', 'category'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $rows = $query->orderBy('sort_order')->get()->map(fn (Status $status): array => [
            $status->name,
            $status->slug,
            $status->category,
            $status->color,
            $status->is_active ? 'yes' : 'no',
        ]);

        $auditLogger->log('statuses.exported');

        return CsvExporter::download('statuses.csv', [
            'Name', 'Slug', 'Category', 'Color', 'Active',
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Status $status = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique('statuses', 'slug')->ignore($status?->id),
            ],
            'category' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
