<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $lang === 'en' ? 'Product Preview' : 'Produkt-Vorschau' }} — {{ $data['stammdaten']['sku'] ?? '' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; line-height: 1.4; padding: 20px; }
        h1 { font-size: 18px; margin-bottom: 4px; color: #111827; }
        .subtitle { font-size: 11px; color: #6b7280; margin-bottom: 16px; }
        .section { margin-bottom: 16px; page-break-inside: avoid; }
        .section-header {
            background: #2563eb; color: #fff; padding: 6px 10px;
            font-size: 12px; font-weight: bold; margin-bottom: 0;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        th { background: #f3f4f6; text-align: left; padding: 4px 8px; font-size: 9px; border-bottom: 1px solid #d1d5db; }
        td { padding: 4px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) td { background: #f9fafb; }
        .stamm-label { font-weight: bold; width: 35%; background: #f3f4f6; }
        .empty { color: #9ca3af; font-style: italic; }
        .mandatory { color: #ef4444; font-size: 8px; vertical-align: super; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
    </style>
</head>
<body>

<h1>{{ $data['stammdaten']['name'] ?? ($lang === 'en' ? 'Product Preview' : 'Produkt-Vorschau') }}</h1>
<div class="subtitle">SKU: {{ $data['stammdaten']['sku'] ?? '-' }} | EAN: {{ $data['stammdaten']['ean'] ?? '-' }}</div>

{{-- Stammdaten --}}
<div class="section">
    <div class="section-header">{{ $lang === 'en' ? 'Master Data' : 'Stammdaten' }}</div>
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
            <td class="stamm-label">Name</td>
            <td>{{ $data['stammdaten']['name'] ?? '-' }}</td>
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
            <td>{{ implode(' > ', array_column($data['stammdaten']['category_breadcrumb'], 'name')) ?: '-' }}</td>
        </tr>
        <tr>
            <td class="stamm-label">{{ $lang === 'en' ? 'Created' : 'Erstellt' }}</td>
            <td>{{ $data['stammdaten']['created_at'] ?? '-' }}</td>
        </tr>
        <tr>
            <td class="stamm-label">{{ $lang === 'en' ? 'Updated' : 'Aktualisiert' }}</td>
            <td>{{ $data['stammdaten']['updated_at'] ?? '-' }}</td>
        </tr>
    </table>
</div>

{{-- Attribute Sections --}}
@foreach ($data['attribute_sections'] as $section)
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
                <td>
                    {{ $attr['label'] }}
                    @if ($attr['is_mandatory'])<span class="mandatory">*</span>@endif
                </td>
                <td>
                    @if ($attr['display_value'] !== null)
                        {{ $attr['display_value'] }}
                    @else
                        <span class="empty">-</span>
                    @endif
                </td>
                <td>{{ $attr['unit'] ?? '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endforeach

{{-- Relations --}}
@if (!empty($data['relations']))
<div class="section">
    <div class="section-header">{{ $lang === 'en' ? 'Relations' : 'Beziehungen' }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ $lang === 'en' ? 'Type' : 'Typ' }}</th>
                <th>{{ $lang === 'en' ? 'Target Product' : 'Zielprodukt' }}</th>
                <th>SKU</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['relations'] as $rel)
            <tr>
                <td>{{ $rel['relation_type'] ?? '-' }}</td>
                <td>{{ $rel['target_product']['name'] ?? '-' }}</td>
                <td>{{ $rel['target_product']['sku'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Prices --}}
@if (!empty($data['prices']))
<div class="section">
    <div class="section-header">{{ $lang === 'en' ? 'Prices' : 'Preise' }}</div>
    <table>
        <thead>
            <tr>
                <th>{{ $lang === 'en' ? 'Price Type' : 'Preistyp' }}</th>
                <th>{{ $lang === 'en' ? 'Amount' : 'Betrag' }}</th>
                <th>{{ $lang === 'en' ? 'Currency' : 'Wahrung' }}</th>
                <th>{{ $lang === 'en' ? 'Valid From' : 'Gultig ab' }}</th>
                <th>{{ $lang === 'en' ? 'Valid To' : 'Gultig bis' }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['prices'] as $price)
            <tr>
                <td>{{ $price['price_type'] ?? '-' }}</td>
                <td>{{ number_format((float) $price['amount'], 2, ',', '.') }}</td>
                <td>{{ $price['currency'] }}</td>
                <td>{{ $price['valid_from'] ?? '-' }}</td>
                <td>{{ $price['valid_to'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Media --}}
@if (!empty($data['media']))
<div class="section">
    <div class="section-header">Media</div>
    <table>
        <thead>
            <tr>
                <th>{{ $lang === 'en' ? 'File' : 'Datei' }}</th>
                <th>{{ $lang === 'en' ? 'Type' : 'Typ' }}</th>
                <th>{{ $lang === 'en' ? 'Primary' : 'Primaer' }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['media'] as $media)
            <tr>
                <td>{{ $media['file_name'] }}</td>
                <td>{{ $media['media_type'] ?? '-' }}</td>
                <td>{{ $media['is_primary'] ? ($lang === 'en' ? 'Yes' : 'Ja') : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Variants --}}
@if (!empty($data['variants']))
<div class="section">
    <div class="section-header">{{ $lang === 'en' ? 'Variants' : 'Varianten' }}</div>
    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Name</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data['variants'] as $variant)
            <tr>
                <td>{{ $variant['sku'] ?? '-' }}</td>
                <td>{{ $variant['name'] ?? '-' }}</td>
                <td>{{ $variant['status'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<div class="footer">
    Publixx PIM &mdash; {{ $lang === 'en' ? 'Product Preview' : 'Produkt-Vorschau' }} &mdash; {{ now()->format('d.m.Y H:i') }}
</div>

</body>
</html>
