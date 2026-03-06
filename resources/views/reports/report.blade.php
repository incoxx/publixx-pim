<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $options['title'] ?? 'Report' }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $template['style']['size'] ?? 10 }}px; color: #1f2937; line-height: 1.4; padding: 20px; }
        h1 { font-size: 18px; margin-bottom: 4px; color: #111827; }
        h2 { font-size: 15px; margin-bottom: 4px; color: #1e40af; }
        h3 { font-size: 13px; margin-bottom: 4px; color: #374151; }
        .subtitle { font-size: 11px; color: #6b7280; margin-bottom: 16px; }
        .group-block { margin-bottom: 16px; }
        .group-header {
            background: {{ $template['style']['primaryColor'] ?? '#2563eb' }};
            color: #fff; padding: 6px 10px;
            font-size: 13px; font-weight: bold; margin-bottom: 0;
        }
        .group-header-l2 {
            background: #dbeafe; color: #1e40af; padding: 4px 10px;
            font-size: 11px; font-weight: bold;
        }
        .group-header-l3 {
            background: #f0f9ff; color: #0369a1; padding: 3px 10px;
            font-size: 10px; font-weight: bold;
        }
        .group-footer {
            font-size: 9px; color: #6b7280; font-style: italic;
            padding: 4px 10px; border-top: 1px solid #e5e7eb; margin-bottom: 12px;
        }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th { background: #f3f4f6; text-align: left; padding: 3px 8px; font-size: 9px; border-bottom: 1px solid #d1d5db; }
        td { padding: 3px 8px; border-bottom: 1px solid #e5e7eb; font-size: 10px; }
        tr:nth-child(even) td { background: #f9fafb; }
        .field-label { font-weight: bold; width: 35%; background: #f3f4f6; }
        .separator { border-top: 1px solid #d1d5db; margin: 8px 0; }
        .page-break { page-break-after: always; }
        .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        .empty { color: #9ca3af; font-style: italic; }
        .element-text { margin-bottom: 4px; }
    </style>
</head>
<body>

{{-- Page Header --}}
@if (!empty($template['pageHeader']['elements']))
    @foreach ($template['pageHeader']['elements'] as $element)
        @include('reports.partials.element', ['element' => $element, 'context' => $options])
    @endforeach
@else
    <h1>{{ $options['title'] ?? 'Report' }}</h1>
    <div class="subtitle">{{ now()->format('d.m.Y H:i') }}</div>
@endif

{{-- Groups --}}
@include('reports.partials.groups', ['groups' => $groups, 'template' => $template, 'lang' => $lang, 'depth' => 0])

{{-- Page Footer --}}
<div class="footer">
    anyPIM &mdash; {{ $options['title'] ?? 'Report' }} &mdash; {{ now()->format('d.m.Y H:i') }}
</div>

</body>
</html>
