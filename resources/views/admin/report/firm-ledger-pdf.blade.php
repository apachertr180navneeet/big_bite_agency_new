<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Firm Ledger</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 85%;
            margin: 30px auto;
            text-align: center;
        }

        /* Company Header */
        .company-header {
            margin-bottom: 20px;
        }

        .company-name {
            font-size: 20px;
            font-weight: bold;
        }

        .company-info {
            font-size: 13px;
            margin-top: 5px;
            line-height: 1.5;
        }

        /* Title */
        .title {
            font-size: 18px;
            font-weight: bold;
            margin: 15px 0;
        }

        /* Firm Info */
        .firm-info {
            margin-bottom: 20px;
            font-size: 14px;
        }

        .firm-info p {
            margin: 4px 0;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #000;
            padding: 8px;
            font-size: 13px;
        }

        th {
            background: #f2f2f2;
        }

        .text-left {
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .total-row th {
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="container">

    {{-- COMPANY HEADER --}}
    <div class="company-header">
        <div class="company-name">Bigbite Agency</div>

        <div class="company-info">
            Number: +91 8107078020<br>
            Address: NEAR MAHABAL MALL, SHOP NO 1,<br>
            NARSINGH JI PAYAO, MATA KA THAN ROAD,<br>
            JODHPUR, Rajasthan - 342001
        </div>
    </div>

    {{-- TITLE --}}
    <div class="title">Firm Ledger</div>

    {{-- FIRM DETAILS --}}
    <div class="firm-info">
        <p><strong>Firm:</strong> {{ $selectedFirm->firm_name }}</p>
        <p><strong>Phone:</strong> {{ $selectedFirm->phone ?? '-' }}</p>
    </div>

    {{-- TABLE --}}
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Description</th>
                <th>Bill</th>
                <th>Receipt</th>
                <th>Discount</th>
            </tr>
        </thead>

        <tbody>
            @forelse($ledgerEntries as $entry)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($entry->date)->format('d/m/Y') }}</td>

                    <td class="text-left">
                        {{ $entry->entry_type == 'invoice' ? 'Sales Invoice ' : 'Receipt ' }}
                        {{ $entry->reference_no }}
                    </td>

                    <td class="text-right">{{ number_format($entry->debit, 2) }}</td>
                    <td class="text-right">{{ number_format($entry->credit, 2) }}</td>
                    <td class="text-right">{{ number_format($entry->discount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No Data Found</td>
                </tr>
            @endforelse
        </tbody>

        <tfoot>
            <tr class="total-row">
                <th colspan="2">Total</th>
                <th>{{ number_format($totalBillAmount, 2) }}</th>
                <th>{{ number_format($totalReceiptAmount, 2) }}</th>
                <th>{{ number_format($totalDiscountAmount, 2) }}</th>
            </tr>
        </tfoot>
    </table>

</div>

</body>
</html>