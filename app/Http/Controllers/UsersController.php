<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\CsvExporter;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UsersController extends Controller
{
    public function index(Request $request): Response
    {
        $users = ListingQuery::paginate(
            User::query()->with('role'),
            $request,
            ['username', 'email', 'first_name', 'last_name', 'mobile'],
            ['username', 'email', 'first_name', 'last_name', 'status', 'created_at', 'id'],
        );

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => $request->only(['search', 'sort', 'direction']),
            'can' => [
                'create' => $request->user()->hasPermission('users.create'),
                'export' => $request->user()->hasPermission('users.export'),
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Form', [
            'user' => null,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => array_column(UserStatus::cases(), 'value'),
        ]);
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request);

        $user = User::query()->create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        $auditLogger->log('user.created', $user);

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Form', [
            'user' => $user->load('role'),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => array_column(UserStatus::cases(), 'value'),
        ]);
    }

    public function update(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        $auditLogger->log('user.updated', $user);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function export(Request $request, AuditLogger $auditLogger): StreamedResponse
    {
        $query = User::query()->with('role');

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($builder) use ($search): void {
                foreach (['username', 'email', 'first_name', 'last_name'] as $column) {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $rows = $query->orderBy('id')->get()->map(fn (User $user): array => [
            $user->username,
            $user->email,
            $user->first_name,
            $user->last_name,
            $user->mobile,
            $user->status?->value,
            $user->role?->name,
        ]);

        $auditLogger->log('users.exported');

        return CsvExporter::download('users.csv', [
            'Username', 'Email', 'First Name', 'Last Name', 'Mobile', 'Status', 'Role',
        ], $rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $passwordRule = $user
            ? ['nullable', 'confirmed', Password::defaults()]
            : ['required', 'confirmed', Password::defaults()];

        return $request->validate([
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($user?->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'password' => $passwordRule,
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'mobile' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'role_id' => ['required', 'exists:roles,id'],
        ]);
    }
}
