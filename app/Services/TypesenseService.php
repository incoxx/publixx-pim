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
                ['name' => 'pdf_document_id', 'type' => 'string'],
                ['name' => 'media_id', 'type' => 'string'],
                ['name' => 'page_number', 'type' => 'int32'],
                ['name' => 'text', 'type' => 'string'],
                ['name' => 'title', 'type' => 'string'],
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

        $documents = [];
        foreach ($document->pages as $page) {
            $documents[] = [
                'id' => $document->id . '-' . $page->page_number,
                'pdf_document_id' => $document->id,
                'media_id' => $document->media_id,
                'page_number' => $page->page_number,
                'text' => $page->extracted_text ?? '',
                'title' => $title,
            ];
        }

        if (empty($documents)) {
            return;
        }

        try {
            $this->client()->collections['pdf_pages']->documents->import(
                $documents,
                ['action' => 'upsert']
            );
        } catch (\Throwable $e) {
            Log::warning('TypesenseService: Failed to upsert pages', [
                'pdf_document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }
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
     * Volltextsuche über alle PDF-Seiten.
     */
    public function search(string $query, ?string $language = null, int $perPage = 20, int $page = 1): array
    {
        $searchParams = [
            'q' => $query,
            'query_by' => 'text,title',
            'highlight_full_fields' => 'text',
            'highlight_start_tag' => '<mark>',
            'highlight_end_tag' => '</mark>',
            'per_page' => $perPage,
            'page' => $page,
            'snippet_threshold' => 60,
        ];

        if ($language) {
            $searchParams['filter_by'] = 'language:=' . $language;
        }

        try {
            $result = $this->client()->collections['pdf_pages']->documents->search($searchParams);

            $hits = [];
            foreach ($result['hits'] ?? [] as $hit) {
                $doc = $hit['document'];
                $highlights = $hit['highlights'] ?? [];
                $snippet = '';
                foreach ($highlights as $h) {
                    if ($h['field'] === 'text') {
                        $snippet = $h['snippet'] ?? '';
                        break;
                    }
                }

                $hits[] = [
                    'pdf_document_id' => $doc['pdf_document_id'],
                    'media_id' => $doc['media_id'],
                    'page_number' => $doc['page_number'],
                    'title' => $doc['title'],
                    'snippet' => $snippet,
                ];
            }

            return [
                'hits' => $hits,
                'found' => $result['found'] ?? 0,
                'page' => $page,
                'per_page' => $perPage,
            ];
        } catch (\Throwable $e) {
            Log::error('TypesenseService: Search failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return [
                'hits' => [],
                'found' => 0,
                'page' => $page,
                'per_page' => $perPage,
            ];
        }
    }
}
