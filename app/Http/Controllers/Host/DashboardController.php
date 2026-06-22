<?php

namespace App\Http\Controllers\Host;

use App\Http\Controllers\Controller;
use App\Services\HostDashboardDataService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $data = app(HostDashboardDataService::class)->build($user);

        return view('host.dashboard', array_merge($data, ['hostUser' => $user]));
    }
}
