<?php

declare(strict_types=1);

namespace App\Services\Ecommerce\Adapters;

use App\Models\EcommerceOrder;
use Illuminate\Support\Facades\Http;

class RestApiAdapter implements OrderAdapterInterface
{
    public function __construct(private readonly array $config) {}

    public function getType(): string
    {
        return 'rest_api';
    }

    public function dispatch(EcommerceOrder $order): array
    {
        $url     = $this->resolveUrl($order);
        $method  = strtolower($this->config['method'] ?? 'post');
        $headers = $this->config['headers'] ?? [];
        $payload = $this->buildPayload($order);

        try {
            $start    = microtime(true);
            $response = Http::withHeaders($headers)->$method($url, $payload);
            $duration = round((microtime(true) - $start) * 1000);

            return [
                'type'          => 'rest_api',
                'success'       => $response->successful(),
                'url'           => $url,
                'status_code'   => $response->status(),
                'duration_ms'   => $duration,
                'response_body' => mb_substr($response->body(), 0, 2000),
                'sent_at'       => now()->toISOString(),
            ];
        } catch (\Throwable $e) {
            return [
                'type'    => 'rest_api',
                'success' => false,
                'url'     => $url,
                'error'   => $e->getMessage(),
                'sent_at' => now()->toISOString(),
            ];
        }
    }

    private function resolveUrl(EcommerceOrder $order): string
    {
        $url = $this->config['url'] ?? '';

        return str_replace(['{order_id}', '{order_number}'], [$order->id, $order->order_number], $url);
    }

    private function buildPayload(EcommerceOrder $order): array
    {
        $template = $this->config['payload_template'] ?? null;

        if ($template) {
            $json = str_replace(
                ['{order_id}', '{order_number}'],
                [$order->id, $order->order_number],
                $template
            );
            $decoded = json_decode($json, true);

            return $decoded ?? $order->toArray();
        }

        return [
            'order_id'        => $order->id,
            'order_number'    => $order->order_number,
            'status'          => $order->status,
            'items'           => $order->items_snapshot,
            'total_amount'    => $order->total_amount,
            'currency'        => $order->currency,
            'billing_address' => $order->billing_address,
            'customer_notes'  => $order->customer_notes,
            'submitted_at'    => $order->submitted_at?->toISOString(),
        ];
    }
}
