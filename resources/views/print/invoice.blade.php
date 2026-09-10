<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>{{ $kind === 'receipt' ? 'Receipt' : 'Invoice' }} {{ $invoice['invoice_number'] }}</title>
  <style>
    body { font-family: Arial, sans-serif; color: #1e293b; margin: 32px; }
    h1 { color: #1a56db; letter-spacing: .18em; font-size: 22px; margin: 0; }
    .sub { color: #64748b; letter-spacing: .28em; font-size: 10px; font-weight: 700; margin-bottom: 24px; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
    th { color: #94a3b8; font-weight: 500; }
    .meta { display: flex; justify-content: space-between; gap: 24px; }
    .totals { margin-top: 16px; width: 280px; margin-left: auto; }
    .totals td { border: 0; }
    .totals td:last-child { text-align: right; font-weight: 600; }
    @media print { button { display: none; } }
  </style>
</head>
<body>
  <button onclick="window.print()">Print</button>
  <div class="sub">HOSPITAL ERP</div>
  <h1>HCS</h1>
  <h2>{{ $kind === 'receipt' ? 'Payment Receipt' : 'Invoice' }}</h2>
  <div class="meta">
    <div>
      <div><strong>Patient:</strong> {{ $invoice['patient']['name'] }}</div>
      <div><strong>MRN:</strong> {{ $invoice['patient']['mrn'] }}</div>
    </div>
    <div>
      <div><strong>Number:</strong> {{ $invoice['invoice_number'] }}</div>
      <div><strong>Date:</strong> {{ $invoice['date'] }}</div>
      <div><strong>Status:</strong> {{ $invoice['status'] }}</div>
    </div>
  </div>

  @if($kind !== 'receipt')
  <table>
    <thead>
      <tr><th>Service</th><th>Qty</th><th>Amount</th><th>Discount</th><th>Tax</th><th>Total</th></tr>
    </thead>
    <tbody>
      @forelse($invoice['items'] as $item)
        <tr>
          <td>{{ $item['description'] }}</td>
          <td>{{ $item['quantity'] }}</td>
          <td>₹{{ number_format($item['unit_price'], 2) }}</td>
          <td>₹{{ number_format($item['discount'], 2) }}</td>
          <td>₹{{ number_format($item['tax'], 2) }}</td>
          <td>₹{{ number_format($item['total'], 2) }}</td>
        </tr>
      @empty
        <tr><td colspan="6">No line items.</td></tr>
      @endforelse
    </tbody>
  </table>
  <table class="totals">
    <tr><td>Subtotal</td><td>₹{{ number_format($invoice['subtotal'], 2) }}</td></tr>
    <tr><td>Discount</td><td>₹{{ number_format($invoice['discount'], 2) }}</td></tr>
    <tr><td>Tax</td><td>₹{{ number_format($invoice['tax'], 2) }}</td></tr>
    <tr><td>Total</td><td>₹{{ number_format($invoice['total'], 2) }}</td></tr>
    <tr><td>Paid</td><td>₹{{ number_format($invoice['amount_paid'], 2) }}</td></tr>
    <tr><td>Balance</td><td>₹{{ number_format($invoice['balance'], 2) }}</td></tr>
  </table>
  @endif

  <h3>Payments</h3>
  <table>
    <thead>
      <tr><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th><th>Status</th></tr>
    </thead>
    <tbody>
      @forelse($invoice['payments'] as $pay)
        <tr>
          <td>{{ $pay['paid_at'] }}</td>
          <td>{{ $pay['payment_method'] }}</td>
          <td>{{ $pay['transaction_reference'] }}</td>
          <td>₹{{ number_format($pay['amount'], 2) }}</td>
          <td>{{ $pay['status'] }}</td>
        </tr>
      @empty
        <tr><td colspan="5">No payments recorded.</td></tr>
      @endforelse
    </tbody>
  </table>
</body>
</html>
