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
                    @if (($el['type'] ?? '') === 'image' && !empty($el['resolvedImages']))
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
