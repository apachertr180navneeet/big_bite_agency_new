<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Salesperson;

class ReportController extends Controller
{
    public function salespersionreport(Request $request)
    {
        $salesmanId = $request->salesman_id;

        // Active Salesmen for dropdown
        $salesmen = Salesperson::where('status', 'active')->get();

        // Invoice Report
        $query = Invoice::select(
                'invoices.invoice_no',
                'customers.firm_name',
                'invoices.payable_amount',
                'salespersons.name as salesman_name'
            )
            ->join('customers', 'customers.id', '=', 'invoices.firm_id')
            ->join('salespersons', 'salespersons.id', '=', 'invoices.salesperson_id')
            ->where('invoices.status', 'pending');

        // Filter by Salesperson if selected
        if ($request->filled('salesman_id')) {
            $query->where('invoices.salesperson_id', $salesmanId);
        }

        $reports = $query->get();

        // Total Payable Amount
        $totalAmount = $reports->sum('payable_amount');

        return view('admin.report.salesman', compact('reports','salesmen','salesmanId','totalAmount'));
    }

    
}