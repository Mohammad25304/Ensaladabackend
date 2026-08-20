<?php

namespace App\Policies;

use App\Models\menu_item;
use App\Models\User;

class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, menu_item $menuItem): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, menu_item $menuItem): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, menu_item $menuItem): bool
    {
        return $user->isAdmin();
    }
}