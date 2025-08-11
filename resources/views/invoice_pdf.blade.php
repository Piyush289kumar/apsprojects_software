<!DOCTYPE html>
<html>

<head>
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        th {
            background-color: #f2f2f2;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        h1,
        h2,
        h3 {
            margin: 0;
        }
    </style>
</head>

<body>
    <h1>Invoice: {{ $invoice->invoice_number }}</h1>
    <p><strong>Date:</strong> {{ $invoice->invoice_date->format('d-m-Y') }}</p>
    <p><strong>Due Date:</strong> {{ $invoice->due_date?->format('d-m-Y') ?? '-' }}</p>
    <p><strong>Billed To:</strong> {{ $invoice->billable->name }}</p>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="center">Qty</th>
                <th class="right">Unit Price</th>
                <th class="right">CGST Rate (%)</th>
                <th class="right">SGST Rate (%)</th>
                <th class="right">IGST Rate (%)</th>
                <th class="right">CGST Amount</th>
                <th class="right">SGST Amount</th>
                <th class="right">IGST Amount</th>
                <th class="right">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->product->name ?? 'N/A' }}</td>
                    <td class="center">{{ $item->quantity }}</td>
                    <td class="right">{{ number_format($item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format($item->cgst_rate, 2) }}</td>
                    <td class="right">{{ number_format($item->sgst_rate, 2) }}</td>
                    <td class="right">{{ number_format($item->igst_rate, 2) }}</td>
                    <td class="right">{{ number_format($item->cgst_amount, 2) }}</td>
                    <td class="right">{{ number_format($item->sgst_amount, 2) }}</td>
                    <td class="right">{{ number_format($item->igst_amount, 2) }}</td>
                    <td class="right">{{ number_format($item->total_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <tr>
            <th>Taxable Value</th>
            <td class="right">{{ number_format($invoice->taxable_value, 2) }}</td>
        </tr>
        <tr>
            <th>CGST Amount</th>
            <td class="right">{{ number_format($invoice->cgst_amount, 2) }}</td>
        </tr>
        <tr>
            <th>SGST Amount</th>
            <td class="right">{{ number_format($invoice->sgst_amount, 2) }}</td>
        </tr>
        <tr>
            <th>IGST Amount</th>
            <td class="right">{{ number_format($invoice->igst_amount, 2) }}</td>
        </tr>
        <tr>
            <th>Total Tax</th>
            <td class="right">{{ number_format($invoice->total_tax, 2) }}</td>
        </tr>
        <tr>
            <th>Discount</th>
            <td class="right">{{ number_format($invoice->discount, 2) }}</td>
        </tr>
        <tr>
            <th><strong>Total Amount</strong></th>
            <td class="right"><strong>{{ number_format($invoice->total_amount, 2) }}</strong></td>
        </tr>
    </table>

    @if ($invoice->notes)
        <h3>Notes</h3>
        <p>{{ $invoice->notes }}</p>
    @endif
</body>

</html>
