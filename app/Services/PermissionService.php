<?php

namespace App\Services;

use App\Enums\PermissionAction;
use App\Enums\PermissionModule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionService
{
    public function list(Request $request): LengthAwarePaginator
    {
        $query = Permission::where('guard_name', RoleService::GUARD)->with('roles:id,name,label');

        if ($search = trim((string) $request->input('q'))) {
            $modules = $this->keysMatching(PermissionModule::cases(), $search);
            $actions = $this->keysMatching(PermissionAction::cases(), $search);

            $query->where(function (Builder $inner) use ($search, $modules, $actions) {
                $inner->where('name', 'like', "%{$search}%");

                foreach ($modules as $module) {
                    $inner->orWhere('name', 'like', "{$module}.%");
                }

                foreach ($actions as $action) {
                    $inner->orWhere('name', 'like', "%.{$action}");
                }
            });
        }

        if ($request->filled('module')) {
            $query->where('name', 'like', $request->input('module').'.%');
        }

        if ($request->filled('action')) {
            $query->where('name', 'like', '%.'.$request->input('action'));
        }

        if ($request->filled('role_id')) {
            $query->whereHas('roles', fn (Builder $role) => $role->whereKey($request->input('role_id')));
        }

        return $query
            ->orderBy('id')
            ->paginate(max(1, min(100, (int) $request->input('per_page', 15))))
            ->appends($request->query());
    }

    /**
     * The Arabic labels live in the enums, so a search for «الطلبات» has to be
     * translated back into the keys the permission names are built from.
     *
     * @param  array<int, PermissionModule|PermissionAction>  $cases
     * @return array<int, string>
     */
    private function keysMatching(array $cases, string $search): array
    {
        return collect($cases)
            ->filter(fn ($case) => str_contains($case->label(), $search))
            ->map(fn ($case) => $case->value)
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'modules' => collect(PermissionModule::cases())
                ->map(fn (PermissionModule $module) => ['key' => $module->value, 'label' => $module->label()])
                ->all(),
            'actions' => collect(PermissionAction::cases())
                ->map(fn (PermissionAction $action) => ['key' => $action->value, 'label' => $action->label()])
                ->all(),
        ];
    }
}
