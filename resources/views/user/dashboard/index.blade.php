@extends('user.layouts.app')
@section('style')
<style>
    .pending-invoice-card {
        border: 1px solid #e7e7ff;
        border-radius: 0.75rem;
        padding: 1rem;
        background: #fff;
    }

    .pending-invoice-card + .pending-invoice-card {
        margin-top: 1rem;
    }

    .pending-invoice-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        color: #8592a3;
        letter-spacing: 0.04em;
    }

    .pending-invoice-value {
        font-size: 0.95rem;
        font-weight: 600;
        color: #566a7f;
    }
</style>
@endsection  

@section('content')

<!-- Content -->

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-lg-8 mb-4 order-0">
            <div class="card">
                <div class="d-flex align-items-end row">
                    <div class="col-sm-7">
                        <div class="card-body">
                            <h5 class="card-title text-primary">
                                {{ Auth::guard('sales')->user()->name }}! 🎉
                            </h5>
                            <p class="mb-4">
                                Welcome to your dashboard
                            </p>
                        </div>
                    </div>
                    <div class="col-sm-12 text-center text-sm-left">
                        <div class="card-body pb-0 px-0 px-md-4">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 order-1">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0">Pending Invoices</h5>
                    </div>
                    <span class="badge bg-label-primary">{{ $pendingInvoices->count() }} Total</span>
                </div>

                <div class="card-body">
                    @if($pendingInvoices->isEmpty())
                        <div class="text-center py-4 text-muted">
                            No pending invoices found.
                        </div>
                    @else
                        <div class="d-md-none">
                            @foreach($pendingInvoices as $invoice)
                                <div class="pending-invoice-card">
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <div class="pending-invoice-label">Invoice No</div>
                                            <div class="pending-invoice-value">{{ $invoice->invoice_no }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="pending-invoice-label">Date</div>
                                            <div class="pending-invoice-value">
                                                {{ \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') }}
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="pending-invoice-label">Firm Name</div>
                                            <div class="pending-invoice-value">{{ optional($invoice->firm)->firm_name ?? '-' }}</div>
                                        </div>
                                        <div class="col-6">
                                            <div class="pending-invoice-label">Status</div>
                                            <div class="pending-invoice-value text-warning text-capitalize">
                                                {{ $invoice->status }}
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="pending-invoice-label">Remaining Amount</div>
                                            <div class="pending-invoice-value">
                                                {{ number_format((float) $invoice->remaining_amount, 2) }}
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <a href="{{ route('user.receipt.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-sm btn-primary w-100">
                                                Create Receipt
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="table-responsive d-none d-md-block">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Invoice No</th>
                                        <th>Firm Name</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Remaining Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pendingInvoices as $invoice)
                                        <tr>
                                            <td>{{ $invoice->invoice_no }}</td>
                                            <td>{{ optional($invoice->firm)->firm_name ?? '-' }}</td>
                                            <td>{{ \Carbon\Carbon::parse($invoice->date)->format('d-m-Y') }}</td>
                                            <td>
                                                <span class="badge bg-label-warning text-capitalize">
                                                    {{ $invoice->status }}
                                                </span>
                                            </td>
                                            <td>{{ number_format((float) $invoice->remaining_amount, 2) }}</td>
                                            <td>
                                                <a href="{{ route('user.receipt.create', ['invoice_id' => $invoice->id]) }}" class="btn btn-sm btn-primary">
                                                    Create Receipt
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
<!-- / Content -->

<!-- Footer -->

<!-- / Footer -->

                   
@endsection

@section('script')

@endsection
