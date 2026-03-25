<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PdfDocument;
use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;
use Illuminate\Support\Facades\Log;

class TypesenseService
{
    private ?Client $client = null;
    private ?string $searchFields = null;

    private function client(): Client
    {
        if ($this->client === null) {
            $this->client = new Client([
                'api_key' => config('typesense.api_key'),
                'nodes' => [
                    [
                        'host' => config('typesense.host'),
                        'port' => config('typesense.port'),
                        'protocol' => config('typesense.protocol'),
                    ],
                ],
                'connection_timeout_seconds' => 5,
            ]);
        }

        return $this->client;
    }

    /**
     * Collection-Schema für pdf_pages.
     */
    public function getCollectionSchema(): array
    {
        return [
            'name' => 'pdf_pages',
            'fields' => [
                ['name' => 'pdf_document_id', 'type' => 'string', 'facet' => true],
                ['name' => 'media_id', 'type' => 'string'],
                ['name' => 'page_number', 'type' => 'int32'],
                ['name' => 'text', 'type' => 'string'],
                ['name' => 'title', 'type' => 'string'],
                ['name' => 'file_name', 'type' => 'string', 'optional' => true],
                ['name' => 'description', 'type' => 'string', 'optional' => true],
                ['name' => 'folder_name', 'type' => 'string', 'optional' => true],
                ['name' => 'page_count', 'type' => 'int32', 'optional' => true],
                ['name' => 'language', 'type' => 'string', 'facet' => true, 'optional' => true],
            ],
        ];
    }

    /**
     * Collection erstellen (drop + recreate wenn $force = true).
     */
    public function createCollection(bool $force = false): void
    {
        if ($force) {
            try {
                $this->client()->collections['pdf_pages']->delete();
            } catch (ObjectNotFound) {
                // Collection existiert nicht — ok
            }
        } else {
            try {
                $this->client()->collections['pdf_pages']->retrieve();
                return; // Collection existiert bereits
            } catch (ObjectNotFound) {
                // Collection existiert nicht — wird unten erstellt
            }
        }

        $this->client()->collections->create($this->getCollectionSchema());
    }

    /**
     * Alle Seiten eines PdfDocuments in Typesense indexieren.
     */
    public function upsertPages(PdfDocument $document): void
    {
        $media = $document->media;
        $title = $media->title_de ?? $media->title_en ?? $media->file_name ?? '';
        $fileName = $media->file_name ?? '';
        $description = $media->description_de ?? $media->description_en ?? '';
        $folderName = $media->assetFolder?->name_de ?? '';

        // Seiten in Chunks indexieren um Memory bei großen PDFs zu begrenzen
        $document->pages()->orderBy('page_number')->chunk(50, function ($pages) use ($document, $title, $fileName, $description, $folderName) {
            $documents = [];
            foreach ($pages as $page) {
                $documents[] = [
                    'id' => $document->id . '-' . $page->page_number,
                    'pdf_document_id' => $document->id,
                    'media_id' => $document->media_id,
                    'page_number' => $page->page_number,
                    'text' => $page->extracted_text ?? '',
                    'title' => $title,
                    'file_name' => $fileName,
                    'description' => $description,
                    'folder_name' => $folderName,
                    'page_count' => $document->page_count ?? 0,
                ];
            }

            if (!empty($documents)) {
                try {
                    $this->client()->collections['pdf_pages']->documents->import(
                        $documents,
                        ['action' => 'upsert']
                    );
                } catch (\Throwable $e) {
                    Log::warning('TypesenseService: Failed to upsert pages chunk', [
                        'pdf_document_id' => $document->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * Alle Seiten eines Dokuments aus dem Index entfernen.
     */
    public function deletePagesForDocument(string $documentId): void
    {
        try {
            $this->client()->collections['pdf_pages']->documents->delete([
                'filter_by' => 'pdf_document_id:=' . $documentId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('TypesenseService: Failed to delete pages', [
                'pdf_document_id' => $documentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Felder und Gewichte für die Suche ermitteln.
     * Prüft welche Felder in der Collection existieren (Kompatibilität mit altem Schema).
     */
    private function resolveSearchFields(): string
    {
        if ($this->searchFields !== null) {
            return $this->searchFields;
        }

        try {
            $collection = $this->client()->collections['pdf_pages']->retrieve();
            $fieldNames = array_column($collection['fields'] ?? [], 'name');

            // Volle Gewichtung wenn alle Felder vorhanden
            if (in_array('file_name', $fieldNames)) {
                $this->searchFields = 'title,file_name,text';
            } else {
                // Fallback: alte Collection ohne file_name
                $this->searchFields = 'title,text';
            }
        } catch (\Throwable) {
            $this->searchFields = 'title,text';
        }

        return $this->searchFields;
    }

    /**
     * Volltextsuche über alle PDF-Seiten — gruppiert nach Dokument, gewichtet nach Feld.
     *
     * Gewichtung: title (3x) > file_name (2x) > text (1x)
     * Ergebnisse werden nach pdf_document_id gruppiert (max 3 Seiten-Snippets pro Dokument).
     */
    public function search(string $query, ?string $language = null, int $perPage = 20, int $page = 1): array
    {
        $searchParams = [
            'q' => $query,
            'query_by' => $this->resolveSearchFields(),
            'highlight_full_fields' => 'text',
            'highlight_start_tag' => '<mark>',
            'highlight_end_tag' => '</mark>',
            'per_page' => $perPage,
            'page' => $page,
            'snippet_threshold' => 60,
            'group_by' => 'pdf_document_id',
            'group_limit' => 3,
        ];

        // Language-Filter nur anwenden wenn Feld tatsächlich befüllt wird (aktuell nicht)
        // if ($language) {
        //     $searchParams['filter_by'] = 'language:=' . $language;
        // }

        try {
            $result = $this->client()->collections['pdf_pages']->documents->search($searchParams);

            $groups = [];
            foreach ($result['grouped_hits'] ?? [] as $group) {
                $groupHits = [];
                $firstDoc = null;

                foreach ($group['hits'] ?? [] as $hit) {
                    $doc = $hit['document'];
                    $firstDoc ??= $doc;

                    // Bestes Snippet aus den Highlights extrahieren
                    $snippet = '';
                    foreach ($hit['highlights'] ?? [] as $h) {
                        if ($h['field'] === 'text') {
                            $snippet = $h['snippet'] ?? '';
                            break;
                        }
                    }

                    $groupHits[] = [
                        'page_number' => $doc['page_number'],
                        'snippet' => $snippet,
                        'score' => $hit['text_match_info']['score'] ?? null,
                    ];
                }

                if (!$firstDoc) {
                    continue;
                }

                $groups[] = [
                    'pdf_document_id' => $firstDoc['pdf_document_id'],
                    'media_id' => $firstDoc['media_id'],
                    'title' => $firstDoc['title'],
                    'file_name' => $firstDoc['file_name'] ?? '',
                    'page_count' => $firstDoc['page_count'] ?? 0,
                    'total_hits' => $group['found'] ?? count($groupHits),
                    'hits' => $groupHits,
                ];
            }

            return [
                'groups' => $groups,
                'found' => $result['found'] ?? 0,
                'found_docs' => count($groups),
                'page' => $page,
                'per_page' => $perPage,
            ];
        } catch (\Throwable $e) {
            Log::error('TypesenseService: Search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [
                'groups' => [],
                'found' => 0,
                'found_docs' => 0,
                'page' => $page,
                'per_page' => $perPage,
            ];
        }
    }
}
