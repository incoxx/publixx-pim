<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\HierarchyNodeResource;
use App\Models\Hierarchy;
use App\Models\HierarchyNode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DebugController extends Controller
{
    /**
     * GET /debug/hierarchy-tree — diagnose why the tree view is empty (no auth required).
     */
    public function hierarchyTree(Request $request): JsonResponse
    {
        $hierarchies = Hierarchy::all(['id', 'technical_name', 'name_de', 'hierarchy_type']);

        $result = [];
        foreach ($hierarchies as $h) {
            $totalNodes = HierarchyNode::where('hierarchy_id', $h->id)->count();
            $rootNodes = HierarchyNode::where('hierarchy_id', $h->id)
                ->whereNull('parent_node_id')
                ->get(['id', 'name_de', 'depth', 'path', 'parent_node_id']);

            $sampleChildren = HierarchyNode::where('hierarchy_id', $h->id)
                ->whereNotNull('parent_node_id')
                ->limit(5)
                ->get(['id', 'name_de', 'depth', 'path', 'parent_node_id']);

            $result[] = [
                'hierarchy_id' => $h->id,
                'name' => $h->name_de,
                'technical_name' => $h->technical_name,
                'type' => $h->hierarchy_type,
                'total_nodes' => $totalNodes,
                'root_nodes_count' => $rootNodes->count(),
                'root_nodes' => $rootNodes->toArray(),
                'sample_children' => $sampleChildren->toArray(),
            ];
        }

        return response()->json($result, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    /**
     * GET /debug/simulate-tree/{hierarchyId} — run the EXACT same buildTree logic as the real endpoint (no auth).
     */
    public function simulateTree(Request $request, string $hierarchyId): JsonResponse
    {
        $hierarchy = Hierarchy::findOrFail($hierarchyId);

        $rootNodes = $hierarchy->nodes()
            ->whereNull('parent_node_id')
            ->with('children')
            ->orderBy('sort_order', 'asc')
            ->get();

        $errors = [];

        $buildTree = function ($nodes, int $currentDepth = 0) use (&$buildTree, &$errors) {
            return $nodes->map(function ($node) use (&$buildTree, $currentDepth, &$errors) {
                try {
                    $data = (new HierarchyNodeResource($node))->resolve();
                } catch (\Throwable $e) {
                    $errors[] = [
                        'node_id' => $node->id,
                        'node_name' => $node->name_de,
                        'error' => $e->getMessage(),
                        'file' => $e->getFile() . ':' . $e->getLine(),
                    ];
                    $data = ['id' => $node->id, 'name_de' => $node->name_de, '_error' => $e->getMessage()];
                }

                try {
                    $children = $node->children()->orderBy('sort_order', 'asc')->with('children')->get();
                    $data['children'] = $buildTree($children, $currentDepth + 1);
                } catch (\Throwable $e) {
                    $errors[] = [
                        'node_id' => $node->id,
                        'step' => 'children_load',
                        'error' => $e->getMessage(),
                    ];
                    $data['children'] = [];
                }

                return $data;
            });
        };

        try {
            $treeData = $buildTree($rootNodes);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'error' => $e->getMessage(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
            ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return response()->json([
            'status' => 'ok',
            'root_nodes_count' => $rootNodes->count(),
            'tree_data_count' => count($treeData),
            'errors' => $errors,
            'data' => $treeData,
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function logs(Request $request): Response
    {
        $channel = $request->query('channel', 'laravel');
        $lines = min((int) $request->query('lines', 500), 5000);

        $allowedChannels = ['laravel', 'import'];
        if (! in_array($channel, $allowedChannels, true)) {
            return response('Unknown channel: ' . $channel, 400)
                ->header('Content-Type', 'text/plain');
        }

        $path = storage_path("logs/{$channel}.log");

        if (! file_exists($path)) {
            return response("(empty — no log entries yet)\n", 200)
                ->header('Content-Type', 'text/plain');
        }

        // Read last N lines efficiently
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        $startLine = max(0, $totalLines - $lines);
        $output = [];
        $file->seek($startLine);

        while (! $file->eof()) {
            $output[] = $file->current();
            $file->next();
        }

        return response(implode('', $output))
            ->header('Content-Type', 'text/plain');
    }

    public function clearLogs(Request $request): Response
    {
        $channel = $request->query('channel', 'laravel');

        $allowedChannels = ['laravel', 'import'];
        if (! in_array($channel, $allowedChannels, true)) {
            return response('Unknown channel: ' . $channel, 400)
                ->header('Content-Type', 'text/plain');
        }

        $path = storage_path("logs/{$channel}.log");

        if (! file_exists($path)) {
            return response("Nothing to clear — no log file yet.\n", 200)
                ->header('Content-Type', 'text/plain');
        }

        file_put_contents($path, '');

        return response("Cleared: {$channel}.log")
            ->header('Content-Type', 'text/plain');
    }
}
