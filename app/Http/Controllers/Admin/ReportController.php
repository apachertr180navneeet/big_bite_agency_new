<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Salesperson;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function salespersionreport(Request $request)
    {
        $salesmanId = $request->salesman_id;

        // Active Salesmen for dropdown
        $salesmen = Salesperson::where('status', 'active')->get();

        // Invoice Report with Receipt Deduction
        $query = Invoice::select(
                'invoices.id',
                'invoices.invoice_no',
                'customers.firm_name',
                'salespersons.name as salesman_name',
                'invoices.payable_amount',
                DB::raw('COALESCE(SUM(receipts.given_amount),0) as received_amount'),
                DB::raw('(invoices.payable_amount - COALESCE(SUM(receipts.given_amount),0)) as remaining_amount')
            )
            ->join('customers', 'customers.id', '=', 'invoices.firm_id')
            ->join('salespersons', 'salespersons.id', '=', 'invoices.salesperson_id')

            // Left join receipts
            ->leftJoin('receipts', 'receipts.invoice_id', '=', 'invoices.id')

            ->where('invoices.status', 'pending')

            ->groupBy(
                'invoices.id',
                'invoices.invoice_no',
                'customers.firm_name',
                'salespersons.name',
                'invoices.payable_amount'
            );

        // Filter by Salesperson
        if ($request->filled('salesman_id')) {
            $query->where('invoices.salesperson_id', $salesmanId);
        }

        $reports = $query->get();

        // Total Remaining Amount
        $totalAmount = $reports->sum('remaining_amount');

        return view('admin.report.salesman', compact('reports','salesmen','salesmanId','totalAmount'));
    }


    public function caashReport(Request $request)
    {
        $date = $request->date ?? Carbon::today()->toDateString();

        // Detail Records
        $reports = DB::table('receipts')
            ->join('invoices', 'receipts.invoice_id', '=', 'invoices.id')
            ->join('customers', 'invoices.firm_id', '=', 'customers.id')
            ->join('salespersons', 'invoices.salesperson_id', '=', 'salespersons.id')
            ->select(
                'receipts.receipt_no',
                'customers.firm_name',
                'salespersons.name as salesman_name',

                DB::raw("SUM(CASE WHEN receipts.mode='cash' THEN receipts.given_amount ELSE 0 END) as cash_total"),
                DB::raw("SUM(CASE WHEN receipts.mode='cheque' THEN receipts.given_amount ELSE 0 END) as cheque_total"),
                DB::raw("SUM(CASE WHEN receipts.mode='upi' THEN receipts.given_amount ELSE 0 END) as upi_total"),
                DB::raw("SUM(CASE WHEN receipts.mode='rtgs' THEN receipts.given_amount ELSE 0 END) as rtgs_total")
            )
            ->whereDate('receipts.created_at', $date)
            ->groupBy(
                'receipts.receipt_no',
                'customers.firm_name',
                'salespersons.name'
            )
            ->get();

        return view('admin.report.cash', compact('reports','date'));
    }

    
}