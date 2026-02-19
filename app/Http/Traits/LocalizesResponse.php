<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait LocalizesResponse
{
    /**
     * Extract requested languages from ?lang= or Accept-Language header.
     *
     * @return string[] e.g. ['de','en']
     */
    protected function getRequestedLanguages(Request $request): array
    {
        $lang = $request->query('lang') ?? $request->header('Accept-Language', 'de');

        $languages = array_map('trim', explode(',', $lang));
        $languages = array_filter($languages, fn (string $l) => preg_match('/^[a-z]{2}$/', $l));

        return !empty($languages) ? array_values($languages) : ['de'];
    }

    /**
     * Get the primary (first) requested language.
     */
    protected function getPrimaryLanguage(Request $request): string
    {
        return $this->getRequestedLanguages($request)[0];
    }

    /**
     * Resolve a localized name field from a model that has name_de, name_en, name_json columns.
     */
    protected function getLocalizedName(mixed $entity, string $lang): string
    {
        if ($lang === 'de') {
            return $entity->name_de;
        }

        if ($lang === 'en') {
            return $entity->name_en ?? $entity->name_de;
        }

        $json = $entity->name_json ?? [];
        if (is_string($json)) {
            $json = json_decode($json, true) ?? [];
        }

        return $json[$lang] ?? $entity->name_en ?? $entity->name_de;
    }

    /**
     * Add eager-loading constraint for product_attribute_values to only load relevant languages.
     */
    protected function constrainAttributeValuesForLanguages(Builder $query, array $languages): Builder
    {
        return $query->with(['attributeValues' => function ($q) use ($languages) {
            $q->where(function ($sub) use ($languages) {
                $sub->whereNull('language')
                    ->orWhereIn('language', $languages);
            });
        }]);
    }
}
