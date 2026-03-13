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
                        <h5 class="card-title"><span class="text-primary fw-bold">Add Receipt</span></h5>
                        <hr>
                    </div>

                    <form id="receiptForm" data-mode="create" action="{{ route('admin.receipt.store') }}" method="POST">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Date <span class="text-danger">*</span></label>
                                <input type="date" name="date" class="form-control" value="{{ old('date') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Receipt No. <span class="text-danger">*</span></label>
                                <input type="text" name="receipt_no" id="receipt_no" class="form-control" value="{{ old('receipt_no', $generatedReceiptNo) }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Firm Name <span class="text-danger">*</span></label>
                                <select name="firm_id" id="firm_id" class="form-select">
                                    <option value="">Select Firm</option>
                                    @foreach ($customers as $customer)
                                        <option value="{{ $customer->id }}" data-discount="{{ $customer->discount }}" {{ old('firm_id') == $customer->id ? 'selected' : '' }}>
                                            {{ $customer->firm_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Invoice <span class="text-danger">*</span></label>
                                <select name="invoice_id" class="form-select" id="invoice_id">
                                    <option value="">Select Invoice</option>
                                </select>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Invoice Amount</label>
                                <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-control" value="{{ old('amount') }}" readonly>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Remaining Amount</label>
                                <input type="number" step="0.01" min="0" id="remaining_amount" class="form-control" value="" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Given Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="given_amount" id="given_amount" class="form-control" value="{{ old('given_amount', 0) }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sales Person</label>
                                <input type="text" name="sales_person" id="sales_person" class="form-control" placeholder="Auto from invoice" value="{{ old('sales_person') }}" readonly>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Mode</label>
                                <select name="mode" class="form-select">
                                    <option value="">Select Mode</option>
                                    <option value="cash" {{ old('mode') === 'cash' ? 'selected' : '' }}>Cash</option>
                                    <option value="upi" {{ old('mode') === 'upi' ? 'selected' : '' }}>UPI</option>
                                    <option value="bank" {{ old('mode') === 'bank' ? 'selected' : '' }}>Bank</option>
                                    <option value="card" {{ old('mode') === 'card' ? 'selected' : '' }}>Card</option>
                                </select>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-success">Save Receipt</button>
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
</script>
<script src="{{ asset('assets/admin/customjs/receipt/index.js') }}"></script>
@endsection

