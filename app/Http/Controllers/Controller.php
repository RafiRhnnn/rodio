<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * The signed-in user as the application's own model.
     *
     * Auth::user() and Request::user() are typed as Authenticatable, so
     * isActive(), isAdmin(), homeRoute() and Model::is() do not resolve on
     * them. Every controller reached through this helper is behind 'auth'.
     */
    protected function signedInUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}

