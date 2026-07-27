<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttributeViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'technical_name' => $this->technical_name,
            'name_de' => $this->name_de,
            'name_en' => $this->name_en,
            'name_json' => $this->name_json,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_write_protected' => $this->is_write_protected,
            'show_as_tab' => $this->show_as_tab,
            // Optionale Produkttyp-Einschränkung: leer/null = für alle Produkttypen gültig.
            // Wird im Produkteditor gespiegelt, um Sicht-Tab und Attribut-Filter zu filtern.
            'allowed_product_type_ids' => $this->allowed_product_type_ids ?? [],
            // Synthetischer RoleTabPermission-Tab-Key — vom Frontend direkt zu übernehmen,
            // statt das "attribute-view:"-Präfix in mehreren Dateien erneut zu bilden.
            'tab_key' => $this->tabKey(),
            'attributes' => $this->whenLoaded('attributes', function () {
                // Explizit nach Pivot-sort_order sortieren (die Relation selbst liefert
                // bewusst keine eigene Reihenfolge, siehe AttributeView::attributes()).
                return $this->attributes
                    ->sortBy(fn ($attribute) => $attribute->pivot->sort_order ?? 0)
                    ->values()
                    ->map(function ($attribute) {
                        return array_merge(
                            (new AttributeResource($attribute))->resolve(),
                            [
                                'sort_order' => $attribute->pivot->sort_order ?? 0,
                                // Read-only-Override speziell für diese Sicht — unabhängig vom
                                // globalen Attribute::is_readonly-Flag.
                                'is_readonly_in_view' => (bool) ($attribute->pivot->is_readonly ?? false),
                            ]
                        );
                    });
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
