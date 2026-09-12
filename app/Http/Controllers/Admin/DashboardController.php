<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AudioConversion;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'total_users' => User::count(),
                'active_users' => User::where('status', UserStatus::Active)->count(),
                // Unique source files vs. total conversion runs; both are 0
                // until the converter ships.
                'total_audio' => AudioConversion::distinct()->count('original_filename'),
                'total_conversions' => AudioConversion::count(),
                'conversions_today' => AudioConversion::whereDate('created_at', today())->count(),
            ],
        ]);
    }
}
