<?php

namespace App\Policies;

use App\Models\AudioConversion;
use App\Models\User;

/**
 * One place for "may this user touch that conversion". Nothing in a controller
 * or a URL guesses ownership from a filename or path.
 */
class AudioConversionPolicy
{
    public function view(User $user, AudioConversion $conversion): bool
    {
        return $user->isAdmin() || $conversion->user_id === $user->id;
    }

    public function download(User $user, AudioConversion $conversion): bool
    {
        return $this->view($user, $conversion);
    }

    /** Only the owner may cancel, and only while queued (checked in the service). */
    public function cancel(User $user, AudioConversion $conversion): bool
    {
        return $conversion->user_id === $user->id;
    }

    /** Retrying other people's jobs is an admin action. */
    public function retry(User $user, AudioConversion $conversion): bool
    {
        return $user->isAdmin();
    }
}
