<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isAdmin();
    }

    /**
     * Admins can delete other admins, but never themselves, and
     * never the last remaining admin account — otherwise nobody
     * would be able to log in to the panel again.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        if ($user->id === $model->id) {
            return false;
        }

        return User::where('role', 'admin')->count() > 1;
    }

    /**
     * Bulk deletion is disabled at the resource level, but this is
     * a second line of defense in case that ever changes.
     */
    public function deleteAny(User $user): bool
    {
        return false;
    }
}
