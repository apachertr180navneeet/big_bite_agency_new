@extends('admin.layouts.app')

@section('style')
@endsection

@section('content')
<div class="container-fluid flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-md-6 text-start">
            <h5 class="py-2 mb-2">
                <span class="text-primary fw-light">Receipt Management</span>
            </h5>
        </div>

        <div class="col-md-6 text-end">
            <a href="{{ route('admin.receipt.index') }}" class="btn btn-primary">Back</a>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12 col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="mb-4">
                        <h5 class="card-title"><span class="text-primary fw-bold">Edit Receipt</span></h5>
                        <hr>
                    </div>

                    <form id="receiptForm" data-mode="edit" data-current-invoice-id="{{ $receipt->invoice_id }}" data-current-given="{{ $receipt->given_amount }}" action="{{ route('admin.receipt.update', $receipt->id) }}" method="POST">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" name="date" class="form-control" value="{{ old('date', $receipt->date ? \Illuminate\Support\Carbon::parse($receipt->date)->format('Y-m-d') : '') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Receipt No. <span class="text-danger">*</span></label>
                                <input type="text" name="receipt_no" id="receipt_no" class="form-control" value="{{ old('receipt_no', $receipt->receipt_no) }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Firm Name <span class="text-danger">*</span></label>
                                <select name="firm_id" id="firm_id" class="form-select">
                                    <option value="">Select Firm</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}" data-discount="{{ $customer->discount }}" {{ (string) old('firm_id', $receipt->firm_id) === (string) $customer->id ? 'selected' : '' }}>
                                            {{ $customer->firm_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Invoice <span class="text-danger">*</span></label>
                                <select name="invoice_id" class="form-select" id="invoice_id">
                                    <option value="">Select Invoice</option>
                                    @foreach ($invoices as $invoice)
                                        <option value="{{ $invoice->id }}" data-amount="{{ $invoice->amount }}" data-firm="{{ $invoice->firm_id }}" data-sales-person="{{ optional($invoice->salesperson)->name }}" data-paid="{{ number_format((float) ($invoice->paid_amount ?? 0), 2, '.', '') }}" {{ (string) old('invoice_id', $receipt->invoice_id) === (string) $invoice->id ? 'selected' : '' }}>
                                            {{ $invoice->invoice_no }} ({{ strtoupper($invoice->status) }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Invoice Amount</label>
                                <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-control" value="{{ old('amount', $receipt->amount) }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Discount (%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="discount" id="discount" class="form-control" value="{{ old('discount', $receipt->discount) }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Net Payable</label>
                                <input type="number" step="0.01" min="0" name="final_amount" id="final_amount" class="form-control" value="{{ old('final_amount', $receipt->final_amount) }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Remaining Amount</label>
                                <input type="number" step="0.01" min="0" id="remaining_amount" class="form-control" value="" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Given Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="given_amount" id="given_amount" class="form-control" value="{{ old('given_amount', $receipt->given_amount) }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sales Person</label>
                                <input type="text" name="sales_person" id="sales_person" class="form-control" placeholder="Auto from invoice" value="{{ old('sales_person', $receipt->sales_person) }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Mode</label>
                                <select name="mode" class="form-select">
                                    <option value="">Select Mode</option>
                                    <option value="cash" {{ old('mode', $receipt->mode) === 'cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="upi" {{ old('mode', $receipt->mode) === 'upi' ? 'selected' : '' }}>UPI</option>
                                    <option value="bank" {{ old('mode', $receipt->mode) === 'bank' ? 'selected' : '' }}>Bank</option>
                                    <option value="card" {{ old('mode', $receipt->mode) === 'card' ? 'selected' : '' }}>Card</option>
                                </select>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Update Receipt</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    const indexReceiptUrl = "{{ route('admin.receipt.index') }}";
    const createReceiptUrl = "{{ route('admin.receipt.create') }}";
</script>
<script src="{{ asset('assets/admin/customjs/receipt/index.js') }}"></script>
@endsection

