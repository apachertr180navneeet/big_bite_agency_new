@extends('admin.layouts.app')

@section('style')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    .select2-container {
        width: 100% !important;
    }

    .select2-container .select2-selection--single {
        height: calc(2.25rem + 2px);
        border: 1px solid #d9dee3;
        border-radius: 0.375rem;
        display: flex;
        align-items: center;
    }

    .select2-container .select2-selection--single .select2-selection__rendered {
        color: #566a7f;
        line-height: normal;
        padding-left: 0.75rem;
        padding-right: 2rem;
    }

    .select2-container .select2-selection--single .select2-selection__arrow {
        height: 100%;
        right: 0.5rem;
    }

    .select2-dropdown {
        border-color: #d9dee3;
    }

    .ledger-shell {
        background: #f6f7fb;
        min-height: calc(100vh - 140px);
        padding: 24px;
    }

    .ledger-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }

    .ledger-title {
        color: #5b64f6;
        font-size: 1.5rem;
        font-weight: 500;
        margin: 0;
    }

    .ledger-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(31, 45, 61, 0.08);
        padding: 18px 24px 28px;
    }

    .ledger-filter {
        margin-bottom: 20px;
    }

    .ledger-header {
        text-align: center;
        margin-bottom: 18px;
    }

    .ledger-logo {
        max-width: 220px;
        width: 100%;
        margin-bottom: 8px;
    }

    .ledger-firm,
    .ledger-phone {
        color: #596d86;
        font-size: 1rem;
        font-weight: 500;
        margin-bottom: 6px;
    }

    .ledger-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 14px;
        margin-bottom: 18px;
    }

    .ledger-summary-card {
        border: 1px solid #d8e0ea;
        border-radius: 10px;
        padding: 12px 16px;
        background: #fbfcff;
    }

    .ledger-summary-label {
        color: #7a8ea6;
        font-size: 0.86rem;
        margin-bottom: 4px;
    }

    .ledger-summary-value {
        color: #42566f;
        font-size: 1.15rem;
        font-weight: 600;
    }

    .ledger-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ledger-table th,
    .ledger-table td {
        border: 1px solid #d8e0ea;
        padding: 10px 20px;
        color: #617792;
        font-size: 0.98rem;
        vertical-align: middle;
    }

    .ledger-table th {
        font-weight: 500;
        text-align: left;
        letter-spacing: 0.04em;
    }

    .ledger-table td.text-end,
    .ledger-table th.text-end {
        text-align: right;
    }

    .ledger-empty {
        color: #7f90a7;
        text-align: center;
        padding: 24px 12px;
    }

    @media print {
        .layout-menu,
        .layout-navbar,
        .content-footer,
        .ledger-toolbar,
        .ledger-filter,
        .layout-page::before,
        .buy-now,
        .footer,
        .btn,
        .menu-toggle {
            display: none !important;
        }

        .layout-wrapper,
        .layout-page,
        .content-wrapper,
        .content-body,
        .container-xxl,
        .container-fluid,
        .ledger-shell {
            margin: 0 !important;
            padding: 0 !important;
            background: #fff !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .ledger-card {
            box-shadow: none;
            border-radius: 0;
            padding: 0;
        }
    }

    @media (max-width: 991px) {
        .ledger-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575px) {
        .ledger-summary {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="ledger-shell">
    <div class="ledger-toolbar">
        <h4 class="ledger-title">Ledger</h4>
        <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
    </div>

    <div class="ledger-card">
        <form method="GET" action="{{ route('admin.firm.ledger.details.report') }}" class="ledger-filter">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Select Firm</label>
                    <select name="firm_id" class="form-select searchable-select" data-placeholder="Search Firm Name">
                        <option value="">Select Firm</option>
                        @foreach($firms as $firm)
                            <option value="{{ $firm->id }}" {{ (string) $firmId === (string) $firm->id ? 'selected' : '' }}>
                                {{ $firm->firm_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary">Show Ledger</button>
                    <a href="{{ route('admin.firm.ledger.details.report') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>

        @if($selectedFirm)
            <div class="ledger-header">
                {{--  <img src="{{ asset('assets/web/img/logo.png') }}" alt="BigBite Agency" class="ledger-logo">  --}}
                <div class="ledger-firm">Firm :- {{ $selectedFirm->firm_name }}</div>
                <div class="ledger-phone">Mobile :- {{ $selectedFirm->phone ?: '-' }}</div>
            </div>

            <div class="ledger-summary">
                <div class="ledger-summary-card">
                    <div class="ledger-summary-label">Total Bill Amount</div>
                    <div class="ledger-summary-value">{{ number_format($totalBillAmount, 2) }}</div>
                </div>
                <div class="ledger-summary-card">
                    <div class="ledger-summary-label">Total Receipt Amount</div>
                    <div class="ledger-summary-value">{{ number_format($totalReceiptAmount, 2) }}</div>
                </div>
                <div class="ledger-summary-card">
                    <div class="ledger-summary-label">Total Discount</div>
                    <div class="ledger-summary-value">{{ number_format($totalDiscountAmount, 2) }}</div>
                </div>
                <div class="ledger-summary-card">
                    <div class="ledger-summary-label">Total Pending Amount</div>
                    <div class="ledger-summary-value">{{ number_format($totalPendingAmount, 2) }}</div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="ledger-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th class="text-end">Bill</th>
                            <th class="text-end">Receipt</th>
                            <th class="text-end">Discount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($ledgerEntries as $entry)
                            <tr>
                                <td>{{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}</td>
                                <td>
                                    {{ $entry->entry_type === 'invoice' ? 'Sales Invoice ' : 'Receipt Voucher ' }}{{ $entry->reference_no }}
                                </td>
                                <td class="text-end">{{ number_format($entry->debit, 2) }}</td>
                                <td class="text-end">{{ number_format($entry->credit, 2) }}</td>
                                <td class="text-end">{{ number_format($entry->discount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="ledger-empty">No Ledger Entries Found</td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="2" class="text-end">Total</th>
                            <th class="text-end">{{ number_format($totalBillAmount, 2) }}</th>
                            <th class="text-end">{{ number_format($totalReceiptAmount, 2) }}</th>
                            <th class="text-end">{{ number_format($totalDiscountAmount, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="alert alert-info mb-0">
                Select a firm to view ledger details.
            </div>
        @endif
    </div>
</div>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $('.searchable-select').each(function () {
        const $select = $(this);
        const placeholder = $select.data('placeholder') || 'Search';

        $select.select2({
            width: '100%',
            placeholder: placeholder,
            allowClear: true
        }).on('select2:open', function () {
            $('.select2-search__field').attr('placeholder', placeholder);
        });
    });
</script>
@endsection
