<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Salesperson;


use App\Exports\SalesReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

use Carbon\Carbon;

use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function firmLedgerReport(Request $request)
    {
        $firmId = $request->firm_id;

        $firms = Customer::where('status', 'active')
            ->orderBy('firm_name')
            ->get(['id', 'firm_name']);

        $invoiceTotals = Invoice::select(
                'firm_id',
                DB::raw('SUM(COALESCE(payable_amount, amount)) as total_debit')
            )
            ->groupBy('firm_id');

        $receiptTotals = Receipt::select(
                'firm_id',
                DB::raw('SUM(given_amount) as total_credit')
            )
            ->where('status', 'accpet')
            ->groupBy('firm_id');

        $reports = Customer::query()
            ->select(
                'customers.id',
                'customers.firm_name',
                DB::raw('COALESCE(invoice_totals.total_debit, 0) as total_debit'),
                DB::raw('COALESCE(receipt_totals.total_credit, 0) as total_credit'),
                DB::raw('COALESCE(invoice_totals.total_debit, 0) - COALESCE(receipt_totals.total_credit, 0) as balance')
            )
            ->leftJoinSub($invoiceTotals, 'invoice_totals', function ($join) {
                $join->on('invoice_totals.firm_id', '=', 'customers.id');
            })
            ->leftJoinSub($receiptTotals, 'receipt_totals', function ($join) {
                $join->on('receipt_totals.firm_id', '=', 'customers.id');
            })
            ->when($firmId, function ($query) use ($firmId) {
                $query->where('customers.id', $firmId);
            })
            ->orderBy('customers.firm_name')
            ->get();

        $totalDebit = $reports->sum('total_debit');
        $totalCredit = $reports->sum('total_credit');
        $totalBalance = $reports->sum('balance');

        return view('admin.report.firm-ledger', compact(
            'reports',
            'firms',
            'firmId',
            'totalDebit',
            'totalCredit',
            'totalBalance'
        ));
    }

    public function firmLedgerDetailsReport(Request $request)
    {
        $firmId = $request->firm_id;

        $firms = Customer::where('status', 'active')
            ->orderBy('firm_name')
            ->get(['id', 'firm_name']);

        $selectedFirm = null;
        $ledgerEntries = collect();
        $totalPendingAmount = 0;
        $totalBillAmount = 0;
        $totalReceiptAmount = 0;
        $totalDiscountAmount = 0;

        if ($firmId) {
            $selectedFirm = Customer::find($firmId, ['id', 'firm_name', 'phone']);

            $invoiceEntries = Invoice::query()
                ->select(
                    'id',
                    'date',
                    'invoice_no as reference_no',
                    DB::raw("'invoice' as entry_type"),
                    DB::raw('COALESCE(payable_amount, amount) as debit'),
                    DB::raw('0 as credit'),
                    DB::raw('COALESCE(discount_amount, 0) as discount'),
                    DB::raw('NULL as remark')
                )
                ->where('firm_id', $firmId)
                ->get();

            $receiptEntries = Receipt::query()
                ->select(
                    'id',
                    'date',
                    'receipt_no as reference_no',
                    DB::raw("'receipt' as entry_type"),
                    DB::raw('0 as debit'),
                    'given_amount as credit',
                    DB::raw('COALESCE(discount, 0) as discount'),
                    'remark'
                )
                ->where('firm_id', $firmId)
                ->where('status', 'accpet')
                ->get();

            $ledgerEntries = $invoiceEntries
                ->concat($receiptEntries)
                ->sortBy([
                    ['date', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();

            $runningBalance = 0;
            $ledgerEntries = $ledgerEntries->reverse()->values()->map(function ($entry) use (&$runningBalance) {
                $runningBalance += (float) $entry->debit - (float) $entry->credit;
                $entry->running_balance = $runningBalance;

                return $entry;
            })->reverse()->values();

            $totalBillAmount = $invoiceEntries->sum('debit');
            $totalReceiptAmount = $receiptEntries->sum('credit');
            $totalDiscountAmount = $invoiceEntries->sum('discount') + $receiptEntries->sum('discount');
            $totalPendingAmount = $totalBillAmount - $totalReceiptAmount;
        }

        return view('admin.report.firm-ledger-details', compact(
            'firms',
            'firmId',
            'selectedFirm',
            'ledgerEntries',
            'totalPendingAmount',
            'totalBillAmount',
            'totalReceiptAmount',
            'totalDiscountAmount'
        ));
    }

    public function salespersionreport(Request $request)
    {
        $salesmanId = $request->salesman_id;

        // Active Salesmen for dropdown
        $salesmen = Salesperson::where('status', 'active')->get();

        // Invoice Report with Receipt Deduction
        $query = Invoice::select(
                'invoices.id',
                'invoices.invoice_no',
                'invoices.date',
                'customers.firm_name',
                'salespersons.name as salesman_name',
                'invoices.payable_amount',
                DB::raw('COALESCE(SUM(receipts.given_amount),0) as received_amount'),
                DB::raw('(invoices.payable_amount - COALESCE(SUM(receipts.given_amount),0)) as remaining_amount')
            )
            ->join('customers', 'customers.id', '=', 'invoices.firm_id')
            ->join('salespersons', 'salespersons.id', '=', 'invoices.salesperson_id')
            //  IMPORTANT: Filter non-deleted receipts
            ->leftJoin('receipts', function ($join) {
                $join->on('receipts.invoice_id', '=', 'invoices.id')
                    ->whereNull('receipts.deleted_at');
            })
            ->where('invoices.status', 'pending')

            ->groupBy(
                'invoices.id',
                'invoices.invoice_no',
                'customers.firm_name',
                'salespersons.name',
                'invoices.payable_amount'
            )

            // ✅ Add this line
            ->orderBy('customers.firm_name', 'asc')
            ->orderBy('invoices.date', 'asc');

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
                DB::raw("SUM(CASE WHEN receipts.mode='card' THEN receipts.given_amount ELSE 0 END) as cheque_total"),
                DB::raw("SUM(CASE WHEN receipts.mode='upi' THEN receipts.given_amount ELSE 0 END) as upi_total"),
                DB::raw("SUM(CASE WHEN receipts.mode='bank' THEN receipts.given_amount ELSE 0 END) as rtgs_total")
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

    private function getReportData($request)
    {
        $salesmanId = $request->salesman_id;

        $query = Invoice::select(
                'invoices.id',
                'invoices.invoice_no',
                'invoices.date',
                'customers.firm_name',
                'salespersons.name as salesman_name',
                'invoices.payable_amount',

                DB::raw('COALESCE(SUM(receipts.given_amount),0) as received_amount'),

                DB::raw('(invoices.payable_amount - COALESCE(SUM(receipts.given_amount),0)) as remaining_amount')
            )
            ->join('customers', 'customers.id', '=', 'invoices.firm_id')
            ->join('salespersons', 'salespersons.id', '=', 'invoices.salesperson_id')
            //  IMPORTANT: Filter non-deleted receipts
            ->leftJoin('receipts', function ($join) {
                $join->on('receipts.invoice_id', '=', 'invoices.id')
                    ->whereNull('receipts.deleted_at');
            })

            ->where('invoices.status', 'pending')

            ->groupBy(
                'invoices.id',
                'invoices.invoice_no',
                'invoices.date', // ✅ important (missing earlier)
                'customers.firm_name',
                'salespersons.name',
                'invoices.payable_amount'
            )

            ->orderBy('customers.firm_name', 'asc')
            ->orderBy('invoices.date', 'asc');

        // ✅ Filter by Salesperson
        if (!empty($salesmanId)) {
            $query->where('invoices.salesperson_id', $salesmanId);
        }

        return $query->get();
    }

    public function exportExcel(Request $request)
    {
        $reports = $this->getReportData($request);

        return Excel::download(new SalesReportExport($reports), 'sales_report.xlsx');
    }

    public function exportPdf(Request $request)
    {
        $reports = $this->getReportData($request);

        $totalAmount = $reports->sum('remaining_amount');

        $pdf = Pdf::loadView('admin.report.sales_report_pdf', compact('reports', 'totalAmount'));

        return $pdf->download('sales_report.pdf');
    }

    
}
