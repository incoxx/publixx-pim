<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $lang === 'en' ? 'Watchlist' : 'Merkliste' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; line-height: 1.4; padding: 20px; }
        h1 { font-size: 18px; margin-bottom: 4px; color: #111827; }
        .subtitle { font-size: 11px; color: #6b7280; margin-bottom: 16px; }
        .product-block { margin-bottom: 24px; page-break-inside: avoid; }
        .product-header {
            background: #2563eb; color: #fff; padding: 8px 10px;
            font-size: 13px; font-weight: bold; margin-bottom: 0;
        }
        .product-header small { font-weight: normal; font-size: 10px; opacity: .8; }
        .section { margin-bottom: 8px; }
        .section-header {
            background: #dbeafe; color: #1e40af; padding: 4px 10px;
            font-size: 10px; font-weight: bold;
        }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f3f4f6; text-align: left; padding: 3px 8px; font-size: 9px; border-bottom: 1px solid #d1d5db; }
        td { padding: 3px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) td { background: #f9fafb; }
        .stamm-label { font-weight: bold; width: 35%; background: #f3f4f6; }
        .empty { color: #9ca3af; font-style: italic; }
        .mandatory { color: #ef4444; font-size: 8px; vertical-align: super; }
        .page-break { page-break-after: always; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>

<h1>{{ $lang === 'en' ? 'Watchlist' : 'Merkliste' }}</h1>
<div class="subtitle">{{ count($products) }} {{ $lang === 'en' ? 'Products' : 'Produkte' }} &mdash; {{ now()->format('d.m.Y H:i') }}</div>

@foreach ($products as $idx => $data)
<div class="product-block">
    <div class="product-header">
        {{ $data['stammdaten']['name'] ?? '-' }}
        <small>SKU: {{ $data['stammdaten']['sku'] ?? '-' }}</small>
    </div>

    {{-- Stammdaten --}}
    <div class="section">
        <table>
            <tr>
                <td class="stamm-label">SKU</td>
                <td>{{ $data['stammdaten']['sku'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="stamm-label">EAN</td>
                <td>{{ $data['stammdaten']['ean'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="stamm-label">Status</td>
                <td>{{ $data['stammdaten']['status'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="stamm-label">{{ $lang === 'en' ? 'Product Type' : 'Produkttyp' }}</td>
                <td>{{ $data['stammdaten']['product_type']['name'] ?? '-' }}</td>
            </tr>
            <tr>
                <td class="stamm-label">{{ $lang === 'en' ? 'Category' : 'Kategorie' }}</td>
                <td>{{ implode(' > ', array_column($data['stammdaten']['category_breadcrumb'] ?? [], 'name')) ?: '-' }}</td>
            </tr>
        </table>
    </div>

    {{-- Attribute Sections --}}
    @foreach ($data['attribute_sections'] ?? [] as $section)
    <div class="section">
        <div class="section-header">{{ $section['section_name'] }}</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 35%;">{{ $lang === 'en' ? 'Attribute' : 'Attribut' }}</th>
                    <th style="width: 50%;">{{ $lang === 'en' ? 'Value' : 'Wert' }}</th>
                    <th style="width: 15%;">{{ $lang === 'en' ? 'Unit' : 'Einheit' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($section['attributes'] as $attr)
                <tr>
                    <td>{{ $attr['label'] }}@if ($attr['is_mandatory'])<span class="mandatory">*</span>@endif</td>
                    <td>{{ $attr['display_value'] ?? '' }}{!! !$attr['display_value'] ? '<span class="empty">-</span>' : '' !!}</td>
                    <td>{{ $attr['unit'] ?? '' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endforeach

    {{-- Prices --}}
    @if (!empty($data['prices']))
    <div class="section">
        <div class="section-header">{{ $lang === 'en' ? 'Prices' : 'Preise' }}</div>
        <table>
            <thead>
                <tr>
                    <th>{{ $lang === 'en' ? 'Price Type' : 'Preistyp' }}</th>
                    <th>{{ $lang === 'en' ? 'Amount' : 'Betrag' }}</th>
                    <th>{{ $lang === 'en' ? 'Currency' : 'Währung' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['prices'] as $price)
                <tr>
                    <td>{{ $price['price_type'] ?? '-' }}</td>
                    <td>{{ number_format((float) $price['amount'], 2, ',', '.') }}</td>
                    <td>{{ $price['currency'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

@if (!$loop->last)
<div class="page-break"></div>
@endif
@endforeach

<div class="footer">
    anyPIM &mdash; {{ $lang === 'en' ? 'Watchlist Export' : 'Merkliste Export' }} &mdash; {{ now()->format('d.m.Y H:i') }}
</div>

</body>
</html>
