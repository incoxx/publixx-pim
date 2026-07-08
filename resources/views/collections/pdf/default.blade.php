<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-Regular.ttf') }}') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-Bold.ttf') }}') format('truetype'); font-weight: bold; font-style: normal; }
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-Italic.ttf') }}') format('truetype'); font-weight: normal; font-style: italic; }
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-BoldItalic.ttf') }}') format('truetype'); font-weight: bold; font-style: italic; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Noto Sans', DejaVu Sans, sans-serif; font-size: 10pt; color: #1f2937; }
        .page { position: relative; width: 210mm; }

        .header-canvas { position: relative; }
        .element { position: absolute; overflow: hidden; word-wrap: break-word; }

        .doc-meta { margin: 4mm 0 6mm 0; }
        .doc-meta .title { font-size: 16pt; font-weight: bold; }
        .doc-meta .ref { color: #4b5563; }

        .address-block { white-space: pre-line; margin-bottom: 4mm; }
        .cover-text { margin-bottom: 4mm; }
        .payment-terms { margin-bottom: 6mm; }
        .payment-terms .label { font-weight: bold; }

        table.items { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table.items th {
            background: #f3f4f6; color: #374151; text-align: left;
            border: 1px solid #e5e7eb; padding: 2mm; font-size: 8.5pt;
        }
        table.items td { border: 1px solid #e5e7eb; padding: 2mm; vertical-align: top; }
        table.items td.num { text-align: right; }
        table.items tr.line-text td { border-top: none; color: #4b5563; font-size: 8.5pt; padding-top: 0; }

        .totals { width: 100%; margin-top: 4mm; }
        .totals td { padding: 1mm 2mm; }
        .totals .grand-total { font-weight: bold; font-size: 11pt; border-top: 1px solid #1f2937; }

        .footer { position: fixed; bottom: -15mm; left: 0; right: 0; font-size: 8pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="page">
        {{-- Kopf-/Branding-Bereich: text/image-Elemente aus einer referenzierten PdfTemplate
             (CollectionPdfHeaderResolver) -- absolute-position Canvas-Konvention identisch zu
             resources/views/pdf-templates/template.blade.php:41-54,84. Leer, wenn kein
             default_render_template_id konfiguriert ist -- kein sichtbarer Effekt dann. --}}
        @if (!empty($header['template_elements']))
            <div class="header-canvas" style="height: 40mm;">
                @foreach ($header['template_elements'] as $el)
                    @php
                        $style = $el['style'] ?? [];
                        $css = sprintf(
                            'left: %smm; top: %smm; width: %smm; height: %smm;',
                            $el['x'] ?? 0,
                            $el['y'] ?? 0,
                            $el['width'] ?? 50,
                            $el['height'] ?? 10
                        );
                        if (!empty($style['fontFamily'])) $css .= ' font-family: ' . e($style['fontFamily']) . ', sans-serif;';
                        if (!empty($style['fontSize'])) $css .= ' font-size: ' . (int)$style['fontSize'] . 'pt;';
                        if (!empty($style['color'])) $css .= ' color: ' . e($style['color']) . ';';
                        if (!empty($style['fontWeight']) && $style['fontWeight'] !== 'normal') $css .= ' font-weight: ' . e($style['fontWeight']) . ';';
                        if (!empty($style['textAlign'])) $css .= ' text-align: ' . e($style['textAlign']) . ';';
                    @endphp
                    <div class="element" style="{{ $css }}">
                        @if (($el['type'] ?? '') === 'image' && !empty($el['resolvedImages']))
                            @foreach ($el['resolvedImages'] as $imgPath)
                                @if (file_exists($imgPath))
                                    <img src="{{ $imgPath }}" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
                                @endif
                            @endforeach
                        @else
                            {!! nl2br(e($el['displayValue'] ?? '')) !!}
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <div class="doc-meta">
            <div class="title">{{ $header['name'] ?? '' }}</div>
            @if (!empty($header['reference']))
                <div class="ref">Referenz: {{ $header['reference'] }}</div>
            @endif
            @if (!empty($header['valid_from']) || !empty($header['valid_until']))
                <div class="ref">
                    Gültig
                    @if (!empty($header['valid_from'])) vom {{ $header['valid_from'] }} @endif
                    @if (!empty($header['valid_until'])) bis {{ $header['valid_until'] }} @endif
                </div>
            @endif
        </div>

        @if (!empty($header['organization_name']) || !empty($header['address_block']))
            <div class="address-block">{{ $header['organization_name'] }}
@if (!empty($header['address_block']))
{{ $header['address_block'] }}
@endif</div>
        @endif

        @if (!empty($header['cover_text']))
            <div class="cover-text">{!! nl2br(e($header['cover_text'])) !!}</div>
        @endif

        @if (!empty($header['payment_terms']))
            <div class="payment-terms"><span class="label">Zahlungsbedingungen:</span> {{ $header['payment_terms'] }}</div>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th>Pos.</th>
                    <th>Bezeichnung</th>
                    <th>Menge</th>
                    <th>Einheit</th>
                    <th class="num">Einzelpreis</th>
                    <th class="num">Rabatt %</th>
                    <th class="num">Summe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item['position'] }}</td>
                        <td>
                            {{ $item['name'] }}
                            @if (!empty($item['sku']))
                                <br><span style="color:#6b7280;font-size:8pt;">{{ $item['sku'] }}</span>
                            @endif
                        </td>
                        <td class="num">{{ number_format($item['quantity'], 2, ',', '.') }}</td>
                        <td>{{ $item['unit_label'] }}</td>
                        @if ($item['price_warning'])
                            <td class="num">Preis auf Anfrage</td>
                            <td class="num">{{ number_format($item['discount_percent'], 2, ',', '.') }}</td>
                            <td class="num">Preis auf Anfrage</td>
                        @else
                            <td class="num">{{ $item['unit_price'] !== null ? number_format($item['unit_price'], 2, ',', '.') . ' ' . $item['currency'] : '' }}</td>
                            <td class="num">{{ number_format($item['discount_percent'], 2, ',', '.') }}</td>
                            <td class="num">{{ $item['line_total'] !== null ? number_format($item['line_total'], 2, ',', '.') . ' ' . $item['currency'] : '' }}</td>
                        @endif
                    </tr>
                    @if (!empty($item['line_text']))
                        <tr class="line-text">
                            <td></td>
                            <td colspan="6">{{ $item['line_text'] }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td style="width: 80%;"></td>
                <td class="grand-total">Gesamt: {{ number_format($grandTotal, 2, ',', '.') }} {{ $header['currency'] ?? 'EUR' }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">Seite {PAGE_NUM} von {PAGE_COUNT}</div>
</body>
</html>
