{{--
    Reines Dokument-Markup (Kopfbereich + Positionstabelle + Summen) -- von zwei Kontexten
    eingebunden: default.blade.php (PDF via Dompdf) und shared/collection-document.blade.php
    (Browser-HTML fuer den passwortgeschuetzten Freigabe-Link). Style-Klassen (.page,
    .header-canvas, table.items, ...) werden vom jeweiligen Parent-Template definiert, damit
    beide Kontexte ihre eigene Praesentation (Dompdf-Schriftarten vs. Browser-Systemschriften,
    A4-Druckseite vs. zentrierte Karte) haben koennen, ohne dieses Markup zu duplizieren.
--}}
<div class="page">
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

    @if (!empty($header['display_attributes']))
        <div class="header-attributes">
            @foreach ($header['display_attributes'] as $attr)
                <div class="header-attribute"><span class="label">{{ $attr['label'] }}:</span> {!! nl2br(e($attr['value'])) !!}</div>
            @endforeach
        </div>
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
                @foreach ($item['display_attributes'] ?? [] as $attr)
                    <tr class="line-text">
                        <td></td>
                        <td colspan="6"><span class="label">{{ $attr['label'] }}:</span> {{ $attr['value'] }}</td>
                    </tr>
                @endforeach
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
