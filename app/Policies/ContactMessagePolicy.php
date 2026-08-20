<?php

namespace App\Policies;

use App\Models\ContactMessage;
use App\Models\User;

class ContactMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, ContactMessage $contactMessage): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false; // messages only ever come from the public contact form
    }

    public function update(User $user, ContactMessage $contactMessage): bool
    {
        return $user->isAdmin(); // used for the "mark as read" update
    }

    public function delete(User $user, ContactMessage $contactMessage): bool
    {
        return $user->isAdmin();
    }
}