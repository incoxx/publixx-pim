<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Models\EcommerceCart;
use App\Models\EcommerceCartItem;
use App\Models\EcommerceOrder;
use App\Services\Ecommerce\Adapters\AdapterFactory;
use Illuminate\Support\Facades\DB;

class OrderService
{
    // Warenkorb als Bestellung abschicken
    public function submitCart(EcommerceCart $cart, array $formData): EcommerceOrder
    {
        $cart->load(['items.product', 'cartType.priceType', 'paymentType']);

        if ($cart->items->isEmpty()) {
            throw new \RuntimeException('Warenkorb ist leer.');
        }

        $itemsSnapshot = $this->buildItemsSnapshot($cart);
        $totalAmount   = $this->calculateTotal($itemsSnapshot);
        $currency      = $itemsSnapshot[0]['currency'] ?? 'EUR';

        $order = DB::transaction(function () use ($cart, $formData, $itemsSnapshot, $totalAmount, $currency) {
            $order = EcommerceOrder::create([
                'order_number'    => $this->generateOrderNumber(),
                'cart_id'         => $cart->id,
                'cart_type_id'    => $cart->cart_type_id,
                'session_id'      => $cart->session_id,
                'user_id'         => $cart->user_id,
                'status'          => 'pending',
                'items_snapshot'  => $itemsSnapshot,
                'billing_address' => $formData['billing_address'] ?? null,
                'shipping_address' => $formData['shipping_address'] ?? null,
                'payment_type_id' => $formData['payment_type_id'] ?? null,
                'total_amount'    => $totalAmount,
                'currency'        => $currency,
                'customer_notes'  => $formData['notes'] ?? null,
                'submitted_at'    => now(),
            ]);

            $cart->update(['status' => 'submitted', 'submitted_at' => now()]);

            return $order;
        });

        // Adapter außerhalb der Transaktion (keine Blockierung bei langsamem HTTP/SMTP)
        $this->dispatchAdapter($order);

        return $order->fresh(['cartType', 'paymentType']);
    }

    private function buildItemsSnapshot(EcommerceCart $cart): array
    {
        return $cart->items->map(function (EcommerceCartItem $item) use ($cart) {
            $product   = $item->product;
            $unitPrice = $item->unit_price !== null ? (float) $item->unit_price : null;
            $quantity  = (float) $item->quantity;

            return [
                'product_id' => $item->product_id,
                'sku'        => $product?->sku,
                'name'       => $product?->name,
                'quantity'   => $quantity,
                'unit_price' => $unitPrice,
                'line_price' => $unitPrice !== null ? round($unitPrice * $quantity, 2) : null,
                'currency'   => $item->currency ?? 'EUR',
                'note'       => $item->note,
            ];
        })->values()->all();
    }

    private function calculateTotal(array $items): ?float
    {
        $hasPrice = collect($items)->filter(fn ($i) => $i['line_price'] !== null)->isNotEmpty();

        if (! $hasPrice) {
            return null;
        }

        return round(collect($items)->sum('line_price'), 2);
    }

    private function dispatchAdapter(EcommerceOrder $order): void
    {
        $cartType = $order->cartType;

        if (! $cartType || $cartType->adapter_type === 'none') {
            $order->update(['status' => 'confirmed']);

            return;
        }

        try {
            $adapter = AdapterFactory::make($cartType);
            $log     = $adapter->dispatch($order);

            $order->update([
                'status'      => $log['success'] ? 'confirmed' : 'failed',
                'adapter_log' => $log,
            ]);
        } catch (\Throwable $e) {
            $order->update([
                'status'      => 'failed',
                'adapter_log' => ['error' => $e->getMessage(), 'type' => $cartType->adapter_type],
            ]);
        }
    }

    // Adapter manuell erneut auslösen (Admin-Funktion)
    public function retryDispatch(EcommerceOrder $order): EcommerceOrder
    {
        $order->update(['status' => 'pending']);
        $this->dispatchAdapter($order);

        return $order->fresh();
    }

    private function generateOrderNumber(): string
    {
        $year = now()->year;
        // Sequenz für das aktuelle Jahr ermitteln
        $count = EcommerceOrder::whereYear('created_at', $year)->count() + 1;

        return sprintf('ORD-%d-%06d', $year, $count);
    }
}
