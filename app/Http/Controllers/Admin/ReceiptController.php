<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Receipt;

class ReceiptController extends Controller
{
    public function index()
    {
        return view('admin.receipt.index');
    }

    public function getall(Request $request)
    {
        $query = Receipt::query()->with([
            'firm:id,firm_name',
            'invoice:id,invoice_no',
        ]);

        if ($request->has('search') && !empty($request->search['value'])) {
            $search = $request->search['value'];

            $query->where(function ($q) use ($search) {
                $q->where('receipt_no', 'like', "%{$search}%")
                    ->orWhere('date', 'like', "%{$search}%")
                    ->orWhere('mode', 'like', "%{$search}%")
                    ->orWhere('sales_person', 'like', "%{$search}%")
                    ->orWhere('manager_status', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhereHas('firm', function ($firmQuery) use ($search) {
                        $firmQuery->where('firm_name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('invoice', function ($invoiceQuery) use ($search) {
                        $invoiceQuery->where('invoice_no', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('receipt_no')) {
            $query->where('receipt_no', 'like', '%' . $request->receipt_no . '%');
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('date', [$request->date_from, $request->date_to]);
        } elseif ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->date_from);
        } elseif ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->date_to);
        }

        if ($request->filled('mode')) {
            $query->where('mode', $request->mode);
        }

        if ($request->filled('manager_status')) {
            $query->where('manager_status', $request->manager_status);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $totalRecords = Receipt::count();
        $filteredRecords = $query->count();

        $start = max((int) $request->input('start', 0), 0);
        $length = (int) $request->input('length', 10);

        $query = $query->orderBy('id', 'desc');

        if ($length === -1) {
            $receipts = $query->skip($start)->get();
        } else {
            $length = $length > 0 ? $length : 10;
            $receipts = $query->skip($start)->take($length)->get();
        }

        $receipts = $receipts->map(function ($item) {
            $item->firm_name = optional($item->firm)->firm_name;
            $item->invoice_no = optional($item->invoice)->invoice_no;
            return $item;
        });

        return response()->json([
            'draw' => intval($request->draw),
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $filteredRecords,
            'data' => $receipts,
        ]);
    }

    public function create()
    {
        $customers = Customer::query()
            ->where('status', 'active')
            ->orderBy('firm_name')
            ->get(['id', 'firm_name', 'discount']);

        $invoices = Invoice::query()
            ->with('salesperson:id,name')
            ->withSum('receipts as paid_amount', 'given_amount')
            ->orderBy('invoice_no')
            ->get(['id', 'firm_id', 'invoice_no', 'amount', 'status', 'salesperson_id']);

        $generatedReceiptNo = $this->generateReceiptNo();

        return view('admin.receipt.create', compact('customers', 'invoices', 'generatedReceiptNo'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'receipt_no' => 'nullable|string|max:100|unique:receipts,receipt_no',
            'firm_id' => 'required|exists:customers,id',
            'invoice_id' => 'required|exists:invoices,id',
            'given_amount' => 'required|numeric|min:0.01',
            'mode' => 'nullable|string|max:100',
            'manager_status' => 'nullable|in:pending,accpet,rejected',
            'status' => 'nullable|in:pending,accpet,rejected',
        ]);

        [$customer, $invoice] = $this->resolveCustomerAndInvoice($request->firm_id, $request->invoice_id);
        [$invoiceAmount, $discountPercent, $payableAmount] = $this->invoiceFinancials($invoice, $customer);

        $totalPaidBefore = (float) Receipt::where('invoice_id', $invoice->id)->sum('given_amount');
        $newTotalPaid = round($totalPaidBefore + (float) $request->given_amount, 2);

        if ($newTotalPaid > $payableAmount + 0.0001) {
            throw ValidationException::withMessages([
                'given_amount' => 'Payment exceeds pending invoice amount.',
            ]);
        }

        $receiptNo = $request->filled('receipt_no') ? $request->receipt_no : $this->generateReceiptNo();

        $receipt = Receipt::create([
            'date' => $request->date,
            'receipt_no' => $receiptNo,
            'firm_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => $invoiceAmount,
            'given_amount' => $request->given_amount,
            'discount' => $discountPercent,
            'final_amount' => $payableAmount,
            'sales_person' => optional($invoice->salesperson)->name,
            'mode' => $request->mode,
            'manager_status' => $request->manager_status ?? 'pending',
            'status' => $request->status ?? 'pending',
        ]);

        $this->updateInvoiceStatus($invoice->id);

        if ($request->ajax()) {
            return response()->json([
                'status' => true,
                'message' => 'Receipt added successfully',
                'data' => $receipt,
            ]);
        }

        return redirect()->route('admin.receipt.index')->with('success', 'Receipt added successfully');
    }

    public function delete($id)
    {
        $receipt = Receipt::findOrFail($id);
        $invoiceId = $receipt->invoice_id;
        $receipt->delete();

        if ($invoiceId) {
            $this->updateInvoiceStatus((int) $invoiceId);
        }

        return response()->json([
            'status' => true,
            'message' => 'Receipt deleted successfully',
        ]);
    }


    public function changeStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,accpet,rejected',
        ]);

        $receipt = Receipt::findOrFail($id);
        $receipt->status = $request->status;
        $receipt->manager_status = $request->status;
        $receipt->save();

        return response()->json([
            'status' => true,
            'message' => 'Receipt status updated successfully',
            'data' => [
                'status' => $receipt->status,
                'manager_status' => $receipt->manager_status,
            ],
        ]);
    }
    public function edit($id)
    {
        $receipt = Receipt::findOrFail($id);

        $customers = Customer::query()
            ->where('status', 'active')
            ->orderBy('firm_name')
            ->get(['id', 'firm_name', 'discount']);

        $invoices = Invoice::query()
            ->with('salesperson:id,name')
            ->withSum('receipts as paid_amount', 'given_amount')
            ->orderBy('invoice_no')
            ->get(['id', 'firm_id', 'invoice_no', 'amount', 'status', 'salesperson_id']);

        return view('admin.receipt.edit', compact('receipt', 'customers', 'invoices'));
    }

    public function update(Request $request, $id)
    {
        $receipt = Receipt::findOrFail($id);
        $oldInvoiceId = (int) $receipt->invoice_id;

        $request->validate([
            'date' => 'required|date',
            'receipt_no' => 'required|string|max:100|unique:receipts,receipt_no,' . $id,
            'firm_id' => 'required|exists:customers,id',
            'invoice_id' => 'required|exists:invoices,id',
            'given_amount' => 'required|numeric|min:0.01',
            'mode' => 'nullable|string|max:100',
            'manager_status' => 'nullable|in:pending,accpet,rejected',
            'status' => 'nullable|in:pending,accpet,rejected',
        ]);

        [$customer, $invoice] = $this->resolveCustomerAndInvoice($request->firm_id, $request->invoice_id);
        [$invoiceAmount, $discountPercent, $payableAmount] = $this->invoiceFinancials($invoice, $customer);

        $totalPaidOthers = (float) Receipt::where('invoice_id', $invoice->id)
            ->where('id', '!=', $receipt->id)
            ->sum('given_amount');

        $newTotalPaid = round($totalPaidOthers + (float) $request->given_amount, 2);

        if ($newTotalPaid > $payableAmount + 0.0001) {
            throw ValidationException::withMessages([
                'given_amount' => 'Payment exceeds pending invoice amount.',
            ]);
        }

        $receipt->update([
            'date' => $request->date,
            'receipt_no' => $request->receipt_no,
            'firm_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => $invoiceAmount,
            'given_amount' => $request->given_amount,
            'discount' => $discountPercent,
            'final_amount' => $payableAmount,
            'sales_person' => optional($invoice->salesperson)->name,
            'mode' => $request->mode,
            'manager_status' => $request->manager_status ?? 'pending',
            'status' => $request->status ?? 'pending',
        ]);

        $this->updateInvoiceStatus($invoice->id);
        if ($oldInvoiceId && $oldInvoiceId !== (int) $invoice->id) {
            $this->updateInvoiceStatus($oldInvoiceId);
        }

        return response()->json([
            'status' => true,
            'message' => 'Receipt updated successfully',
            'data' => $receipt,
        ]);
    }

    private function resolveCustomerAndInvoice(int $firmId, int $invoiceId): array
    {
        $customer = Customer::findOrFail($firmId);

        $invoice = Invoice::with('salesperson:id,name')
            ->where('id', $invoiceId)
            ->where('firm_id', $firmId)
            ->first();

        if (!$invoice) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Selected invoice does not belong to selected firm.',
            ]);
        }

        return [$customer, $invoice];
    }

    private function invoiceFinancials(Invoice $invoice, Customer $customer): array
    {
        $invoiceAmount = round((float) $invoice->amount, 2);
        $discountPercent = round((float) $customer->discount, 2);
        $payableAmount = round($invoiceAmount - (($invoiceAmount * $discountPercent) / 100), 2);

        return [$invoiceAmount, $discountPercent, $payableAmount];
    }

    private function updateInvoiceStatus(int $invoiceId): void
    {
        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            return;
        }

        $customer = Customer::find($invoice->firm_id);
        $discountPercent = $customer ? (float) $customer->discount : 0;

        $invoiceAmount = round((float) $invoice->amount, 2);
        $payableAmount = round($invoiceAmount - (($invoiceAmount * $discountPercent) / 100), 2);
        $paid = (float) Receipt::where('invoice_id', $invoice->id)->sum('given_amount');

        $invoice->status = $paid + 0.0001 >= $payableAmount ? 'full_paid' : 'pending';
        $invoice->save();
    }

    private function generateReceiptNo(): string
    {
        $lastReceipt = Receipt::query()
            ->where('receipt_no', 'like', 'RCPT-%')
            ->orderByDesc('id')
            ->first(['receipt_no']);

        $nextNumber = 1;

        if ($lastReceipt && preg_match('/^RCPT-(\d+)$/', $lastReceipt->receipt_no, $matches)) {
            $nextNumber = ((int) $matches[1]) + 1;
        }

        return 'RCPT-' . str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
    }
}


