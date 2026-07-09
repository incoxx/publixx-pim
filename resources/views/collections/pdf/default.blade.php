<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-Regular.ttf') }}') format('truetype'); font-weight: normal; font-style: normal; }
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-Bold.ttf') }}') format('truetype'); font-weight: bold; font-style: normal; }
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-Italic.ttf') }}') format('truetype'); font-weight: normal; font-style: italic; }
        @font-face { font-family: 'Noto Sans'; src: url('{{ storage_path('fonts/NotoSans-BoldItalic.ttf') }}') format('truetype'); font-weight: bold; font-style: italic; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Noto Sans', DejaVu Sans, sans-serif; font-size: 10pt; color: #1f2937; }
        .page { position: relative; width: 210mm; }

        {{-- overflow:hidden -- default_render_template_id ist nur als nullable|uuid validiert,
             eine fuer die ganzseitige Produkt-Vorlage gedachte PdfTemplate (Elemente bei
             y=100mm/200mm) darf nicht in Adressblock/Kopftext/Positionstabelle hineinbluten. --}}
        .header-canvas { position: relative; overflow: hidden; }
        .element { position: absolute; overflow: hidden; word-wrap: break-word; }

        .doc-meta { margin: 4mm 0 6mm 0; }
        .doc-meta .title { font-size: 16pt; font-weight: bold; }
        .doc-meta .ref { color: #4b5563; }

        .address-block { white-space: pre-line; margin-bottom: 4mm; }
        .cover-text { margin-bottom: 4mm; }
        .payment-terms { margin-bottom: 6mm; }
        .payment-terms .label { font-weight: bold; }

        table.items { width: 100%; border-collapse: collapse; font-size: 9pt; }
        table.items th {
            background: #f3f4f6; color: #374151; text-align: left;
            border: 1px solid #e5e7eb; padding: 2mm; font-size: 8.5pt;
        }
        table.items td { border: 1px solid #e5e7eb; padding: 2mm; vertical-align: top; }
        table.items td.num { text-align: right; }
        table.items tr.line-text td { border-top: none; color: #4b5563; font-size: 8.5pt; padding-top: 0; }

        .totals { width: 100%; margin-top: 4mm; }
        .totals td { padding: 1mm 2mm; }
        .totals .grand-total { font-weight: bold; font-size: 11pt; border-top: 1px solid #1f2937; }

        .footer { position: fixed; bottom: -15mm; left: 0; right: 0; font-size: 8pt; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    {{-- Kopf-/Branding-Bereich, Positionstabelle und Summen: gemeinsames Markup mit dem
         Browser-Freigabe-Link (shared/collection-document.blade.php), siehe pdf/_document.blade.php. --}}
    @include('collections.pdf._document', ['header' => $header, 'items' => $items, 'grandTotal' => $grandTotal])

    <div class="footer">Seite {PAGE_NUM} von {PAGE_COUNT}</div>
</body>
</html>
