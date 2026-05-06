<table>
    <thead>
    <tr>
        <th>Description</th>
        <th>Quantity</th>
        <th>Unit Price</th>
        <th>Total</th>
    </tr>
    </thead>
    <tbody>
    @forelse(data_get($snapshot, 'lines', []) as $line)
        <tr>
            <td>{{ $line['description'] ?? $line['name'] ?? '' }}</td>
            <td>{{ $line['quantity'] ?? 1 }}</td>
            <td>{{ number_format((float) ($line['unit_price'] ?? 0), 2) }}</td>
            <td>{{ number_format((float) ($line['total'] ?? $line['total_price'] ?? 0), 2) }}</td>
        </tr>
    @empty
        <tr><td colspan="4">No lines</td></tr>
    @endforelse
    </tbody>
</table>
