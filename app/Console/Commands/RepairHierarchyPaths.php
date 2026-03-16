<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HierarchyNode;
use Illuminate\Console\Command;

class RepairHierarchyPaths extends Command
{
    protected $signature = 'hierarchy:repair-paths {--dry-run : Show changes without applying them}';

    protected $description = 'Repair hierarchy node paths to use UUID-based materialized paths';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $fixed = 0;
        $total = 0;

        $nodes = HierarchyNode::orderBy('depth', 'asc')->get();
        $total = $nodes->count();

        $this->info("Checking {$total} hierarchy nodes...");

        foreach ($nodes as $node) {
            $correctPath = $this->buildCorrectPath($node);

            if ($node->path !== $correctPath) {
                if ($dryRun) {
                    $this->line("  [DRY-RUN] {$node->name_de}: '{$node->path}' → '{$correctPath}'");
                } else {
                    $node->update(['path' => $correctPath]);
                    $this->line("  [FIXED] {$node->name_de}: → '{$correctPath}'");
                }
                $fixed++;
            }
        }

        if ($fixed === 0) {
            $this->info('All paths are correct. Nothing to repair.');
        } else {
            $action = $dryRun ? 'would be fixed' : 'fixed';
            $this->info("{$fixed} of {$total} paths {$action}.");
        }

        return self::SUCCESS;
    }

    private function buildCorrectPath(HierarchyNode $node): string
    {
        if ($node->parent_node_id === null) {
            return "/{$node->id}/";
        }

        $parent = HierarchyNode::find($node->parent_node_id);
        if (!$parent) {
            return "/{$node->id}/";
        }

        // Ensure parent path is correct first (processed in depth order)
        return "{$parent->path}{$node->id}/";
    }
}
