<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ProductUpdated;
use App\Jobs\ExecuteExportJob;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductPrice;
use App\Models\ScheduledAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduledActionService
{
    public function executeDueActions(): array
    {
        $actions = ScheduledAction::due()->get();
        $activated = 0;
        $failed = 0;

        foreach ($actions as $action) {
            try {
                $this->executeAction($action);
                $activated++;
            } catch (\Throwable $e) {
                $failed++;
                Log::error('Scheduled action failed', [
                    'action_id' => $action->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return ['activated' => $activated, 'failed' => $failed];
    }

    public function executeAction(ScheduledAction $action): void
    {
        $action->update(['status' => 'processing']);

        try {
            match ($action->action_type) {
                'activate_product', 'deactivate_product' => $this->executeStatusChange($action),
                'price_change' => $this->executePriceChange($action),
                'data_change' => $this->executeDataChange($action),
                'export' => $this->executeExport($action),
                default => throw new \RuntimeException("Unknown action type: {$action->action_type}"),
            };

            $action->update([
                'status' => 'completed',
                'executed_at' => now(),
                'result_message' => 'Erfolgreich ausgeführt',
            ]);
        } catch (\Throwable $e) {
            $action->update([
                'status' => 'failed',
                'executed_at' => now(),
                'result_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function executeStatusChange(ScheduledAction $action): void
    {
        $payload = $action->payload;
        $targetStatus = $payload['target_status'] ?? ($action->action_type === 'activate_product' ? 'active' : 'inactive');

        $productIds = $this->resolveProductIds($action);

        DB::transaction(function () use ($productIds, $targetStatus) {
            foreach ($productIds as $productId) {
                $product = Product::findOrFail($productId);
                $product->update(['status' => $targetStatus]);
                event(new ProductUpdated($product));
            }
        });

        $action->update(['result_message' => count($productIds) . " Produkt(e) auf '{$targetStatus}' gesetzt"]);
    }

    private function executePriceChange(ScheduledAction $action): void
    {
        $payload = $action->payload;
        $prices = $payload['prices'] ?? [];
        $productIds = $this->resolveProductIds($action);

        DB::transaction(function () use ($productIds, $prices) {
            foreach ($productIds as $productId) {
                foreach ($prices as $priceData) {
                    ProductPrice::updateOrCreate(
                        [
                            'product_id' => $productId,
                            'price_type_id' => $priceData['price_type_id'],
                            'currency' => $priceData['currency'] ?? 'EUR',
                        ],
                        [
                            'amount' => $priceData['amount'],
                        ]
                    );
                }

                $product = Product::find($productId);
                if ($product) {
                    event(new ProductUpdated($product));
                }
            }
        });

        $action->update(['result_message' => count($productIds) . ' Produkt(e) Preise aktualisiert']);
    }

    private function executeDataChange(ScheduledAction $action): void
    {
        $payload = $action->payload;
        $attributes = $payload['attributes'] ?? [];
        $productIds = $this->resolveProductIds($action);

        DB::transaction(function () use ($productIds, $attributes) {
            foreach ($productIds as $productId) {
                foreach ($attributes as $attrData) {
                    ProductAttributeValue::updateOrCreate(
                        [
                            'product_id' => $productId,
                            'attribute_id' => $attrData['attribute_id'],
                            'language' => $attrData['language'] ?? null,
                        ],
                        [
                            'value_string' => $attrData['value_string'] ?? null,
                            'value_number' => $attrData['value_number'] ?? null,
                            'value_boolean' => $attrData['value_boolean'] ?? null,
                            'value_date' => $attrData['value_date'] ?? null,
                            'value_list_entry_id' => $attrData['value_list_entry_id'] ?? null,
                        ]
                    );
                }

                $product = Product::find($productId);
                if ($product) {
                    event(new ProductUpdated($product));
                }
            }
        });

        $action->update(['result_message' => count($productIds) . ' Produkt(e) Daten aktualisiert']);
    }

    private function executeExport(ScheduledAction $action): void
    {
        $payload = $action->payload;
        $exportJobId = $payload['export_job_id'] ?? null;

        if (!$exportJobId) {
            throw new \RuntimeException('Kein Export-Job angegeben');
        }

        ExecuteExportJob::dispatch($exportJobId);

        $action->update(['result_message' => 'Export-Job gestartet']);
    }

    private function resolveProductIds(ScheduledAction $action): array
    {
        if ($action->product_id) {
            return [$action->product_id];
        }

        return $action->product_ids ?? [];
    }
}
