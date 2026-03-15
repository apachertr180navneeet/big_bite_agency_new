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
            <a href="{{ route('admin.receipt.create') }}" class="btn btn-primary">Add Receipt</a>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-12 col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-2">
                            <label for="filter_receipt_no" class="form-label">Receipt Number</label>
                            <input type="text" id="filter_receipt_no" class="form-control" placeholder="Enter receipt number">
                        </div>
                        <div class="col-md-2">
                            <label for="filter_date_from" class="form-label">Start Date</label>
                            <input type="date" id="filter_date_from" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label for="filter_date_to" class="form-label">End Date</label>
                            <input type="date" id="filter_date_to" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label for="filter_mode" class="form-label">Mode</label>
                            <select id="filter_mode" class="form-select">
                                <option value="">All</option>
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="bank">Bank</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filter_manager_status" class="form-label">Manager Status</label>
                            <select id="filter_manager_status" class="form-select">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="accpet">Accept</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="filter_status" class="form-label">Status</label>
                            <select id="filter_status" class="form-select">
                                <option value="">All</option>
                                <option value="pending">Pending</option>
                                <option value="accpet">Accept</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-12 d-flex align-items-end gap-2">
                            <button type="button" id="applyReceiptFilters" class="btn btn-primary">Apply</button>
                            <button type="button" id="resetReceiptFilters" class="btn btn-outline-secondary">Reset</button>
                        </div>
                    </div>

                    <div class="table-responsive text-nowrap">
                        <table class="table table-bordered" id="receiptTable">
                            <thead>
                                <tr>
                                    <th>Receipt No</th>
                                    <th>Date</th>
                                    <th>Firm</th>
                                    <th>Invoice No</th>
                                    <th>Amount</th>
                                    <th>Given</th>
                                    <th>Final</th>
                                    <th>Mode</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    const getReceiptUrl = "{{ route('admin.receipt.getall') }}";
    const indexReceiptUrl = "{{ route('admin.receipt.index') }}";
    const createReceiptUrl = "{{ route('admin.receipt.create') }}";
    const deleteReceiptUrl = "{{ route('admin.receipt.delete', ':id') }}";
    const editReceiptUrl = "{{ route('admin.receipt.edit', ':id') }}";
    const updateReceiptStatusUrl = "{{ route('admin.receipt.status', ':id') }}";
</script>
<script src="{{ asset('assets/admin/customjs/receipt/index.js') }}"></script>
@endsection
