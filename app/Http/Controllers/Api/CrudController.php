<?php

namespace App\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * CRUD générique cloisonné par district. Les sous-classes déclarent le modèle, le préfixe de permission
 * (view_/create_/update_/delete_{permission}), les filtres autorisés et les règles de validation.
 */
abstract class CrudController extends ApiController
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $permission;

    /** Filtres d'égalité autorisés dans la query string. */
    protected array $filters = [];

    protected array $with = [];

    protected string $orderBy = 'created_at';

    /** Règles de validation (champs hors district_id). */
    abstract protected function rules(?Model $record): array;

    public function index(Request $request)
    {
        $this->requirePermission($request, "view_{$this->permission}");

        $query = $this->scoped($request, $this->model::query()->with($this->with));
        foreach ($this->filters as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->query($filter));
            }
        }
        if ($request->filled('district_id')) {
            $this->assertDistrictAccess($request, $request->query('district_id'));
            $query->where('district_id', $request->query('district_id'));
        }

        return $query->orderByDesc($this->orderBy)->paginate($this->perPage($request));
    }

    public function show(Request $request, string $id)
    {
        $this->requirePermission($request, "view_{$this->permission}");

        return $this->find($request, $id)->load($this->with);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, "create_{$this->permission}");

        $accessible = $request->user()->accessibleDistrictIds();
        $data = $request->validate($this->rules(null) + [
            'district_id' => ['required', 'uuid', Rule::exists('districts', 'id')],
        ]);
        $this->assertDistrictAccess($request, $data['district_id']);

        $record = new $this->model;
        if (Schema::hasColumn($record->getTable(), 'owner_id')) {
            $data += ['owner_type' => 'district', 'owner_id' => $data['district_id']];
        }
        $record->fill($data)->save();

        return response()->json($record->fresh()->load($this->with), 201);
    }

    public function update(Request $request, string $id)
    {
        $this->requirePermission($request, "update_{$this->permission}");

        $record = $this->find($request, $id);
        $data = $request->validate($this->rules($record));
        $record->fill($data);
        if (Schema::hasColumn($record->getTable(), 'version')) {
            $record->version = $record->version + 1;
        }
        $record->save();

        return $record->fresh()->load($this->with);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->requirePermission($request, "delete_{$this->permission}");

        $this->find($request, $id)->delete();

        return response()->json(null, 204);
    }

    protected function find(Request $request, string $id): Model
    {
        return $this->scoped($request, $this->model::query())->findOrFail($id);
    }

    /** Règle d'existence limitée à un district accessible (évite les références croisées). */
    protected function existsInScope(Request $request, string $table): \Illuminate\Validation\Rules\Exists
    {
        $ids = $request->user()->accessibleDistrictIds();

        return Rule::exists($table, 'id')->where(fn ($q) => $ids === null ? $q : $q->whereIn('district_id', $ids));
    }
}
