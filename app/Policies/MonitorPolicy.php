<?php

namespace App\Policies;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MonitorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view monitors (but scoped in controller)
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Monitor $monitor): bool
    {
        return $user->hasRole('Super Admin') || $monitor->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true; // All authenticated users can create monitors
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Monitor $monitor): bool
    {
        return $user->hasRole('Super Admin') || $monitor->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Monitor $monitor): bool
    {
        return $user->hasRole('Super Admin') || $monitor->user_id === $user->id;
    }

    /**
     * Determine whether the user can toggle the model.
     */
    public function toggle(User $user, Monitor $monitor): bool
    {
        return $user->hasRole('Super Admin') || $monitor->user_id === $user->id;
    }

    /**
     * Determine whether the user can check the model.
     */
    public function check(User $user, Monitor $monitor): bool
    {
        return $user->hasRole('Super Admin') || $monitor->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Monitor $monitor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Monitor $monitor): bool
    {
        return false;
    }
}
