<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeView;
use App\Models\CollectionType;
use Illuminate\Database\Seeder;

/**
 * Seeder fuer den vorkonfigurierten Collection-Typ "Angebot" (Brief §6/§8).
 *
 * Liefert per Seeder mindestens einen fertigen collection_type mit passenden
 * Attribut-Gruppen aus, damit der Wert von Collections sofort sichtbar ist
 * (bake, don't fry -- auf Produktebene diesmal fuer die Konfiguration selbst).
 *
 * Vollstaendig idempotent: kann beliebig oft ausgefuehrt werden.
 */
class DemoCollectionSeeder extends Seeder
{
    public function run(): void
    {
        // Interne/versteckte Attribute fuer die Import-Review-Queue (Phase 4) -- unabhaengig
        // vom Collection-Typ, daher hier global geseedet statt an "Angebot" gebunden.
        // is_internal/is_hidden: erscheinen in keiner normalen Attribut-UI/-Auswahl.
        $this->seedInternalAttribute('_import_match_status', 'Import-Match-Status', 'String');
        $this->seedInternalAttribute('_import_fuzzy_candidates', 'Import-Fuzzy-Kandidaten', 'String');

        $headerView = $this->seedAttributeView(
            'angebot-kopfdaten',
            'Angebot — Kopfdaten',
            10
        );
        $itemView = $this->seedAttributeView(
            'angebot-position',
            'Angebot — Position',
            20
        );

        $this->seedAttribute(
            'angebot-zahlungsbedingungen',
            'Zahlungsbedingungen',
            'String',
            ['collection'],
            $headerView,
            ['is_translatable' => true]
        );
        $this->seedAttribute(
            'angebot-kopftext',
            'Kopftext',
            'String',
            ['collection'],
            $headerView,
            ['is_translatable' => true, 'textarea_rows' => 6]
        );

        $this->seedAttribute(
            'angebot-rabatt',
            'Rabatt',
            'Number',
            ['collection_item'],
            $itemView,
            ['max_pre_decimal' => 3, 'max_post_decimal' => 2]
        );
        $this->seedAttribute(
            'angebot-positionstext',
            'Positionstext',
            'String',
            ['collection_item'],
            $itemView,
            ['is_translatable' => true, 'textarea_rows' => 2]
        );

        CollectionType::updateOrCreate(
            ['technical_name' => 'angebot'],
            [
                'name_de' => 'Angebot',
                'name_en' => 'Offer',
                'description' => 'Angebot fuer einen Empfaenger -- Positionen mit Preis, eingefroren beim Versand.',
                'icon' => 'file-text',
                'requires_organization' => true,
                // Ein Angebot ist ein rechtsverbindliches Dokument -- Positionen muessen vor
                // dem Versand eingefroren werden (kein Angebot mit "lebendigem" Preis).
                'requires_snapshot' => true,
                'allowed_export_formats' => ['pdf', 'xlsx', 'opentrans'],
                'default_attribute_groups' => [$headerView->technical_name],
                'default_item_attribute_groups' => [$itemView->technical_name],
                // 'list_price' ist der reale geseedete price_types.technical_name
                // (DemoAttributeSeeder) -- Grundlage fuer CollectionRenderService's
                // buildEnrichmentRules() (Phase 2).
                'default_price_type' => 'list_price',
                // Rabatt bleibt die einzige Einzelrollen-Zuordnung (geht in die Summe ein).
                // Positionstext/Zahlungsbedingungen/Kopftext werden automatisch angezeigt, weil
                // sie oben bereits header_view/item_view zugeordnet wurden (siehe
                // CollectionRenderService::resolveGroupDisplayValues()).
                'default_discount_attribute' => 'angebot-rabatt',
                'sort_order' => 10,
                'is_active' => true,
            ]
        );
    }

    private function seedInternalAttribute(string $technicalName, string $nameDe, string $dataType): void
    {
        Attribute::updateOrCreate(
            ['technical_name' => $technicalName],
            [
                'name_de' => $nameDe,
                'data_type' => $dataType,
                'applies_to' => ['collection_item'],
                'is_internal' => true,
                'is_hidden' => true,
                'status' => 'active',
            ]
        );
    }

    private function seedAttributeView(string $technicalName, string $nameDe, int $sortOrder): AttributeView
    {
        return AttributeView::updateOrCreate(
            ['technical_name' => $technicalName],
            ['name_de' => $nameDe, 'sort_order' => $sortOrder]
        );
    }

    private function seedAttribute(
        string $technicalName,
        string $nameDe,
        string $dataType,
        array $appliesTo,
        AttributeView $view,
        array $extra = []
    ): Attribute {
        $attribute = Attribute::updateOrCreate(
            ['technical_name' => $technicalName],
            array_merge([
                'name_de' => $nameDe,
                'data_type' => $dataType,
                'applies_to' => $appliesTo,
                'status' => 'active',
            ], $extra)
        );

        $attribute->attributeViews()->syncWithoutDetaching([$view->id]);

        return $attribute;
    }
}
