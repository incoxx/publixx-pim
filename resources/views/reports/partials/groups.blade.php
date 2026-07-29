@foreach ($groups as $group)
    @php
        $definition = $group['definition'] ?? [];
        $headerClass = match($depth) {
            0 => 'group-header',
            1 => 'group-header-l2',
            default => 'group-header-l3',
        };
    @endphp

    <div class="group-block">
        {{-- Group Header --}}
        @if (!empty($definition['header']['elements']))
            @foreach ($definition['header']['elements'] as $element)
                @include('reports.partials.element', ['element' => $element, 'context' => [
                    'group.value' => $group['value'] ?? '',
                    'group.label' => $group['label'] ?? '',
                    'count' => $group['count'] ?? 0,
                ]])
            @endforeach
        @elseif ($group['value'])
            <div class="{{ $headerClass }}">{{ $group['value'] }}</div>
        @endif

        {{-- Products (Detail Rows) --}}
        @if (!empty($group['products']))
            @php
                $detailElements = $definition['detail']['elements'] ?? [];
                $columnTypes = ['field', 'attribute', 'price', 'relation', 'categories'];
                $fieldElements = array_values(array_filter($detailElements, fn($e) => in_array($e['type'], $columnTypes)));
                $detailLayout = $definition['detailLayout'] ?? 'table';
                $tStyle = $definition['tableStyle'] ?? [];
                $showBorders = $tStyle['showBorders'] ?? true;
                $borderColor = $tStyle['borderColor'] ?? '#e5e7eb';
                $alternateRowBg = $tStyle['alternateRowBg'] ?? true;
                $alternateRowColor = $tStyle['alternateRowColor'] ?? '#f9fafb';
                $headerBg = $tStyle['headerBg'] ?? '#f3f4f6';
                $headerColor = $tStyle['headerColor'] ?? '#374151';
                $compact = $tStyle['compact'] ?? false;
                $columnWidths = $tStyle['columnWidths'] ?? [];
                $cellPad = $compact ? '2px 6px' : '3px 8px';
                $borderCss = $showBorders ? "1px solid {$borderColor}" : 'none';
                $renderer = app(\App\Services\Report\ElementRenderer::class);
            @endphp

            @if ($detailLayout === 'list')
                {{-- List layout: Label → Value per row --}}
                @foreach ($group['products'] as $product)
                    <table style="width: 100%; border-collapse: collapse; margin-bottom: 10px;">
                        @foreach ($fieldElements as $rowIdx => $col)
                            @php
                                $rowBg = ($alternateRowBg && $rowIdx % 2 === 1) ? "background: {$alternateRowColor};" : '';
                                $label = $col['label'] ?? $col['field'] ?? '';
                                $val = $renderer->resolveColumnValue($product, $col, $lang);
                            @endphp
                            <tr style="{{ $rowBg }}">
                                <td style="padding: {{ $cellPad }}; border-bottom: {{ $borderCss }}; font-weight: bold; width: 35%; font-size: 9px; color: #6b7280;">{{ $label }}</td>
                                <td style="padding: {{ $cellPad }}; border-bottom: {{ $borderCss }}; font-size: 10px;">{{ $val }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endforeach
            @elseif (count($fieldElements) >= 2)
                {{-- Table layout --}}
                <table style="width: 100%; border-collapse: collapse; margin-bottom: 8px;">
                    <thead>
                        <tr>
                            @foreach ($fieldElements as $col)
                                @php $colWidth = $columnWidths[$col['id'] ?? ''] ?? ''; @endphp
                                <th style="background: {{ $headerBg }}; color: {{ $headerColor }}; text-align: left; padding: {{ $cellPad }}; border-bottom: {{ $borderCss }}; font-size: 9px;{{ $colWidth ? " width: {$colWidth};" : '' }}">
                                    {{ $col['label'] ?? $col['field'] ?? '' }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['products'] as $rowIdx => $product)
                            @php $rowBg = ($alternateRowBg && $rowIdx % 2 === 1) ? "background: {$alternateRowColor};" : ''; @endphp
                            <tr style="{{ $rowBg }}">
                                @foreach ($fieldElements as $col)
                                    <td style="padding: {{ $cellPad }}; border-bottom: {{ $borderCss }}; font-size: 10px;">
                                        {{ $renderer->resolveColumnValue($product, $col, $lang) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                {{-- Block layout (single field) --}}
                @foreach ($group['products'] as $product)
                    @foreach ($detailElements as $element)
                        @include('reports.partials.detail-element', ['element' => $element, 'product' => $product, 'lang' => $lang])
                    @endforeach
                    <div style="margin-bottom: 8px;"></div>
                @endforeach
            @endif
        @endif

        {{-- Subgroups --}}
        @if (!empty($group['subgroups']))
            @include('reports.partials.groups', ['groups' => $group['subgroups'], 'template' => $template, 'lang' => $lang, 'depth' => $depth + 1])
        @endif

        {{-- Group Footer --}}
        @if (!empty($definition['footer']['elements']))
            <div class="group-footer">
                @foreach ($definition['footer']['elements'] as $element)
                    @if ($element['type'] === 'counter')
                        {{ str_replace('{count}', (string) ($group['count'] ?? 0), $element['format'] ?? '{count}') }}
                    @elseif ($element['type'] === 'separator')
                        <div class="separator"></div>
                    @else
                        @include('reports.partials.element', ['element' => $element, 'context' => ['count' => $group['count'] ?? 0]])
                    @endif
                @endforeach
            </div>
        @endif

        {{-- Page break --}}
        @if (!empty($definition['pageBreak']) && !$loop->last)
            <div class="page-break"></div>
        @endif
    </div>
@endforeach
