<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 13px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: auto;
            padding: 10px;
        }
        .header {
            border-bottom: 2px solid #efefef;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header table {
            width: 100%;
        }
        .header .logo-text {
            font-size: 24px;
            font-weight: bold;
            color: #4f46e5;
            margin: 0;
        }
        .header .company-details {
            text-align: right;
            font-size: 12px;
            color: #666;
        }
        .company-details h2 {
            margin: 0 0 5px 0;
            color: #111;
            font-size: 16px;
        }
        .company-details p {
            margin: 2px 0;
        }
        .meta-section {
            width: 100%;
            margin-bottom: 30px;
        }
        .meta-section td {
            vertical-align: top;
            width: 50%;
        }
        .billing-details h3 {
            margin: 0 0 8px 0;
            color: #4f46e5;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .billing-details p {
            margin: 3px 0;
        }
        .invoice-details {
            text-align: right;
        }
        .invoice-details h3 {
            margin: 0 0 8px 0;
            color: #111;
            font-size: 18px;
        }
        .invoice-details p {
            margin: 3px 0;
            color: #555;
        }
        .invoice-details span {
            font-weight: bold;
            color: #111;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f9fafb;
            border-bottom: 2px solid #eaebed;
            color: #4a5568;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 10px;
            text-align: left;
            letter-spacing: 0.5px;
        }
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #edf2f7;
            font-size: 12px;
        }
        .items-table .text-right {
            text-align: right;
        }
        .items-table .text-center {
            text-align: center;
        }
        .totals-section {
            width: 100%;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .totals-section td {
            vertical-align: top;
        }
        .totals-section .notes-column {
            width: 55%;
            font-size: 11px;
            color: #718096;
            padding-right: 40px;
        }
        .totals-section .notes-column h4 {
            margin: 0 0 5px 0;
            color: #4a5568;
            text-transform: uppercase;
            font-size: 11px;
        }
        .totals-section .totals-column {
            width: 45%;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 0;
            font-size: 12px;
            color: #4a5568;
        }
        .totals-table .amount {
            text-align: right;
            font-weight: 500;
            color: #1a202c;
        }
        .totals-table .grand-total {
            border-top: 2px solid #edf2f7;
            border-bottom: 2px solid #edf2f7;
            padding: 10px 0;
            font-size: 14px;
            font-weight: bold;
            color: #4f46e5;
        }
        .totals-table .grand-total .amount {
            font-size: 16px;
            font-weight: bold;
            color: #4f46e5;
        }
        .footer {
            margin-top: 50px;
            border-top: 1px solid #edf2f7;
            padding-top: 20px;
            text-align: center;
            color: #a0aec0;
            font-size: 11px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-draft { background-color: #edf2f7; color: #4a5568; }
        .badge-issued { background-color: #e0e7ff; color: #4f46e5; }
        .badge-paid { background-color: #d1fae5; color: #065f46; }
        .badge-cancelled { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <table>
                <tr>
                    <td>
                        <div class="logo-text">Growstro</div>
                        <div style="font-size: 11px; color: #718096; margin-top: 2px;">Smart Restaurant OS</div>
                    </td>
                    <td class="company-details">
                        <h2>{{ $businessName }}</h2>
                        <p>{{ $businessAddress }}</p>
                        <p>PAN/VAT Number: <strong>{{ $panNumber }}</strong></p>
                        <p>VAT Registered: <strong>{{ $vatRegistered }}</strong></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Meta Section -->
        <table class="meta-section">
            <tr>
                <!-- Billing To -->
                <td class="billing-details">
                    <h3>Bill To</h3>
                    @if($invoice->customer)
                        <p><strong>{{ $invoice->customer->name }}</strong></p>
                        @if($invoice->customer->phone)
                            <p>Phone: {{ $invoice->customer->phone }}</p>
                        @endif
                        @if($invoice->customer->address)
                            <p>{{ $invoice->customer->address }}</p>
                        @endif
                    @else
                        <p>Walk-in Customer</p>
                    @endif
                </td>
                <!-- Invoice Details -->
                <td class="invoice-details">
                    <h3>INVOICE</h3>
                    <p>Invoice No: <span>{{ $invoice->invoice_number }}</span></p>
                    <p>Status: <span class="badge badge-{{ $invoice->status }}">{{ $invoice->status }}</span></p>
                    <p>Date: <span>{{ $invoice->invoice_date->format('Y-m-d') }}</span></p>
                    @if($invoice->due_date)
                        <p>Due Date: <span>{{ $invoice->due_date->format('Y-m-d') }}</span></p>
                    @endif
                    @if($invoice->order)
                        <p>Order Ref: <span>{{ $invoice->order->order_number }}</span></p>
                    @endif
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th class="text-center" style="width: 10%;">Qty</th>
                    <th class="text-right" style="width: 15%;">Unit Price</th>
                    <th class="text-right" style="width: 15%;">Discount</th>
                    <th class="text-right" style="width: 20%;">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $item)
                    <tr>
                        <td>
                            <strong>{{ $item->name }}</strong>
                        </td>
                        <td class="text-center">{{ number_format($item->quantity, 0) }}</td>
                        <td class="text-right">Rs. {{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($item->discount_amount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals & Notes -->
        <table class="totals-section">
            <tr>
                <td class="notes-column">
                    @if($invoice->notes)
                        <h4>Notes & Special Instructions</h4>
                        <p style="white-space: pre-line;">{{ $invoice->notes }}</p>
                    @endif
                </td>
                <td class="totals-column">
                    <table class="totals-table">
                        <tr>
                            <td>Subtotal</td>
                            <td class="amount">Rs. {{ number_format($invoice->subtotal, 2) }}</td>
                        </tr>
                        @if($invoice->discount > 0)
                            <tr>
                                <td>Discount</td>
                                <td class="amount">-Rs. {{ number_format($invoice->discount, 2) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td>Taxable Amount</td>
                            <td class="amount">Rs. {{ number_format($invoice->taxable_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td>VAT (13%)</td>
                            <td class="amount">Rs. {{ number_format($invoice->vat_amount, 2) }}</td>
                        </tr>
                        @if($invoice->service_charge > 0)
                            <tr>
                                <td>Service Charge</td>
                                <td class="amount">Rs. {{ number_format($invoice->service_charge, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="grand-total">
                            <td>Grand Total</td>
                            <td class="amount">Rs. {{ number_format($invoice->total, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Paid Amount</td>
                            <td class="amount" style="color: #065f46;">Rs. {{ number_format($invoice->paid_amount, 2) }}</td>
                        </tr>
                        <tr style="font-weight: bold;">
                            <td>Due Amount</td>
                            <td class="amount" style="color: #991b1b;">Rs. {{ number_format($invoice->due_amount, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <!-- Footer -->
        <div class="footer">
            <p>Thank you for your business!</p>
            <p style="font-weight: bold; margin-top: 5px; color: #718096;">Powered by Growstro</p>
        </div>
    </div>
</body>
</html>
