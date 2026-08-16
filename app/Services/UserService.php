<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserService extends BaseCrudService
{
    protected string $modelClass = User::class;

    protected array $searchable = ['name', 'phone'];

    protected array $sortable = ['id', 'name', 'phone', 'status', 'created_at'];

    protected string $defaultSort = 'created_at';

    protected array $filterable = ['status', 'gender', 'governorate_id', 'district_id'];

    protected function baseQuery(): Builder
    {
        return $this->query()
            ->with(['governorate', 'district'])
            ->withCount('orders');
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('deletion_requested')) {
            $request->boolean('deletion_requested')
                ? $query->whereNotNull('deletion_requested_at')
                : $query->whereNull('deletion_requested_at');
        }
    }

    public function hydrate(Model $model): Model
    {
        return $model->load(['governorate', 'district'])->loadCount('orders');
    }

    /** The customer changed his mind, or the admin handled it another way. */
    public function dismissDeletionRequest(User $user): User
    {
        if ($user->deletion_requested_at === null) {
            throw ValidationException::withMessages([
                'account' => 'لا يوجد طلب حذف على هذا الحساب',
            ]);
        }

        // The request is what closed the account, so undoing it reopens it.
        $user->forceFill([
            'status' => UserStatus::ACTIVE,
            'deletion_requested_at' => null,
        ])->save();

        return $this->hydrate($user->refresh());
    }

    public function changeStatus(User $user, UserStatus $status): User
    {
        // The two must move together: leaving the timestamp behind would show
        // a "deletion requested" badge on an account the admin just reopened.
        $user->forceFill([
            'status' => $status,
            'deletion_requested_at' => $status === UserStatus::SCHEDULED_FOR_DELETION
                ? ($user->deletion_requested_at ?? now())
                : null,
        ])->save();

        // The status is only read at login, so a suspended customer would keep
        // ordering with the token he already holds until it is taken away.
        if ($status !== UserStatus::ACTIVE) {
            $user->tokens()->delete();
        }

        return $this->hydrate($user->refresh());
    }

    /**
     * The phone arrives already normalised. Sessions are cut because the login
     * credential just changed under the account's feet.
     */
    public function changePhone(User $user, string $phone): User
    {
        $user->forceFill(['phone' => $phone])->save();
        $user->tokens()->delete();

        return $this->hydrate($user->refresh());
    }
}
