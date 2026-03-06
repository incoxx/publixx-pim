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
                $fieldElements = array_filter($detailElements, fn($e) => in_array($e['type'], ['field', 'attribute']));
            @endphp

            @if (count($fieldElements) >= 2)
                {{-- Table layout --}}
                <table>
                    <thead>
                        <tr>
                            @foreach ($fieldElements as $col)
                                <th>{{ $col['label'] ?? $col['field'] ?? '' }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($group['products'] as $product)
                            <tr>
                                @foreach ($fieldElements as $col)
                                    <td>
                                        @if ($col['type'] === 'field')
                                            {{ app(\App\Services\Report\ElementRenderer::class)->resolveFieldValue($product, $col['field'] ?? '', $lang) }}
                                        @elseif ($col['type'] === 'attribute')
                                            @php
                                                $resolved = app(\App\Services\Report\ElementRenderer::class)->resolveAttributeValue($product, $col['attributeId'] ?? '', $lang);
                                                $parts = [];
                                                if (($col['showValue'] ?? true) && $resolved['value']) $parts[] = $resolved['value'];
                                                if (($col['showUnit'] ?? true) && $resolved['unit']) $parts[] = $resolved['unit'];
                                            @endphp
                                            {{ implode(' ', $parts) }}
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                {{-- Block layout --}}
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
