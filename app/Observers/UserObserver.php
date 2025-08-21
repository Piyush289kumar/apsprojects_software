<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\UserNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Filament\Notifications\Notification as FilamentNotification;

class UserObserver
{
    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        //
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Send to the updated user (or Auth::user() if you prefer)
        FilamentNotification::make()
            ->title('Profile Updated')
            ->body('Your profile details have been successfully updated.')
            ->success()
            ->sendToDatabase($user); // saves in notifications table
    }


    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
