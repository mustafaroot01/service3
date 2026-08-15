<?php

namespace App\Services;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminService extends BaseCrudService
{
    protected string $modelClass = Admin::class;

    protected array $searchable = ['name', 'email'];

    protected array $sortable = ['id', 'name', 'email', 'status', 'created_at'];

    protected string $defaultSort = 'created_at';

    protected array $filterable = ['status'];

    protected function baseQuery(): Builder
    {
        return $this->query()->with('roles:id,name,label');
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('role_id')) {
            $query->whereHas('roles', fn (Builder $role) => $role->whereKey($request->input('role_id')));
        }
    }

    public function hydrate(Model $model): Model
    {
        return $model->load('roles:id,name,label');
    }

    public function create(array $data): Model
    {
        $admin = DB::transaction(function () use ($data) {
            $admin = Admin::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => $data['status'] ?? AdminStatus::ACTIVE->value,
            ]);

            $admin->syncRoles([$this->role($data['role_id'])]);

            return $admin;
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->hydrate($admin->refresh());
    }

    public function update(Model $model, array $data): Model
    {
        $this->guardSelfLockout($model, $data);
        $this->guardLastSuperAdmin($model, $data);

        $roleChanged = (int) $data['role_id'] !== (int) $model->loadMissing('roles')->roles->first()?->id;

        DB::transaction(function () use ($model, $data, $roleChanged) {
            $model->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => $data['status'],
            ]);

            // An empty password field means "leave the current one alone".
            if (! empty($data['password'])) {
                $model->password = $data['password'];
            }

            $model->save();
            $model->syncRoles([$this->role($data['role_id'])]);

            /**
             * The status is only read at login, so disabling an admin from this
             * form would lock him out of the door while the session he already
             * has keeps working. A new password and a new role are the same
             * story: both are meant to end the old session, not sit beside it.
             */
            if ($model->status !== AdminStatus::ACTIVE || $roleChanged || ! empty($data['password'])) {
                $model->tokens()->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->hydrate($model->refresh());
    }

    public function delete(Model $model): void
    {
        if ($this->isSelf($model)) {
            throw ValidationException::withMessages([
                'admin' => 'لا يمكنك حذف حسابك',
            ]);
        }

        if ($this->isLastSuperAdmin($model)) {
            throw ValidationException::withMessages([
                'admin' => 'هذا آخر مدير عام، لا يمكن حذفه',
            ]);
        }

        DB::transaction(function () use ($model) {
            $model->tokens()->delete();
            $model->syncRoles([]);
            $model->delete();
        });
    }

    public function changeStatus(Admin $admin, AdminStatus $status): Admin
    {
        if ($this->isSelf($admin) && $status !== AdminStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكنك تعطيل حسابك',
            ]);
        }

        if ($status !== AdminStatus::ACTIVE && $this->isLastSuperAdmin($admin)) {
            throw ValidationException::withMessages([
                'status' => 'هذا آخر مدير عام نشط، لا يمكن تعطيله',
            ]);
        }

        $admin->status = $status;
        $admin->save();

        // A disabled account keeps working until its issued tokens are dropped.
        if ($status !== AdminStatus::ACTIVE) {
            $admin->tokens()->delete();
        }

        return $this->hydrate($admin->refresh());
    }

    private function role(int|string $id): Role
    {
        $role = Role::where('guard_name', RoleService::GUARD)->find($id);

        if (! $role) {
            throw ValidationException::withMessages([
                'role_id' => 'الدور المختار غير موجود',
            ]);
        }

        return $role;
    }

    private function isSelf(Model $admin): bool
    {
        return Auth::guard('admin')->id() === $admin->getKey();
    }

    /** Changing your own role can strip the very permission you are using. */
    private function guardSelfLockout(Model $admin, array $data): void
    {
        if (! $this->isSelf($admin)) {
            return;
        }

        $current = $admin->roles->first();

        if ($current && (int) $current->id !== (int) $data['role_id']) {
            throw ValidationException::withMessages([
                'role_id' => 'لا يمكنك تغيير دور حسابك، اطلب من مدير عام آخر',
            ]);
        }

        if (($data['status'] ?? null) !== AdminStatus::ACTIVE->value) {
            throw ValidationException::withMessages([
                'status' => 'لا يمكنك تعطيل حسابك',
            ]);
        }
    }

    private function guardLastSuperAdmin(Model $admin, array $data): void
    {
        if (! $this->isLastSuperAdmin($admin)) {
            return;
        }

        $keepsRole = (int) $data['role_id'] === (int) $admin->roles->first()?->id;

        if (! $keepsRole || ($data['status'] ?? null) !== AdminStatus::ACTIVE->value) {
            throw ValidationException::withMessages([
                'role_id' => 'هذا آخر مدير عام نشط، لا يمكن تغيير دوره أو تعطيله',
            ]);
        }
    }

    private function isLastSuperAdmin(Model $admin): bool
    {
        if (! $admin->hasRole(RoleService::LOCKED_ROLE)) {
            return false;
        }

        return Admin::role(RoleService::LOCKED_ROLE)
            ->where('status', AdminStatus::ACTIVE)
            ->whereKeyNot($admin->getKey())
            ->doesntExist();
    }
}
