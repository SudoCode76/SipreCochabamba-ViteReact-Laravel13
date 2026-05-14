<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $document['header']['title'] }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #111827;
            margin: 18px 22px;
        }
        .header {
            text-align: center;
            line-height: 1.2;
            margin-bottom: 12px;
        }
        .header .title {
            font-size: 16px;
            font-weight: bold;
            margin-top: 8px;
            text-transform: uppercase;
        }
        .print-meta {
            text-align: right;
            font-size: 9px;
            margin-bottom: 10px;
        }
        .item-box {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .item-box td {
            border: 1px solid #111827;
            padding: 5px 6px;
        }
        .item-box .label {
            width: 70px;
            font-weight: bold;
            background: #f3f4f6;
        }
        .block-title {
            font-weight: bold;
            text-transform: uppercase;
            margin: 10px 0 4px;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        table.data th,
        table.data td {
            border: 1px solid #111827;
            padding: 4px 5px;
            vertical-align: top;
        }
        table.data th {
            background: #e5e7eb;
            font-weight: bold;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background: #f9fafb;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .summary-table td {
            border: 1px solid #111827;
            padding: 5px 6px;
        }
        .summary-table .code {
            width: 45px;
            text-align: center;
            font-weight: bold;
            background: #f3f4f6;
        }
        .summary-table .amount {
            width: 120px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="print-meta">Impresion: {{ $document['header']['printed_at'] }}</div>

    <div class="header">
        <div>{{ $document['header']['entity'] }}</div>
        <div>{{ $document['header']['department'] }}</div>
        <div>{{ $document['header']['division'] }}</div>
        <div>{{ $document['header']['country'] }}</div>
        <div class="title">{{ $document['header']['title'] }}</div>
    </div>

    <table class="item-box">
        <tr>
            <td class="label">ITEM</td>
            <td>{{ $document['item']['name'] }}</td>
            <td class="label">UNIDAD</td>
            <td>{{ trim(($document['item']['unit'] ?? '').' '.(($document['item']['unit_abbreviation'] ?? '') ? '('.$document['item']['unit_abbreviation'].')' : '')) }}</td>
        </tr>
    </table>

    @foreach ($document['blocks'] as $block)
        <div class="block-title">{{ $block['code'] }}. {{ $block['title'] }}</div>
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 30px;">N°</th>
                    <th>Insumo</th>
                    <th style="width: 70px;">Unidad</th>
                    <th style="width: 70px;">Cantidad</th>
                    <th style="width: 80px;">Unitario</th>
                    <th style="width: 80px;">Parcial</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($block['rows'] as $row)
                    <tr>
                        <td class="text-center">{{ $row['position'] }}</td>
                        <td>{{ $row['description'] }}</td>
                        <td class="text-center">{{ $row['unit'] }}</td>
                        <td class="text-right">{{ number_format((float) $row['quantity'], 4, '.', ',') }}</td>
                        <td class="text-right">{{ number_format((float) $row['unit_price'], 2, '.', ',') }}</td>
                        <td class="text-right">{{ number_format((float) $row['partial'], 2, '.', ',') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center">Sin registros</td>
                    </tr>
                @endforelse
                <tr class="total-row">
                    <td colspan="5" class="text-right">Total {{ strtolower($block['title']) }}</td>
                    <td class="text-right">{{ number_format((float) $block['total'], 2, '.', ',') }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <div class="block-title">Parametros / porcentajes</div>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 30px;">N°</th>
                <th>Parametro</th>
                <th style="width: 90px;">Base</th>
                <th style="width: 70px;">%</th>
                <th style="width: 90px;">Parcial</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($document['parameters'] as $row)
                <tr>
                    <td class="text-center">{{ $row['position'] }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="text-right">{{ number_format((float) $row['base_amount'], 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) $row['percentage'], 2, '.', ',') }}</td>
                    <td class="text-right">{{ number_format((float) $row['amount'], 2, '.', ',') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">Sin parametros activos</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="block-title">Totales finales</div>
    <table class="summary-table">
        @foreach ($document['summary'] as $row)
            <tr class="{{ $row['code'] === 'TOTAL' ? 'total-row' : '' }}">
                <td class="code">{{ $row['code'] }}</td>
                <td>{{ $row['label'] }}</td>
                <td class="amount">{{ number_format((float) $row['amount'], 2, '.', ',') }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
