<!DOCTYPE html>
<html>
<head>
    <title>Fatura {{ $order->order_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; }
        .header { text-align: center; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>FATURA</h1>
        <p>Sipariş No: {{ $order->order_number }}</p>
        <p>Tarih: {{ $order->created_at->format('d.m.Y') }}</p>
    </div>

    <p><strong>Müşteri:</strong> {{ $order->customer_name }}</p>
    <p><strong>Adres:</strong> {{ $order->shipping_address['address1'] ?? '-' }}</p>

    <table>
        <thead>
            <tr>
                <th>Ürün</th>
                <th>Adet</th>
                <th>Fiyat</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->line_items as $item)
            <tr>
                <td>{{ $item['title'] }}</td>
                <td>{{ $item['quantity'] }}</td>
                <td>{{ $item['price'] }} {{ $order->currency }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>Toplam Tutar: {{ $order->total_price }} {{ $order->currency }}</h3>
</body>
</html>
