<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminCommissionWalletExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class CommissionWalletController extends Controller
{
    public function export(Request $request)
    {
        $filters = $request->only([
            'search', 'category', 'entry_type', 'reason',
            'accommodation_id', 'city_id', 'service_catalog_id',
            'booking_source', 'booking_status', 'sign',
            'date_from', 'date_to',
            'commission_min', 'commission_max',
            'transaction_min', 'transaction_max',
            'sort', 'dir',
        ]);

        $filename = 'commission-wallet-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new AdminCommissionWalletExport($filters), $filename);
    }
}
