<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContentPage;
use App\Models\ContentSection;
use App\Models\ContentType;
use App\Models\EcommerceAddressType;
use App\Models\EcommerceCartType;
use App\Models\EcommerceOrder;
use App\Models\EcommercePaymentType;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\SectionType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * e-Commerce-Showcase für das SmartOne-Portfolio.
 *
 * Legt die vollständige e-Commerce-Grundkonfiguration an:
 * - 3 Warenkorbarten (Merkliste, PDF-Anfrage, Warenkorb)
 * - 2 Adressarten (Rechnungs- und Lieferadresse)
 * - 4 Zahlungsarten (Vorkasse, Rechnung, Kreditkarte, PayPal)
 * - SectionType "ecommerce_cart" (falls noch nicht vorhanden)
 * - Content-Integration: ecommerce_cart in ContentType + SmartOne-Seite
 * - 6 realistische Demo-Bestellungen auf SmartOne-Produkten
 *
 * Idempotent: verwendet technical_name als Upsert-Schlüssel.
 *
 * Aufruf: php artisan db:seed --class=Database\\Seeders\\EcommerceSmartOneSeeder
 */
class EcommerceSmartOneSeeder extends Seeder
{
    public function run(): void
    {
        $listPriceTypeId = PriceType::where('technical_name', 'list_price')->value('id');

        $this->seedCartTypes($listPriceTypeId);
        $this->seedAddressTypes();
        $this->seedPaymentTypes();
        $this->seedSectionType();
        $this->seedContentIntegration();
        $this->seedDemoOrders();

        $this->command?->info('e-Commerce-Showcase: Warenkorbarten, Adressarten, Zahlungsarten, Content-Integration und Demo-Bestellungen angelegt.');
    }

    // ─── 1. Warenkorbarten ────────────────────────────────────────────────────

    private function seedCartTypes(?string $listPriceTypeId): void
    {
        $types = [
            [
                'technical_name' => 'merkliste',
                'name_de'        => 'Merkliste',
                'name_en'        => 'Wishlist',
                'type'           => 'wishlist',
                'price_type_id'  => null,
                'show_prices'    => false,
                'show_quantity'  => false,
                'show_total'     => false,
                'allow_checkout' => false,
                'adapter_type'   => 'none',
                'adapter_config' => null,
                'sort_order'     => 10,
            ],
            [
                'technical_name' => 'pdf_anfrage',
                'name_de'        => 'PDF-Anfrage',
                'name_en'        => 'PDF Request',
                'type'           => 'pdf_list',
                'price_type_id'  => $listPriceTypeId,
                'show_prices'    => true,
                'show_quantity'  => true,
                'show_total'     => false,
                'allow_checkout' => true,
                'adapter_type'   => 'email',
                'adapter_config' => [
                    'to'         => 'anfrage@smartone.example',
                    'subject_de' => 'Neue Produktanfrage über SmartOne-Katalog',
                ],
                'sort_order'     => 20,
            ],
            [
                'technical_name' => 'warenkorb',
                'name_de'        => 'Warenkorb',
                'name_en'        => 'Shopping Cart',
                'type'           => 'ecommerce',
                'price_type_id'  => $listPriceTypeId,
                'show_prices'    => true,
                'show_quantity'  => true,
                'show_total'     => true,
                'allow_checkout' => true,
                'adapter_type'   => 'email',
                'adapter_config' => [
                    'to'         => 'bestellung@smartone.example',
                    'subject_de' => 'Neue Bestellung – SmartOne Online-Shop',
                ],
                'sort_order'     => 30,
            ],
        ];

        foreach ($types as $t) {
            EcommerceCartType::updateOrCreate(
                ['technical_name' => $t['technical_name']],
                array_merge($t, ['is_active' => true]),
            );
        }
    }

    // ─── 2. Adressarten ──────────────────────────────────────────────────────

    private function seedAddressTypes(): void
    {
        $billing = [
            ['key' => 'company',    'label_de' => 'Firma',        'type' => 'text',  'required' => false, 'sort_order' => 1],
            ['key' => 'first_name', 'label_de' => 'Vorname',      'type' => 'text',  'required' => true,  'sort_order' => 2],
            ['key' => 'last_name',  'label_de' => 'Nachname',     'type' => 'text',  'required' => true,  'sort_order' => 3],
            ['key' => 'street',     'label_de' => 'Straße + Nr.', 'type' => 'text',  'required' => true,  'sort_order' => 4],
            ['key' => 'zip',        'label_de' => 'PLZ',          'type' => 'text',  'required' => true,  'sort_order' => 5],
            ['key' => 'city',       'label_de' => 'Ort',          'type' => 'text',  'required' => true,  'sort_order' => 6],
            ['key' => 'country',    'label_de' => 'Land',         'type' => 'text',  'required' => false, 'sort_order' => 7],
            ['key' => 'email',      'label_de' => 'E-Mail',       'type' => 'email', 'required' => true,  'sort_order' => 8],
            ['key' => 'phone',      'label_de' => 'Telefon',      'type' => 'phone', 'required' => false, 'sort_order' => 9],
            ['key' => 'vat_id',     'label_de' => 'USt-IdNr.',    'type' => 'text',  'required' => false, 'sort_order' => 10],
        ];

        $shipping = array_values(array_filter($billing, fn ($f) => !in_array($f['key'], ['email', 'vat_id'])));
        foreach ($shipping as &$f) {
            $f['required'] = in_array($f['key'], ['first_name', 'last_name', 'street', 'zip', 'city']);
        }
        unset($f);

        EcommerceAddressType::updateOrCreate(
            ['technical_name' => 'billing'],
            [
                'name_de'      => 'Rechnungsadresse',
                'name_en'      => 'Billing Address',
                'role'         => 'billing',
                'field_schema' => $billing,
                'is_active'    => true,
                'sort_order'   => 10,
            ],
        );

        EcommerceAddressType::updateOrCreate(
            ['technical_name' => 'shipping'],
            [
                'name_de'      => 'Lieferadresse',
                'name_en'      => 'Shipping Address',
                'role'         => 'shipping',
                'field_schema' => $shipping,
                'is_active'    => true,
                'sort_order'   => 20,
            ],
        );
    }

    // ─── 3. Zahlungsarten ────────────────────────────────────────────────────

    private function seedPaymentTypes(): void
    {
        $types = [
            [
                'technical_name' => 'vorkasse',
                'name_de'        => 'Vorkasse',
                'name_en'        => 'Prepayment',
                'description_de' => 'Zahlung per Überweisung vor Versand. Artikel wird nach Zahlungseingang versendet.',
                'icon'           => 'Landmark',
                'sort_order'     => 10,
            ],
            [
                'technical_name' => 'rechnung',
                'name_de'        => 'Kauf auf Rechnung',
                'name_en'        => 'Invoice',
                'description_de' => 'Zahlung innerhalb von 14 Tagen nach Erhalt der Ware. Nur für Geschäftskunden.',
                'icon'           => 'FileText',
                'sort_order'     => 20,
            ],
            [
                'technical_name' => 'kreditkarte',
                'name_de'        => 'Kreditkarte',
                'name_en'        => 'Credit Card',
                'description_de' => 'Visa, Mastercard und American Express werden akzeptiert.',
                'icon'           => 'CreditCard',
                'sort_order'     => 30,
            ],
            [
                'technical_name' => 'paypal',
                'name_de'        => 'PayPal',
                'name_en'        => 'PayPal',
                'description_de' => 'Schnell und sicher mit Ihrem PayPal-Konto bezahlen.',
                'icon'           => 'Wallet',
                'sort_order'     => 40,
            ],
        ];

        foreach ($types as $t) {
            EcommercePaymentType::updateOrCreate(
                ['technical_name' => $t['technical_name']],
                array_merge($t, ['is_active' => true]),
            );
        }
    }

    // ─── 4. SectionType "ecommerce_cart" ─────────────────────────────────────

    private function seedSectionType(): void
    {
        SectionType::updateOrCreate(
            ['technical_name' => 'ecommerce_cart'],
            [
                'name_de'            => 'Warenkorb-Widget',
                'name_en'            => 'Cart Widget',
                'icon'               => 'ShoppingCart',
                'category'           => 'commerce',
                'preview_component'  => 'SectionEcommerceCart',
                'is_system'          => true,
                'is_active'          => true,
                'sort_order'         => 200,
                'schema'             => ['fields' => [
                    ['key' => 'cart_type',   'label' => 'Warenkorbtyp (technical_name)', 'type' => 'String'],
                    ['key' => 'show_header', 'label' => 'Überschrift anzeigen',          'type' => 'Boolean', 'default' => true],
                    ['key' => 'header_text', 'label' => 'Überschrift',                   'type' => 'String', 'translatable' => true],
                ]],
            ],
        );
    }

    // ─── 5. Content-Integration ──────────────────────────────────────────────
    //
    // a) ContentType "smartone-landing" bekommt ecommerce_cart als erlaubten
    //    Sektionstyp, damit Redakteure den Warenkorb-Widget auf der Seite platzieren
    //    können.
    //
    // b) Auf der SmartOne-Landingpage werden drei ecommerce_cart-Sektionen
    //    eingefügt (Merkliste, PDF-Anfrage, Warenkorb), falls sie noch nicht
    //    vorhanden sind. So ist der Warenkorb sofort sichtbar ohne manuellen
    //    Eingriff im CMS.

    private function seedContentIntegration(): void
    {
        // a) ContentType erlaubt ecommerce_cart
        $ct = ContentType::where('technical_name', 'smartone-landing')->first();
        if ($ct) {
            $allowed = $ct->allowed_section_types ?? [];
            if (!in_array('ecommerce_cart', $allowed, true)) {
                $ct->update(['allowed_section_types' => array_merge($allowed, ['ecommerce_cart'])]);
            }
        }

        // b) Sektionen auf der SmartOne-Seite
        $page = ContentPage::where('slug', 'smartone')->first();
        $stId = SectionType::where('technical_name', 'ecommerce_cart')->value('id');

        if (!$page || !$stId) {
            $this->command?->warn(
                'SmartOne-Seite oder SectionType "ecommerce_cart" nicht gefunden – Content-Integration übersprungen.'
            );
            return;
        }

        $cartSections = [
            [
                'cart_type'   => 'warenkorb',
                'header_text' => 'Warenkorb',
                'sort_offset' => 0,
            ],
            [
                'cart_type'   => 'merkliste',
                'header_text' => 'Meine Merkliste',
                'sort_offset' => 1,
            ],
            [
                'cart_type'   => 'pdf_anfrage',
                'header_text' => 'Anfrage als PDF',
                'sort_offset' => 2,
            ],
        ];

        // Höchsten bestehenden sort_order ermitteln und dahinter einfügen
        $maxSort = $page->sections()->max('sort_order') ?? 0;

        foreach ($cartSections as $def) {
            $alreadyExists = ContentSection::where('content_page_id', $page->id)
                ->where('section_type_id', $stId)
                ->whereJsonContains('values_json->_->cart_type', $def['cart_type'])
                ->exists();

            if ($alreadyExists) {
                continue;
            }

            ContentSection::create([
                'content_page_id' => $page->id,
                'section_type_id' => $stId,
                'parent_section_id' => null,
                'sort_order' => $maxSort + 10 + $def['sort_offset'],
                'is_visible' => true,
                'values_json' => [
                    '_'  => ['cart_type' => $def['cart_type'], 'show_header' => true],
                    'de' => ['header_text' => $def['header_text']],
                ],
            ]);
        }

        $this->command?->info('Content-Integration: ecommerce_cart-Sektionen auf SmartOne-Seite angelegt.');
    }

    // ─── 6. Demo-Bestellungen ─────────────────────────────────────────────────

    private function seedDemoOrders(): void
    {
        $cartType = EcommerceCartType::where('technical_name', 'warenkorb')->first();
        $pdfType  = EcommerceCartType::where('technical_name', 'pdf_anfrage')->first();

        if (!$cartType && !$pdfType) {
            $this->command?->warn('Keine Warenkorbarten gefunden – Demo-Bestellungen übersprungen.');
            return;
        }

        $paymentVorkasse  = EcommercePaymentType::where('technical_name', 'vorkasse')->value('id');
        $paymentRechnung  = EcommercePaymentType::where('technical_name', 'rechnung')->value('id');
        $paymentPayPal    = EcommercePaymentType::where('technical_name', 'paypal')->value('id');

        // Produkte der SmartOne-Linie auflösen
        $products = Product::whereIn('sku', [
            '10001', '10002', '10003', '10005',   // Smartphones
            '20001', '20002', '20011', '20021',   // Zubehör
            '30001', '30002',                      // Ersatzteile
        ])->get()->keyBy('sku');

        if ($products->isEmpty()) {
            $this->command?->warn('Keine SmartOne-Produkte gefunden – Demo-Bestellungen übersprungen. Zuerst DemoProductSeeder ausführen.');
            return;
        }

        $orders = $this->orderDefinitions($cartType, $pdfType, $paymentVorkasse, $paymentRechnung, $paymentPayPal);

        DB::transaction(function () use ($orders, $products) {
            foreach ($orders as $def) {
                // Bereits vorhanden? Überspringen.
                if (EcommerceOrder::where('order_number', $def['order_number'])->exists()) {
                    continue;
                }

                $snapshot = $this->buildSnapshot($def['items'], $products, $def['cart_type']);

                EcommerceOrder::create([
                    'order_number'    => $def['order_number'],
                    'cart_type_id'    => $def['cart_type']->id,
                    'session_id'      => hash('sha256', 'demo-session-' . $def['order_number']),
                    'status'          => $def['status'],
                    'items_snapshot'  => $snapshot['items'],
                    'billing_address' => $def['billing'],
                    'shipping_address'=> $def['shipping'] ?? $def['billing'],
                    'payment_type_id' => $def['payment_type_id'] ?? null,
                    'total_amount'    => $snapshot['total'],
                    'currency'        => 'EUR',
                    'customer_notes'  => $def['notes'] ?? null,
                    'adapter_log'     => $def['adapter_log'] ?? null,
                    'submitted_at'    => $def['submitted_at'],
                ]);
            }
        });
    }

    /**
     * @return array<int,array{
     *   order_number: string, cart_type: EcommerceCartType,
     *   status: string, billing: array, items: array,
     *   payment_type_id: ?string, submitted_at: string
     * }>
     */
    private function orderDefinitions(
        ?EcommerceCartType $cartType,
        ?EcommerceCartType $pdfType,
        ?string $paymentVorkasse,
        ?string $paymentRechnung,
        ?string $paymentPayPal,
    ): array {
        $defs = [];

        if ($cartType) {
            $defs[] = [
                'order_number' => 'ORD-2026-000001',
                'cart_type'    => $cartType,
                'status'       => 'confirmed',
                'billing'      => [
                    'first_name' => 'Sophie',   'last_name' => 'Wagner',
                    'street'     => 'Musterstraße 12', 'zip' => '80331', 'city' => 'München',
                    'email'      => 'sophie.wagner@example.de', 'phone' => '+49 89 1234567',
                ],
                'items'           => [['sku' => '10005', 'qty' => 1], ['sku' => '20001', 'qty' => 1]],
                'payment_type_id' => $paymentPayPal,
                'submitted_at'    => '2026-06-01 09:14:22',
                'adapter_log'     => ['success' => true, 'status_code' => 200, 'response_body' => 'OK'],
            ];

            $defs[] = [
                'order_number' => 'ORD-2026-000002',
                'cart_type'    => $cartType,
                'status'       => 'pending',
                'billing'      => [
                    'first_name' => 'Thomas', 'last_name' => 'Bauer',
                    'street'     => 'Hauptstr. 5', 'zip' => '10115', 'city' => 'Berlin',
                    'email'      => 'thomas.bauer@example.de',
                ],
                'shipping'        => [
                    'first_name' => 'Thomas', 'last_name' => 'Bauer',
                    'street'     => 'Lieferweg 99', 'zip' => '10178', 'city' => 'Berlin',
                ],
                'items'           => [['sku' => '10002', 'qty' => 1], ['sku' => '20011', 'qty' => 2]],
                'payment_type_id' => $paymentVorkasse,
                'submitted_at'    => '2026-06-12 14:03:55',
                'notes'           => 'Bitte separat verpacken.',
                'adapter_log'     => ['success' => true, 'status_code' => 200, 'response_body' => 'Mail sent'],
            ];

            $defs[] = [
                'order_number' => 'ORD-2026-000003',
                'cart_type'    => $cartType,
                'status'       => 'confirmed',
                'billing'      => [
                    'company'    => 'TechLogistik GmbH',
                    'first_name' => 'Jan', 'last_name' => 'Hofmann',
                    'street'     => 'Industriepark 3', 'zip' => '60549', 'city' => 'Frankfurt',
                    'email'      => 'einkauf@techlogistik.example', 'vat_id' => 'DE123456789',
                ],
                'items'           => [
                    ['sku' => '10001', 'qty' => 3],
                    ['sku' => '10003', 'qty' => 2],
                    ['sku' => '20021', 'qty' => 5],
                ],
                'payment_type_id' => $paymentRechnung,
                'submitted_at'    => '2026-06-18 10:55:00',
                'adapter_log'     => ['success' => true, 'status_code' => 200, 'response_body' => 'Mail sent'],
            ];

            $defs[] = [
                'order_number' => 'ORD-2026-000004',
                'cart_type'    => $cartType,
                'status'       => 'failed',
                'billing'      => [
                    'first_name' => 'Marie', 'last_name' => 'Schmidt',
                    'street'     => 'Ringweg 7', 'zip' => '70173', 'city' => 'Stuttgart',
                    'email'      => 'marie.schmidt@example.de',
                ],
                'items'           => [['sku' => '30001', 'qty' => 1], ['sku' => '30002', 'qty' => 1]],
                'payment_type_id' => $paymentPayPal,
                'submitted_at'    => '2026-06-25 17:42:10',
                'adapter_log'     => [
                    'success'       => false,
                    'status_code'   => 503,
                    'response_body' => 'Service Unavailable',
                ],
            ];
        }

        if ($pdfType) {
            $defs[] = [
                'order_number' => 'ORD-2026-000005',
                'cart_type'    => $pdfType,
                'status'       => 'confirmed',
                'billing'      => [
                    'company'    => 'Stadtwerke Musterstadt',
                    'first_name' => 'Klaus', 'last_name' => 'Meier',
                    'street'     => 'Rathausplatz 1', 'zip' => '50667', 'city' => 'Köln',
                    'email'      => 'einkauf@stadtwerke-musterstadt.example',
                ],
                'items'           => [
                    ['sku' => '10001', 'qty' => 10],
                    ['sku' => '20001', 'qty' => 10],
                ],
                'payment_type_id' => null,
                'submitted_at'    => '2026-06-20 08:30:00',
                'notes'           => 'Sammelbestellung für Außendienst-Mitarbeiter.',
                'adapter_log'     => ['success' => true, 'status_code' => 200, 'response_body' => 'Mail sent'],
            ];

            $defs[] = [
                'order_number' => 'ORD-2026-000006',
                'cart_type'    => $pdfType,
                'status'       => 'pending',
                'billing'      => [
                    'first_name' => 'Anna', 'last_name' => 'Krause',
                    'street'     => 'Bergstraße 22', 'zip' => '01069', 'city' => 'Dresden',
                    'email'      => 'a.krause@example.de',
                ],
                'items'           => [
                    ['sku' => '10002', 'qty' => 1],
                    ['sku' => '20002', 'qty' => 1],
                    ['sku' => '20011', 'qty' => 1],
                ],
                'payment_type_id' => null,
                'submitted_at'    => '2026-06-28 11:17:44',
                'adapter_log'     => ['success' => true, 'status_code' => 200, 'response_body' => 'Mail sent'],
            ];
        }

        return $defs;
    }

    /**
     * Baut einen Bestell-Snapshot aus SKU-Definitionen und den aufgelösten Produkten.
     *
     * @param  array<array{sku: string, qty: int|float}>  $items
     * @param  \Illuminate\Support\Collection             $products  (keyed by sku)
     * @return array{items: array, total: float}
     */
    private function buildSnapshot(array $items, $products, EcommerceCartType $cartType): array
    {
        $snapshot = [];
        $total    = 0.0;

        foreach ($items as $line) {
            $product = $products->get($line['sku']);
            if (!$product) {
                continue;
            }

            // Preis aus dem konfigurierten PriceType holen (nullable)
            $unitPrice = null;
            if ($cartType->price_type_id) {
                $unitPrice = $product->prices()
                    ->where('price_type_id', $cartType->price_type_id)
                    ->value('amount');
            }

            $qty       = (float) $line['qty'];
            $linePrice = $unitPrice !== null ? round($unitPrice * $qty, 2) : null;
            $total    += (float) ($linePrice ?? 0);

            $snapshot[] = [
                'product_id' => $product->id,
                'sku'        => $product->sku,
                'name'       => $product->name ?? $product->sku,
                'quantity'   => $qty,
                'unit_price' => $unitPrice,
                'line_price' => $linePrice,
                'currency'   => 'EUR',
            ];
        }

        return ['items' => $snapshot, 'total' => round($total, 2)];
    }
}
