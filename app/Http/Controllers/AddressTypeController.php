<?php

namespace App\Http\Controllers;

use App\Models\AddressType;
use App\Services\AuditLogger;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AddressTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $addressTypes = ListingQuery::paginate(
            AddressType::query(),
            $request,
            ['name', 'code'],
            ['name', 'code', 'sort_order', 'is_active', 'id'],
        );

        return Inertia::render('AddressTypes/Index', [
            'addressTypes' => $addressTypes,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'manage' => $request->user()->hasPermission('address_types.manage'),
                'export' => $request->user()->hasPermission('address_types.export'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('AddressTypes/Form', [
            'addressType' => null,
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $addressType = AddressType::query()->create($this->validated($request));

        $auditLogger->log('address_type.created', $addressType);

        return redirect()->route('address-types.index')->with('success', 'Address type created.');
    }

    public function edit(AddressType $addressType): Response
    {
        return Inertia::render('AddressTypes/Form', [
            'addressType' => $addressType,
        ]);
    }

    public function update(Request $request, AddressType $addressType, AuditLogger $auditLogger): RedirectResponse
    {
        $addressType->update($this->validated($request, $addressType));

        $auditLogger->log('address_type.updated', $addressType);

        return redirect()->route('address-types.index')->with('success', 'Address type updated.');
    }

    public function destroy(AddressType $addressType, AuditLogger $auditLogger): RedirectResponse
    {
        if ($addressType->is_default) {
            return back()->with('error', 'Default address types cannot be deleted.');
        }

        $addressType->delete();

        $auditLogger->log('address_type.deleted', $addressType);

        return redirect()->route('address-types.index')->with('success', 'Address type deleted.');
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        $query = AddressType::query();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                foreach (['name', 'code'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $rows = $query->orderBy('sort_order')->get()->map(fn (AddressType $type): array => [
            $type->name,
            $type->code,
            $type->is_default ? 'yes' : 'no',
            $type->is_active ? 'yes' : 'no',
            $type->sort_order,
        ]);

        $auditLogger->log('address_types.exported');

        return CsvExporter::download('address-types.csv', [
            'Name', 'Code', 'Default', 'Active', 'Order',
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?AddressType $addressType = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('address_types', 'code')->ignore($addressType?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        if ($addressType?->is_default) {
            unset($data['code']);
        } else {
            $data['is_default'] = false;
        }

        return $data;
    }
}
