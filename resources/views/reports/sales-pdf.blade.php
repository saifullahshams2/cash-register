<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $companyName }} - Sales Report</title>
    <style>
        @page {
            size: a4 portrait;
            margin: 15mm 15mm 20mm 15mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 12px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .company-name {
            font-size: 20px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }
        .report-title {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 2px;
        }
        .meta-box {
            text-align: right;
            font-size: 10px;
            color: #475569;
        }
        .meta-box strong {
            color: #0f172a;
        }
        .logo-img {
            max-height: 48px;
            max-width: 140px;
            object-fit: contain;
        }
        .logo-placeholder {
            width: 44px;
            height: 44px;
            background-color: #0f172a;
            color: #ffffff;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            line-height: 44px;
            border-radius: 8px;
            display: inline-block;
        }
        /* Summary KPI Cards */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .summary-card {
            width: 25%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background-color: #f8fafc;
            text-align: center;
        }
        .summary-card.dark {
            background-color: #0f172a;
            color: #ffffff;
            border-color: #0f172a;
        }
        .summary-card.emerald {
            background-color: #ecfdf5;
            border-color: #a7f3d0;
        }
        .summary-card.sky {
            background-color: #f0f9ff;
            border-color: #bae6fd;
        }
        .summary-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .summary-card.dark .summary-label {
            color: #cbd5e1;
        }
        .summary-card.emerald .summary-label {
            color: #047857;
        }
        .summary-card.sky .summary-label {
            color: #0369a1;
        }
        .summary-value {
            font-size: 16px;
            font-weight: bold;
            font-family: 'Courier New', Courier, monospace;
        }
        .summary-card.dark .summary-value {
            color: #ffffff;
        }
        .summary-card.emerald .summary-value {
            color: #065f46;
        }
        .summary-card.sky .summary-value {
            color: #075985;
        }
        /* Orders Table */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .orders-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 6px;
            text-align: left;
            border: 1px solid #1e293b;
        }
        .orders-table th.text-right {
            text-align: right;
        }
        .orders-table th.text-center {
            text-align: center;
        }
        .orders-table td {
            padding: 6px 6px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .orders-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .order-num {
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
            color: #0f172a;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-cash {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .badge-knet {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        .items-list {
            color: #475569;
            font-size: 9px;
            line-height: 1.3;
        }
        .amount {
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
            text-align: right;
            color: #0f172a;
        }
        .total-row td {
            border-top: 2px solid #0f172a;
            border-bottom: 2px solid #0f172a;
            font-weight: bold;
            background-color: #f1f5f9 !important;
            font-size: 11px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            height: 20px;
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>

    <!-- Header Block -->
    <table class="header-table">
        <tr>
            <td style="width: 60px;">
                @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" class="logo-img" alt="Logo">
                @else
                    <div class="logo-placeholder">KW</div>
                @endif
            </td>
            <td style="padding-left: 12px;">
                <div class="company-name">{{ $companyName }}</div>
                <div class="report-title">Sales Performance &amp; Order Receipts</div>
            </td>
            <td class="meta-box">
                <div><strong>Period:</strong> {{ $dateRangeText }}</div>
                <div><strong>Generated:</strong> {{ $generatedAt }}</div>
                <div><strong>Generated By:</strong> {{ $generatedBy }}</div>
            </td>
        </tr>
    </table>

    <!-- KPI Summary Row -->
    <table class="summary-table">
        <tr>
            <td class="summary-card dark">
                <div class="summary-label">Total Revenue</div>
                <div class="summary-value">{{ number_format($revenue, 3, '.', '') }} <span style="font-size: 10px;">KWD</span></div>
            </td>
            <td style="width: 10px;"></td>
            <td class="summary-card">
                <div class="summary-label">Total Orders</div>
                <div class="summary-value" style="color: #0f172a;">{{ $count }} <span style="font-size: 10px; font-weight: normal; color: #64748b;">Orders</span></div>
            </td>
            <td style="width: 10px;"></td>
            <td class="summary-card emerald">
                <div class="summary-label">CASH Revenue</div>
                <div class="summary-value">{{ number_format($cashRevenue, 3, '.', '') }} <span style="font-size: 10px;">KWD</span></div>
            </td>
            <td style="width: 10px;"></td>
            <td class="summary-card sky">
                <div class="summary-label">K-NET / Card Revenue</div>
                <div class="summary-value">{{ number_format($knetRevenue, 3, '.', '') }} <span style="font-size: 10px;">KWD</span></div>
            </td>
        </tr>
    </table>

    <!-- Orders Table -->
    <table class="orders-table">
        <thead>
            <tr>
                <th style="width: 22%;">Order #</th>
                <th style="width: 18%;">Date &amp; Time</th>
                <th style="width: 16%;">Cashier Name</th>
                <th style="width: 12%;" class="text-center">Payment</th>
                <th style="width: 20%;">Items Sold</th>
                <th style="width: 12%;" class="text-right">Total (KWD)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $order)
                <tr>
                    <td class="order-num">{{ $order->order_number }}</td>
                    <td>
                        <div>{{ $order->created_at->format('d M Y') }}</div>
                        <div style="font-size: 8px; color: #64748b;">{{ $order->created_at->format('h:i:s A') }}</div>
                    </td>
                    <td>
                        <strong>{{ $order->cashier_name ?: ($order->user?->name ?: 'Cashier') }}</strong>
                    </td>
                    <td class="text-center">
                        @if($order->payment_method === 'CASH')
                            <span class="badge badge-cash">CASH</span>
                        @else
                            <span class="badge badge-knet">K-NET</span>
                        @endif
                    </td>
                    <td class="items-list">
                        @if($order->items && count($order->items) > 0)
                            @foreach($order->items as $item)
                                <span>{{ $item->quantity }}x {{ $item->product_name }}@if(!$loop->last), @endif</span>
                            @endforeach
                        @else
                            <span style="font-style: italic; color: #94a3b8;">Direct checkout</span>
                        @endif
                    </td>
                    <td class="amount">{{ number_format($order->total, 3, '.', '') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; padding: 24px; color: #94a3b8;">
                        No orders recorded for the selected period.
                    </td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="4" style="text-align: left; padding: 8px 6px;">
                    TOTAL ({{ $count }} Orders)
                </td>
                <td style="text-align: right; font-size: 9px; color: #64748b;">
                    Period Total:
                </td>
                <td class="amount" style="font-size: 12px;">
                    {{ number_format($revenue, 3, '.', '') }}
                </td>
            </tr>
        </tfoot>
    </table>

    <div class="footer">
        {{ $companyName }} &bull; Kuwait Cash Register POS &bull; Confirmed &amp; Certified Sales Report
    </div>

</body>
</html>
