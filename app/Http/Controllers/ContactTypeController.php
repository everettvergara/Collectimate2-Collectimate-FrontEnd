<?php

namespace App\Http\Controllers;

use App\Enums\ContactInfoType;
use App\Services\AuditLogger;
use App\Support\CsvExporter;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $rows = $this->filteredRows($request);
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = 25;
        $total = $rows->count();
        $slice = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        $contactTypes = new LengthAwarePaginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return Inertia::render('ContactTypes/Index', [
            'contactTypes' => $contactTypes,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'export' => $request->user()->hasPermission('contact_types.export'),
            ],
        ]);
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        $rows = $this->filteredRows($request)->map(fn (array $row): array => [
            $row['name'],
            $row['code'],
        ]);

        $auditLogger->log('contact_types.exported');

        return CsvExporter::download('contact-types.csv', [
            'Name', 'Code',
        ], $rows);
    }

    /**
     * @return Collection<int, array{id: string, name: string, code: string}>
     */
    private function filteredRows(Request $request)
    {
        $search = strtolower($request->string('search')->trim()->toString());
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $rows = collect(ContactInfoType::cases())->map(fn (ContactInfoType $type): array => [
            'id' => $type->value,
            'name' => $type->label(),
            'code' => $type->value,
        ]);

        if ($search !== '') {
            $rows = $rows->filter(function (array $row) use ($search): bool {
                return str_contains(strtolower($row['name']), $search)
                    || str_contains(strtolower($row['code']), $search);
            })->values();
        }

        if (in_array($sort, ['name', 'code'], true)) {
            $rows = $direction === 'desc'
                ? $rows->sortByDesc($sort)->values()
                : $rows->sortBy($sort)->values();
        }

        return $rows;
    }
}
