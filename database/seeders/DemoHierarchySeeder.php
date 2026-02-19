<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use App\Models\HierarchyNodeAttributeAssignment;
use Illuminate\Database\Seeder;

class DemoHierarchySeeder extends Seeder
{
    public function run(): void
    {
        // ----- Master Hierarchy -----
        $master = Hierarchy::firstOrCreate(
            ['technical_name' => 'master_elektrowerkzeuge'],
            [
                'name_de' => 'Elektrowerkzeuge',
                'name_en' => 'Power Tools',
                'hierarchy_type' => 'master',
                'description' => 'Master-Hierarchie für Elektrowerkzeuge-Sortiment',
            ]
        );

        // Level 0: Root
        $root = HierarchyNode::firstOrCreate(
            ['hierarchy_id' => $master->id, 'path' => '/'],
            [
                'name_de' => 'Elektrowerkzeuge',
                'name_en' => 'Power Tools',
                'depth' => 0,
                'sort_order' => 0,
            ]
        );

        // Level 1: Categories
        $bohren = $this->createNode($master, $root, 'Bohren & Schrauben', 'Drilling & Screwing', 10);
        $saegen = $this->createNode($master, $root, 'Sägen', 'Saws', 20);
        $schleifen = $this->createNode($master, $root, 'Schleifen & Polieren', 'Grinding & Polishing', 30);

        // Level 2: Sub-categories
        $akkubohrer = $this->createNode($master, $bohren, 'Akkubohrschrauber', 'Cordless Drill Drivers', 10);
        $schlagbohrer = $this->createNode($master, $bohren, 'Schlagbohrmaschinen', 'Hammer Drills', 20);
        $bohrstaender = $this->createNode($master, $bohren, 'Bohrständer', 'Drill Stands', 30);

        $stichsaege = $this->createNode($master, $saegen, 'Stichsägen', 'Jig Saws', 10);
        $kreissaege = $this->createNode($master, $saegen, 'Kreissägen', 'Circular Saws', 20);

        $winkelschleifer = $this->createNode($master, $schleifen, 'Winkelschleifer', 'Angle Grinders', 10);
        $bandschleifer = $this->createNode($master, $schleifen, 'Bandschleifer', 'Belt Sanders', 20);

        // ----- Output Hierarchy -----
        $output = Hierarchy::firstOrCreate(
            ['technical_name' => 'output_catalog_2025'],
            [
                'name_de' => 'Katalog 2025',
                'name_en' => 'Catalog 2025',
                'hierarchy_type' => 'output',
                'description' => 'Output-Hierarchie für Katalog 2025',
            ]
        );

        $outputRoot = HierarchyNode::firstOrCreate(
            ['hierarchy_id' => $output->id, 'path' => '/'],
            [
                'name_de' => 'Katalog 2025',
                'name_en' => 'Catalog 2025',
                'depth' => 0,
                'sort_order' => 0,
            ]
        );

        // ----- Assign attributes to hierarchy nodes -----
        $allAttrs = Attribute::whereIn('technical_name', [
            'product-name-str', 'product-description-str', 'product-weight-num',
            'product-color-sel', 'product-material-sel', 'product-norm-str',
        ])->get()->keyBy('technical_name');

        // Root gets basic attributes (inherited by all)
        $this->assignAttributes($root, [
            ['attr' => $allAttrs['product-name-str'] ?? null, 'collection' => 'Basisdaten', 'cSort' => 10, 'aSort' => 10],
            ['attr' => $allAttrs['product-description-str'] ?? null, 'collection' => 'Basisdaten', 'cSort' => 10, 'aSort' => 20],
            ['attr' => $allAttrs['product-weight-num'] ?? null, 'collection' => 'Technisch', 'cSort' => 20, 'aSort' => 10],
            ['attr' => $allAttrs['product-norm-str'] ?? null, 'collection' => 'Technisch', 'cSort' => 20, 'aSort' => 20],
        ]);

        // Bohren gets additional: color, material
        $this->assignAttributes($bohren, [
            ['attr' => $allAttrs['product-color-sel'] ?? null, 'collection' => 'Ausstattung', 'cSort' => 30, 'aSort' => 10],
            ['attr' => $allAttrs['product-material-sel'] ?? null, 'collection' => 'Ausstattung', 'cSort' => 30, 'aSort' => 20],
        ]);
    }

    private function createNode(Hierarchy $hierarchy, HierarchyNode $parent, string $nameDe, string $nameEn, int $sort): HierarchyNode
    {
        $path = $parent->path === '/' ? "/{$parent->id}/" : "{$parent->path}{$parent->id}/";

        return HierarchyNode::firstOrCreate(
            ['hierarchy_id' => $hierarchy->id, 'name_de' => $nameDe, 'parent_node_id' => $parent->id],
            [
                'name_en' => $nameEn,
                'path' => $path,
                'depth' => $parent->depth + 1,
                'sort_order' => $sort,
            ]
        );
    }

    private function assignAttributes(HierarchyNode $node, array $assignments): void
    {
        foreach ($assignments as $a) {
            if ($a['attr'] === null) {
                continue;
            }
            HierarchyNodeAttributeAssignment::firstOrCreate(
                ['hierarchy_node_id' => $node->id, 'attribute_id' => $a['attr']->id],
                [
                    'collection_name' => $a['collection'],
                    'collection_sort' => $a['cSort'],
                    'attribute_sort' => $a['aSort'],
                ]
            );
        }
    }
}
