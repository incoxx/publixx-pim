@php
    $type = $element['type'] ?? '';
    $renderer = app(\App\Services\Report\ElementRenderer::class);
@endphp

@if ($type === 'field')
    @php
        $value = $renderer->resolveFieldValue($product, $element['field'] ?? '', $lang);
        $showLabel = $element['showLabel'] ?? true;
        $label = $element['label'] ?? ($element['field'] ?? '');
    @endphp
    <div class="element-text">
        @if ($showLabel)
            <strong>{{ $label }}:</strong>
        @endif
        {{ $value ?: '-' }}
    </div>

@elseif ($type === 'attribute')
    @php
        $resolved = $renderer->resolveAttributeValue($product, $element['attributeId'] ?? '', $lang);
        $showLabel = $element['showLabel'] ?? true;
        $showValue = $element['showValue'] ?? true;
        $showUnit = $element['showUnit'] ?? true;
    @endphp
    @if ($resolved['value'] || $resolved['label'])
        <div class="element-text">
            @if ($showLabel && $resolved['label'])
                <strong>{{ $resolved['label'] }}:</strong>
            @endif
            @if ($showValue)
                {{ $resolved['value'] }}
                @if ($showUnit && $resolved['unit'])
                    {{ $resolved['unit'] }}
                @endif
            @endif
        </div>
    @endif

@elseif ($type === 'separator')
    <div class="separator"></div>

@elseif ($type === 'pageBreak')
    <div class="page-break"></div>

@elseif ($type === 'text')
    @include('reports.partials.element', ['element' => $element, 'context' => []])

@elseif ($type === 'image')
    @php
        $media = $product->mediaAssignments?->first()?->media;
    @endphp
    @if ($media && $media->file_path)
        @php
            $imgPath = storage_path('app/public/media/' . $media->file_name);
            $width = $element['width'] ?? 80;
            $height = $element['height'] ?? 80;
        @endphp
        @if (file_exists($imgPath))
            <img src="{{ $imgPath }}" style="width: {{ $width }}px; height: {{ $height }}px; object-fit: contain; margin: 4px 0;" />
        @endif
    @endif
@endif
