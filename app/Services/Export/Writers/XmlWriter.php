<?php

declare(strict_types=1);

namespace App\Services\Export\Writers;

use Symfony\Component\HttpFoundation\StreamedResponse;
use XMLWriter;

class XmlWriter
{
    public function write(array $data, string $fileName): StreamedResponse
    {
        $fullName = $fileName . '.xml';

        return new StreamedResponse(function () use ($data) {
            $xml = new \XMLWriter();
            $xml->openURI('php://output');
            $xml->startDocument('1.0', 'UTF-8');
            $xml->setIndent(true);
            $xml->setIndentString('  ');

            $xml->startElement('export');
            $xml->writeAttribute('generated', now()->toIso8601String());

            foreach ($data['products'] ?? [] as $product) {
                $xml->startElement('product');
                $xml->writeElement('sku', $product['sku'] ?? '');
                $xml->writeElement('name', $product['name'] ?? '');
                $xml->writeElement('status', $product['status'] ?? '');
                $xml->writeElement('ean', $product['ean'] ?? '');
                $xml->writeElement('product_type', $product['product_type'] ?? '');

                if (!empty($product['attributes'])) {
                    $xml->startElement('attributes');
                    foreach ($product['attributes'] as $attr) {
                        $xml->startElement('attribute');
                        $xml->writeElement('technical_name', $attr['attribute'] ?? '');
                        $xml->writeElement('name', $attr['attribute_name'] ?? '');
                        $xml->writeElement('value', (string) ($attr['value'] ?? ''));
                        $xml->writeElement('language', $attr['language'] ?? '');
                        $xml->endElement();
                    }
                    $xml->endElement();
                }

                if (!empty($product['prices'])) {
                    $xml->startElement('prices');
                    foreach ($product['prices'] as $price) {
                        $xml->startElement('price');
                        $xml->writeElement('type', $price['price_type'] ?? '');
                        $xml->writeElement('amount', (string) ($price['amount'] ?? ''));
                        $xml->writeElement('currency', $price['currency'] ?? '');
                        $xml->writeElement('scale_from', (string) ($price['scale_from'] ?? ''));
                        $xml->writeElement('valid_from', $price['valid_from'] ?? '');
                        $xml->writeElement('valid_to', $price['valid_to'] ?? '');
                        $xml->endElement();
                    }
                    $xml->endElement();
                }

                if (!empty($product['relations'])) {
                    $xml->startElement('relations');
                    foreach ($product['relations'] as $rel) {
                        $xml->startElement('relation');
                        $xml->writeElement('target_sku', $rel['target_sku'] ?? '');
                        $xml->writeElement('type', $rel['relation_type'] ?? '');
                        $xml->writeElement('position', (string) ($rel['position'] ?? ''));
                        $xml->endElement();
                    }
                    $xml->endElement();
                }

                if (!empty($product['media'])) {
                    $xml->startElement('media');
                    foreach ($product['media'] as $m) {
                        $xml->startElement('item');
                        $xml->writeElement('file_name', $m['file_name'] ?? '');
                        $xml->writeElement('usage_type', $m['usage_type'] ?? '');
                        $xml->writeElement('position', (string) ($m['position'] ?? ''));
                        $xml->endElement();
                    }
                    $xml->endElement();
                }

                if (!empty($product['variants'])) {
                    $xml->startElement('variants');
                    foreach ($product['variants'] as $v) {
                        $xml->startElement('variant');
                        $xml->writeElement('sku', $v['sku'] ?? '');
                        $xml->writeElement('name', $v['name'] ?? '');
                        $xml->writeElement('ean', $v['ean'] ?? '');
                        $xml->writeElement('status', $v['status'] ?? '');
                        $xml->endElement();
                    }
                    $xml->endElement();
                }

                $xml->endElement(); // product
            }

            $xml->endElement(); // export
            $xml->endDocument();
            $xml->flush();
        }, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$fullName}\"",
            'Cache-Control' => 'no-store',
        ]);
    }
}
