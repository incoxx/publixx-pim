<?php

declare(strict_types=1);

namespace App\Services\Ecommerce\Adapters;

use App\Models\EcommerceOrder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;

class EmailAdapter implements OrderAdapterInterface
{
    public function __construct(private readonly array $config) {}

    public function getType(): string
    {
        return 'email';
    }

    public function dispatch(EcommerceOrder $order): array
    {
        $to      = $this->config['to'] ?? config('mail.from.address');
        $cc      = $this->config['cc'] ?? null;
        $subject = $this->config['subject_de'] ?? "Neue Bestellung #{$order->order_number}";
        $body    = $this->renderBody($order);

        try {
            $mail = Mail::html($body, function ($message) use ($to, $cc, $subject) {
                $message->to($to)->subject($subject);
                if ($cc) {
                    $message->cc($cc);
                }
            });

            return [
                'type'    => 'email',
                'success' => true,
                'to'      => $to,
                'sent_at' => now()->toISOString(),
            ];
        } catch (\Throwable $e) {
            return [
                'type'    => 'email',
                'success' => false,
                'to'      => $to,
                'error'   => $e->getMessage(),
                'sent_at' => now()->toISOString(),
            ];
        }
    }

    private function renderBody(EcommerceOrder $order): string
    {
        $template = $this->config['body_template'] ?? null;

        if ($template) {
            try {
                return Blade::render($template, [
                    'order'    => $order,
                    'items'    => $order->items_snapshot,
                    'total'    => $order->total_amount,
                    'currency' => $order->currency,
                ]);
            } catch (\Throwable) {
                // Fallback auf Standard-Template bei Syntaxfehler
            }
        }

        return $this->defaultTemplate($order);
    }

    private function defaultTemplate(EcommerceOrder $order): string
    {
        $items = collect($order->items_snapshot)->map(function ($item) {
            $qty   = $item['quantity'] ?? 1;
            $price = isset($item['unit_price']) ? number_format((float) $item['unit_price'], 2) . ' ' . ($item['currency'] ?? 'EUR') : '–';

            return "<tr><td>{$item['sku']}</td><td>{$item['name']}</td><td>{$qty}</td><td>{$price}</td></tr>";
        })->join('');

        $total = $order->total_amount !== null
            ? number_format((float) $order->total_amount, 2) . ' ' . $order->currency
            : '–';

        return <<<HTML
        <h2>Neue Bestellung #{$order->order_number}</h2>
        <table border="1" cellpadding="4" style="border-collapse:collapse">
          <tr><th>SKU</th><th>Produkt</th><th>Menge</th><th>Preis</th></tr>
          {$items}
        </table>
        <p><strong>Gesamt: {$total}</strong></p>
        HTML;
    }
}
