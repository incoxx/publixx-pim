@php
    $type = $element['type'] ?? 'text';
    $style = $element['style'] ?? [];
    $inlineStyle = '';
    if (!empty($style['fontSize'])) $inlineStyle .= "font-size: {$style['fontSize']}px; ";
    if (!empty($style['bold'])) $inlineStyle .= 'font-weight: bold; ';
    if (!empty($style['italic'])) $inlineStyle .= 'font-style: italic; ';
    if (!empty($style['color'])) $inlineStyle .= "color: {$style['color']}; ";
    if (!empty($style['align'])) $inlineStyle .= "text-align: {$style['align']}; ";
    if (!empty($style['bgColor'])) $inlineStyle .= "background-color: {$style['bgColor']}; ";
    if (!empty($style['marginTop'])) $inlineStyle .= "margin-top: {$style['marginTop']}px; ";
    if (!empty($style['marginBottom'])) $inlineStyle .= "margin-bottom: {$style['marginBottom']}px; ";
@endphp

@if ($type === 'text')
    @php
        $content = $element['content'] ?? '';
        $ctx = $context ?? [];
        foreach ($ctx as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $content = str_replace('{' . $key . '}', (string) $val, $content);
            }
        }
        $content = str_replace('{date}', now()->format('d.m.Y'), $content);
        $content = str_replace('{datetime}', now()->format('d.m.Y H:i'), $content);
    @endphp
    <div class="element-text" style="{{ $inlineStyle }}">{{ $content }}</div>
@elseif ($type === 'separator')
    <div class="separator"></div>
@elseif ($type === 'pageBreak')
    <div class="page-break"></div>
@endif
