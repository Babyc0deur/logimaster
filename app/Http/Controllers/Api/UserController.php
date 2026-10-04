<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends ApiController
{
    public function index(Request $request)
    {
        $this->requirePermission($request, 'view_users');

        return $this->visible($request)->with(['roles:id,name', 'districts:id,name'])
            ->orderBy('name')->paginate($this->perPage($request));
    }

    public function show(Request $request, string $id)
    {
        $this->requirePermission($request, 'view_users');

        return $this->visible($request)->with(['roles:id,name', 'districts:id,name'])->findOrFail($id);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'create_users');
        $data = $this->validated($request, null);

        $user = User::create($data + ['is_active' => $data['is_active'] ?? true]);
        $this->assign($request, $user, $data);

        return response()->json($user->load(['roles:id,name', 'districts:id,name']), 201);
    }

    public function update(Request $request, string $id)
    {
        $this->requirePermission($request, 'update_users');
        $user = $this->visible($request)->findOrFail($id);
        $data = $this->validated($request, $user);

        $user->update(collect($data)->except(['role', 'district_ids'])->all());
        $this->assign($request, $user, $data);

        return $user->load(['roles:id,name', 'districts:id,name']);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->requirePermission($request, 'delete_users');
        abort_if($request->user()->getKey() === $id, 422, 'Impossible de supprimer son propre compte.');
        $this->visible($request)->findOrFail($id)->delete();

        return response()->json(null, 204);
    }

    /** Un utilisateur non national ne voit que les comptes qui partagent un de ses districts. */
    private function visible(Request $request)
    {
        $ids = $request->user()->accessibleDistrictIds();

        return User::query()->when($ids !== null, fn ($q) => $q->whereHas('districts', fn ($d) => $d->whereIn('districts.id', $ids)));
    }

    private function validated(Request $request, ?User $user): array
    {
        $req = $user ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:120'],
            'email' => [$req, 'email', 'max:160', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$req, 'string', 'min:8'],
            'is_active' => ['sometimes', 'boolean'],
            'two_factor_enabled' => ['sometimes', 'boolean'],
            'role' => [$req, Rule::exists('roles', 'name')],
            'region_id' => ['sometimes', 'nullable', 'uuid', 'exists:regions,id'],
            'district_ids' => ['sometimes', 'array'],
            'district_ids.*' => ['uuid', 'exists:districts,id'],
        ]);
    }

    private function assign(Request $request, User $user, array $data): void
    {
        if (isset($data['role'])) {
            // Seul un administrateur national peut attribuer le rôle pres_admin.
            abort_if($data['role'] === User::ROLE_PRES_ADMIN && ! $request->user()->isNational(), 403, 'Rôle réservé aux administrateurs nationaux.');
            $user->syncRoles([Role::findByName($data['role'], 'web')]);
        }
        if (! empty($data['region_id']) && ! $request->user()->isNational()) {
            $accessible = $request->user()->accessibleDistrictIds();
            $regionDistricts = \App\Models\District::where('region_id', $data['region_id'])->pluck('id')->all();
            abort_unless(empty(array_diff($regionDistricts, $accessible)), 403, 'Région hors périmètre.');
        }
        if (isset($data['district_ids'])) {
            $accessible = $request->user()->accessibleDistrictIds();
            if ($accessible !== null) {
                abort_unless(empty(array_diff($data['district_ids'], $accessible)), 403, 'District hors périmètre.');
            }
            $user->districts()->sync($data['district_ids']);
        }
    }
}
