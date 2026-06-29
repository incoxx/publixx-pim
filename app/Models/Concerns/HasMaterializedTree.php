<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Wiederverwendbare Materialized-Path-Tree-Logik.
 *
 * Erwartet auf dem Model die Spalten `parent_node_id`, `path`, `depth`.
 * Pfadformat: "/{id}/" für Wurzeln, "{parent.path}{id}/" für Kinder.
 *
 * Hält den Tree-Algorithmus an genau einer Stelle (Anti-Duplikat). Neue
 * Tree-Modelle (z. B. NavigationNode) nutzen diesen Trait; bestehende
 * Hierarchie-Knoten können später darauf umgestellt werden.
 */
trait HasMaterializedTree
{
    /**
     * Pfad + Tiefe aus dem aktuellen parent_node_id berechnen und speichern.
     * Voraussetzung: das Model ist bereits persistiert (hat eine ID).
     */
    public function refreshTreePath(): void
    {
        if ($this->parent_node_id) {
            $parent = static::query()->findOrFail($this->parent_node_id);
            $this->depth = $parent->depth + 1;
            $this->path = $parent->path . $this->getKey() . '/';
        } else {
            $this->depth = 0;
            $this->path = '/' . $this->getKey() . '/';
        }

        $this->save();
    }

    /**
     * Nach einem Move alle Nachfahren-Pfade von $oldPath auf den neuen Pfad
     * umschreiben (Batch). UUIDs sind global eindeutig → LIKE-Match ist sicher.
     */
    public function reattachSubtree(string $oldPath): void
    {
        $newPath = $this->path;
        if ($oldPath === $newPath) {
            return;
        }

        $descendants = static::query()
            ->where('path', 'like', $oldPath . '%')
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->get();

        foreach ($descendants as $child) {
            $child->path = str_replace($oldPath, $newPath, $child->path);
            $child->depth = substr_count(trim($child->path, '/'), '/');
            $child->save();
        }
    }
}
