<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
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
        .element {
            position: absolute;
            overflow: hidden;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
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
                    {{-- Shape element: rendered via background-color and border --}}
                @else
                    {!! nl2br(e($el['displayValue'] ?? '')) !!}
                @endif
            </div>
        @endforeach
    </div>
</body>
</html>
