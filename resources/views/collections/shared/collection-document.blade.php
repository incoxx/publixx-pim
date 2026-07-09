<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>{{ $header['name'] ?? 'Angebot' }}</title>
    <meta name="robots" content="noindex, nofollow">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { background: #e5e7eb; }
        body {
            font-family: 'Segoe UI', Roboto, Arial, sans-serif;
            font-size: 13px;
            color: #1f2937;
            padding: 24px 12px 48px;
        }

        {{-- .page ist bewusst dieselbe mm-Breite wie im PDF (siehe pdf/default.blade.php) --
             gleiches Layout, nur als "schwebende" Karte statt Druckseite dargestellt. --}}
        .page {
            position: relative;
            width: 210mm;
            max-width: 100%;
            min-height: 297mm;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,.1), 0 4px 16px rgba(0,0,0,.08);
            padding: 15mm;
            font-size: 10pt;
        }

        .header-canvas { position: relative; overflow: hidden; }
        .element { position: absolute; overflow: hidden; word-wrap: break-word; }

        .doc-meta { margin: 4mm 0 6mm 0; }
        .doc-meta .title { font-size: 16pt; font-weight: bold; }
        .doc-meta .ref { color: #4b5563; }

        .address-block { white-space: pre-line; margin-bottom: 4mm; }
        .header-attributes { margin-bottom: 6mm; }
        .header-attribute { margin-bottom: 1mm; }
        .header-attribute .label { font-weight: bold; }

        table.items { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table.items th {
            background: #f3f4f6; color: #374151; text-align: left;
            border: 1px solid #e5e7eb; padding: 2mm; font-size: 8.5pt;
        }
        table.items td { border: 1px solid #e5e7eb; padding: 2mm; vertical-align: top; }
        table.items td.num { text-align: right; }
        table.items tr.line-text td { border-top: none; color: #4b5563; font-size: 8.5pt; padding-top: 0; }
        table.items tr.line-text .label { font-weight: bold; }

        .totals { width: 100%; margin-top: 4mm; }
        .totals td { padding: 1mm 2mm; }
        .totals .grand-total { font-weight: bold; font-size: 11pt; border-top: 1px solid #1f2937; }

        .browser-footer {
            max-width: 210mm;
            margin: 8px auto 0;
            text-align: center;
            font-size: 11px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    @include('collections.pdf._document', ['header' => $header, 'items' => $items, 'grandTotal' => $grandTotal])

    <div class="browser-footer">Erstellt mit anyPIM</div>
</body>
</html>
