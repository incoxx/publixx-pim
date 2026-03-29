<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-Regular.ttf') }}') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-Bold.ttf') }}') format('truetype'); font-weight: bold; font-style: normal; }
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-Italic.ttf') }}') format('truetype'); font-weight: normal; font-style: italic; }
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-BoldItalic.ttf') }}') format('truetype'); font-weight: bold; font-style: italic; }
        @font-face { font-family: 'Noto Serif'; src: url('{{ storage_path('fonts/NotoSerif-Regular.ttf') }}') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Noto Serif'; src: url('{{ storage_path('fonts/NotoSerif-Bold.ttf') }}') format('truetype'); font-weight: bold; font-style: normal; }
        @font-face { font-family: 'Noto Serif'; src: url('{{ storage_path('fonts/NotoSerif-Italic.ttf') }}') format('truetype'); font-weight: normal; font-style: italic; }
        @font-face { font-family: 'Noto Serif'; src: url('{{ storage_path('fonts/NotoSerif-BoldItalic.ttf') }}') format('truetype'); font-weight: bold; font-style: italic; }
        @font-face { font-family: 'Roboto'; src: url('{{ storage_path('fonts/Roboto-Regular.ttf') }}') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Open Sans'; src: url('{{ storage_path('fonts/OpenSans-Regular.ttf') }}') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Open Sans'; src: url('{{ storage_path('fonts/OpenSans-Italic.ttf') }}') format('truetype'); font-weight: normal; font-style: italic; }
        @font-face { font-family: 'Lato'; src: url('{{ storage_path('fonts/Lato-Regular.ttf') }}') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Lato'; src: url('{{ storage_path('fonts/Lato-Bold.ttf') }}') format('truetype'); font-weight: bold; font-style: normal; }
        @font-face { font-family: 'Lato'; src: url('{{ storage_path('fonts/Lato-Italic.ttf') }}') format('truetype'); font-weight: normal; font-style: italic; }
        @font-face { font-family: 'Lato'; src: url('{{ storage_path('fonts/Lato-BoldItalic.ttf') }}') format('truetype'); font-weight: bold; font-style: italic; }
        @font-face { font-family: 'Source Sans 3'; src: url('{{ storage_path('fonts/SourceSans3-Regular.ttf') }}') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Source Sans 3'; src: url('{{ storage_path('fonts/SourceSans3-Italic.ttf') }}') format('truetype'); font-weight: normal; font-style: italic; }

        @foreach ($customFonts ?? [] as $cfont)
            @if ($cfont->file_regular)
                @font-face { font-family: '{{ e($cfont->family_name) }}'; src: url('{{ $cfont->getAbsolutePath('regular') }}') format('truetype'); font-weight: normal; font-style: normal; }
            @endif
            @if ($cfont->file_bold)
                @font-face { font-family: '{{ e($cfont->family_name) }}'; src: url('{{ $cfont->getAbsolutePath('bold') }}') format('truetype'); font-weight: bold; font-style: normal; }
            @endif
            @if ($cfont->file_italic)
                @font-face { font-family: '{{ e($cfont->family_name) }}'; src: url('{{ $cfont->getAbsolutePath('italic') }}') format('truetype'); font-weight: normal; font-style: italic; }
            @endif
            @if ($cfont->file_bold_italic)
                @font-face { font-family: '{{ e($cfont->family_name) }}'; src: url('{{ $cfont->getAbsolutePath('bold_italic') }}') format('truetype'); font-weight: bold; font-style: italic; }
            @endif
        @endforeach

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; }
        .page {
            position: relative;
            width: {{ $pageOrientation === 'landscape' ? '297mm' : '210mm' }};
            height: {{ $pageOrientation === 'landscape' ? '210mm' : '297mm' }};
            overflow: hidden;
            @if (!empty($templateJson['style']['backgroundColor']))
                background-color: {{ $templateJson['style']['backgroundColor'] }};
            @endif
        }
        .page-break { page-break-after: always; }
        .element {
            position: absolute;
            overflow: hidden;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    @foreach ($pages as $pageIndex => $elements)
        <div class="page">
            @foreach ($elements as $el)
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
                    if (!empty($style['fontStyle']) && $style['fontStyle'] !== 'normal') $css .= ' font-style: ' . e($style['fontStyle']) . ';';
                    if (!empty($style['textAlign'])) $css .= ' text-align: ' . e($style['textAlign']) . ';';
                    if (!empty($style['backgroundColor'])) $css .= ' background-color: ' . e($style['backgroundColor']) . ';';
                    if (!empty($style['borderWidth']) && (int)$style['borderWidth'] > 0) {
                        $css .= ' border: ' . (int)$style['borderWidth'] . 'px solid ' . e($style['borderColor'] ?? '#000000') . ';';
                    }
                    if (isset($style['padding'])) $css .= ' padding: ' . (int)$style['padding'] . 'mm;';
                    if (!empty($style['lineHeight'])) $css .= ' line-height: ' . e($style['lineHeight']) . ';';
                @endphp

                <div class="element" style="{{ $css }}">
                    @if (($el['type'] ?? '') === 'smart_table' && !empty($el['smartTableData']))
                        @php
                            $ptl = $el['ptl'] ?? [];
                            $stData = $el['smartTableData'];
                            $hStyle = $ptl['headerStyle'] ?? [];
                            $rStyle = $ptl['rowStyle'] ?? [];
                            $bColor = $ptl['borderColor'] ?? '#e5e7eb';
                            $zebra = $ptl['zebraStripes'] ?? false;
                            $zebraColor = $ptl['zebraColor'] ?? '#f9fafb';
                            $flatCols = $stData['columns'] ?? [];
                        @endphp
                        <table style="width:100%; border-collapse:collapse; font-size:{{ $rStyle['fontSize'] ?? 8 }}pt; font-family: inherit;">
                            <thead>
                                @foreach ($stData['headerRows'] as $headerRow)
                                    <tr style="{{ !empty($hStyle['backgroundColor']) ? 'background:' . e($hStyle['backgroundColor']) . ';' : '' }} color:{{ e($hStyle['color'] ?? '#374151') }};">
                                        @foreach ($headerRow as $cell)
                                            <th
                                                @if (($cell['colspan'] ?? 1) > 1) colspan="{{ $cell['colspan'] }}" @endif
                                                @if (($cell['rowspan'] ?? 1) > 1) rowspan="{{ $cell['rowspan'] }}" @endif
                                                style="border:1px solid {{ e($bColor) }}; padding:1mm 2mm; text-align:{{ ($cell['colspan'] ?? 1) > 1 ? 'center' : 'left' }}; font-weight:{{ $hStyle['fontWeight'] ?? 700 }}; font-size:{{ $hStyle['fontSize'] ?? 9 }}pt;"
                                            >{{ e($cell['label'] ?? '') }}</th>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </thead>
                            <tbody>
                                @foreach ($stData['bodyRows'] as $ri => $row)
                                    <tr @if ($zebra && $ri % 2 === 1 && !empty($zebraColor)) style="background:{{ e($zebraColor) }};" @endif>
                                        @foreach ($row as $ci => $cell)
                                            <td style="border:1px solid {{ e($bColor) }}; padding:1mm 2mm; text-align:{{ $flatCols[$ci]['align'] ?? 'left' }};">{{ e($cell) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @elseif (in_array($el['type'] ?? '', ['variant_table', 'relation_table', 'attribute_table']) && !empty($el['variantTableData']))
                        @php $tStyle = $el['tableStyle'] ?? []; $tData = $el['variantTableData']; $colWidths = $el['columnWidths'] ?? []; @endphp
                        <table style="width:100%; border-collapse:collapse; font-size:{{ $tStyle['fontSize'] ?? 8 }}pt; font-family: inherit; table-layout: {{ !empty($colWidths) ? 'fixed' : 'auto' }};">
                            <thead>
                                <tr style="background:{{ e($tStyle['headerBg'] ?? '#f3f4f6') }}; color:{{ e($tStyle['headerColor'] ?? '#374151') }};">
                                    @foreach ($tData['headers'] as $hi => $h)
                                        <th style="border:1px solid {{ e($tStyle['borderColor'] ?? '#e5e7eb') }}; padding:1mm 2mm; text-align:left; font-size:{{ $tStyle['headerFontSize'] ?? 8 }}pt;{{ isset($colWidths[$hi]) ? ' width:' . (int)$colWidths[$hi] . '%;' : '' }}">{{ e($h) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tData['rows'] as $ri => $row)
                                    <tr @if ($ri % 2 === 1) style="background:{{ e($tStyle['alternateRowBg'] ?? '#f9fafb') }};" @endif>
                                        @foreach ($row as $cell)
                                            <td style="border:1px solid {{ e($tStyle['borderColor'] ?? '#e5e7eb') }}; padding:1mm 2mm;">{{ e($cell) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @elseif (($el['type'] ?? '') === 'image' && !empty($el['resolvedImages']))
                        @foreach ($el['resolvedImages'] as $imgPath)
                            @if (file_exists($imgPath))
                                <img src="{{ $imgPath }}" style="max-width: 100%; max-height: 100%; object-fit: contain;" />
                            @endif
                        @endforeach
                    @elseif (($el['type'] ?? '') === 'shape')
                        {{-- Shape --}}
                    @else
                        {!! nl2br(e($el['displayValue'] ?? '')) !!}
                    @endif
                </div>
            @endforeach
        </div>
        @if (!$loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
