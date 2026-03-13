<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\Salesperson;

class InvoiceController extends Controller
{
    /**
     * Sales Person index page
     *
     * @return void
     */
    public function index(){
       return view("admin.invoice.index");
    }

    /**
     * ---------------------------------------------------------
     * Fetch All Invoice Data (With Search + Pagination)
     * ---------------------------------------------------------
     */
    public function getall(Request $request)
    {
        $query = Invoice::query()->with([
            'firm:id,firm_name',
            'salesperson:id,name'
        ]);

        /**
         * ---------------------------------------------------------
         * Global Search (Invoice No + Date)
         * ---------------------------------------------------------
         */
        if ($request->has('search') && !empty($request->search['value'])) {
            $search = $request->search['value'];

            $query->where(function ($q) use ($search) {
                $q->where('invoice_no', 'like', "%{$search}%")
                  ->orWhere('date', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhereHas('firm', function ($firmQuery) use ($search) {
                      $firmQuery->where('firm_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('salesperson', function ($salespersonQuery) use ($search) {
                      $salespersonQuery->where('name', 'like', "%{$search}%");
                  });
            });
        }

        /**
         * ---------------------------------------------------------
         * Extra Filters (Invoice Number + Date Range)
         * ---------------------------------------------------------
         */
        if ($request->filled('invoice_no')) {
            $query->where('invoice_no', 'like', '%' . $request->invoice_no . '%');
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('date', [$request->date_from, $request->date_to]);
        } elseif ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        } elseif ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        /**
         * ---------------------------------------------------------
         * Total Records Count (Before Filtering)
         * ---------------------------------------------------------
         */
        $totalRecords = Invoice::count();

        /**
         * ---------------------------------------------------------
         * Filtered Records Count
         * ---------------------------------------------------------
         */
        $filteredRecords = $query->count();

        /**
         * ---------------------------------------------------------
         * Pagination
         * ---------------------------------------------------------
         */
        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 10);

        $query = $query->orderBy('id', 'desc');

        if ($length === -1) {
            // DataTables uses -1 to request all rows
            $invoice = $query->skip($start)->get();
        } else {
            $length = $length > 0 ? $length : 10;
            $invoice = $query->skip($start)->take($length)->get();
        }

        $invoice = $invoice->map(function ($item) {
            $item->firm_name = optional($item->firm)->firm_name;
            $item->salesperson_name = optional($item->salesperson)->name;
            return $item;
        });

        /**
         * ---------------------------------------------------------
         * Return JSON for DataTable
         * ---------------------------------------------------------
         */
        return response()->json([
            "draw" => intval($request->draw),
            "recordsTotal" => $totalRecords,
            "recordsFiltered" => $filteredRecords,
            "data" => $invoice,
        ]);
    }

    /**
     * Invoice create page.
     */
    public function create()
    {
        $customers = Customer::query()
            ->where('status', 'active')
            ->orderBy('firm_name')
            ->get(['id', 'firm_name']);

        $salespersons = Salesperson::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view("admin.invoice.create", compact('customers', 'salespersons'));
    }


    /**
     * Store a newly created invoice in database.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'invoice_no' => 'required|string|max:100|unique:invoices,invoice_no',
            'firm_id' => 'required|exists:customers,id',
            'salesperson_id' => 'required|exists:salespersons,id',
            'amount' => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $amount = $request->amount;
        $discountPercent = $request->discount_percent ?? 0;

        $discountAmount = ($amount * $discountPercent) / 100;
        $payableAmount = $amount - $discountAmount;

        $invoice = Invoice::create([
            'date' => $request->date,
            'invoice_no' => $request->invoice_no,
            'firm_id' => $request->firm_id,
            'salesperson_id' => $request->salesperson_id,
            'amount' => $amount,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'payable_amount' => $payableAmount,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Invoice added successfully',
            'data' => $invoice,
        ]);
    }
    /**
     * Delete invoice
     */
    public function delete($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->delete();

        return response()->json([
            'status' => true,
            'message' => 'Invoice deleted successfully'
        ]);
    }

    /**
     * Invoice edit page.
     */
    public function edit($id)
    {
        $invoice = Invoice::findOrFail($id);

        $customers = Customer::query()
            ->where('status', 'active')
            ->orderBy('firm_name')
            ->get(['id', 'firm_name']);

        $salespersons = Salesperson::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view("admin.invoice.edit", compact('invoice', 'customers', 'salespersons'));
    }

    /**
     * Update invoice
     */
    public function update(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);

        $request->validate([
            'date' => 'required|date',
            'invoice_no' => 'required|string|max:100|unique:invoices,invoice_no,' . $id,
            'firm_id' => 'required|exists:customers,id',
            'salesperson_id' => 'required|exists:salespersons,id',
            'amount' => 'required|numeric|min:0',
        ]);

        $amount = $request->amount;
        $discountPercent = $request->discount_percent ?? 0;

        $discountAmount = ($amount * $discountPercent) / 100;
        $payableAmount = $amount - $discountAmount;

        $invoice->update([
            'date' => $request->date,
            'invoice_no' => $request->invoice_no,
            'firm_id' => $request->firm_id,
            'salesperson_id' => $request->salesperson_id,
            'amount' => $amount,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'payable_amount' => $payableAmount,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Invoice updated successfully',
            'data' => $invoice,
        ]);
    }


}
