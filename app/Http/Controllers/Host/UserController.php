<?php

namespace App\Http\Controllers\Host;

use App\Exports\HostUsersExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    public function export(Request $request)
    {
        $filters = $request->only([
            'search', 'user_type', 'province_id', 'veteran_type',
            'accommodation_id', 'bookings_min', 'has_bookings', 'sort',
        ]);

        $accommodationIds = Auth::user()->managedAccommodationIds()->all();

        return Excel::download(
            new HostUsersExport($filters, $accommodationIds),
            'host-users.xlsx'
        );
    }
}
