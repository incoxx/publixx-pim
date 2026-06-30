<?php

declare(strict_types=1);

namespace App\Services\Ecommerce;

use App\Models\EcommerceCart;
use App\Models\EcommerceCartItem;
use App\Models\EcommerceCartType;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use Illuminate\Http\Request;

class CartService
{
    private const CART_EXPIRY_DAYS = 30;

    // SHA-256 des pim_session-Cookies — kein Klartext in der DB
    public function resolveSessionId(Request $request): string
    {
        $sessionId = $request->cookie('pim_session') ?? session()->getId();

        return hash('sha256', $sessionId);
    }

    // Aktiven Warenkorb für Session+Typ finden oder neu anlegen
    public function getOrCreateCart(Request $request, EcommerceCartType $cartType): EcommerceCart
    {
        $sessionHash = $this->resolveSessionId($request);

        $cart = EcommerceCart::where('cart_type_id', $cartType->id)
            ->where('session_id', $sessionHash)
            ->active()
            ->first();

        if (! $cart) {
            $cart = EcommerceCart::create([
                'cart_type_id' => $cartType->id,
                'session_id'   => $sessionHash,
                'user_id'      => $request->user()?->id,
                'status'       => 'active',
                'expires_at'   => now()->addDays(self::CART_EXPIRY_DAYS),
            ]);
        } elseif ($cart->user_id === null && $request->user() !== null) {
            // Anonymen Cart mit eingeloggtem Benutzer verknüpfen
            $cart->update(['user_id' => $request->user()->id]);
        }

        return $cart;
    }

    // Produkt hinzufügen (bei Duplikat: Menge addieren)
    public function addItem(
        EcommerceCart $cart,
        string $productId,
        float $quantity = 1.0
    ): EcommerceCartItem {
        $cartType = $cart->cartType;

        // Wishlist: immer Menge 1
        if ($cartType->type === 'wishlist') {
            $quantity = 1.0;
        }

        $priceData = $this->resolvePrice($productId, $cartType);

        $maxSort = EcommerceCartItem::where('cart_id', $cart->id)->max('sort_order') ?? 0;

        $item = EcommerceCartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $productId],
            [
                'quantity'       => $quantity,
                'unit_price'     => $priceData['amount'] ?? null,
                'currency'       => $priceData['currency'] ?? 'EUR',
                'price_snapshot' => $priceData,
                'sort_order'     => $maxSort + 10,
            ]
        );

        // Ablaufdatum verlängern bei Aktivität
        $cart->update(['expires_at' => now()->addDays(self::CART_EXPIRY_DAYS)]);

        return $item;
    }

    // Menge eines Items aktualisieren
    public function updateItem(EcommerceCartItem $item, float $quantity): void
    {
        $cartType = $item->cart->cartType;

        if ($cartType->type === 'wishlist') {
            return;
        }

        // Preissnapshot bei Mengenänderung aktualisieren (skalenabhängige Preise)
        $priceData = $this->resolvePrice($item->product_id, $cartType);

        $item->update([
            'quantity'       => max(0.001, $quantity),
            'unit_price'     => $priceData['amount'] ?? $item->unit_price,
            'price_snapshot' => $priceData,
        ]);
    }

    // Item aus dem Warenkorb entfernen
    public function removeItem(EcommerceCartItem $item): void
    {
        $item->delete();
    }

    // Warenkorb leeren
    public function clearCart(EcommerceCart $cart): void
    {
        $cart->items()->delete();
    }

    // Warenkorb mit Items und berechneter Summe zurückgeben
    public function getCartData(EcommerceCart $cart): array
    {
        $cart->load(['cartType.priceType', 'items.product', 'paymentType']);

        $items = $cart->items->map(function (EcommerceCartItem $item) use ($cart) {
            $cartType  = $cart->cartType;
            $product   = $item->product;
            $unitPrice = null;

            if ($cartType->show_prices && $cartType->price_type_id) {
                $priceData = $this->resolvePrice($item->product_id, $cartType);
                $unitPrice = $priceData['amount'] ?? null;
                // Snapshot in der DB aktuell halten
                if ($unitPrice !== null && $unitPrice != $item->unit_price) {
                    $item->update([
                        'unit_price'     => $unitPrice,
                        'price_snapshot' => $priceData,
                    ]);
                }
            }

            return [
                'id'          => $item->id,
                'product_id'  => $item->product_id,
                'sku'         => $product?->sku,
                'name'        => $product?->name,
                'quantity'    => (float) $item->quantity,
                'unit_price'  => $unitPrice !== null ? (float) $unitPrice : null,
                'currency'    => $item->currency ?? 'EUR',
                'line_price'  => $unitPrice !== null
                    ? round((float) $unitPrice * (float) $item->quantity, 2)
                    : null,
                'note'        => $item->note,
                'sort_order'  => $item->sort_order,
                'added_at'    => $item->added_at?->toISOString(),
            ];
        });

        $total = null;
        if ($cart->cartType->show_total) {
            $total = $items->sum('line_price');
        }

        return [
            'id'           => $cart->id,
            'cart_type'    => [
                'id'             => $cart->cartType->id,
                'technical_name' => $cart->cartType->technical_name,
                'name_de'        => $cart->cartType->name_de,
                'type'           => $cart->cartType->type,
                'show_prices'    => $cart->cartType->show_prices,
                'show_quantity'  => $cart->cartType->show_quantity,
                'show_total'     => $cart->cartType->show_total,
                'allow_checkout' => $cart->cartType->allow_checkout,
            ],
            'items'        => $items->values(),
            'item_count'   => $items->count(),
            'total_amount' => $total,
            'currency'     => $items->first()['currency'] ?? 'EUR',
            'billing_address'  => $cart->billing_address,
            'shipping_address' => $cart->shipping_address,
            'payment_type'     => $cart->paymentType?->only(['id', 'technical_name', 'name_de']),
            'notes'        => $cart->notes,
            'expires_at'   => $cart->expires_at?->toISOString(),
        ];
    }

    // Gast-Cart nach dem Login mit dem Benutzer-Account mergen
    public function mergeGuestCart(string $sessionHash, User $user): void
    {
        $guestCarts = EcommerceCart::where('session_id', $sessionHash)
            ->whereNull('user_id')
            ->active()
            ->get();

        foreach ($guestCarts as $guestCart) {
            // Prüfen ob für den Benutzer bereits ein Cart dieses Typs existiert
            $userCart = EcommerceCart::where('cart_type_id', $guestCart->cart_type_id)
                ->where('user_id', $user->id)
                ->active()
                ->first();

            if (! $userCart) {
                // Einfach den Gast-Cart dem Benutzer zuweisen
                $guestCart->update(['user_id' => $user->id]);
                continue;
            }

            // Items mergen: Gast-Items in den User-Cart übertragen
            foreach ($guestCart->items as $guestItem) {
                $existingItem = EcommerceCartItem::where('cart_id', $userCart->id)
                    ->where('product_id', $guestItem->product_id)
                    ->first();

                if ($existingItem) {
                    // Menge addieren (nur bei ecommerce-Typ sinnvoll)
                    if ($guestCart->cartType->type === 'ecommerce') {
                        $existingItem->increment('quantity', (float) $guestItem->quantity);
                    }
                } else {
                    EcommerceCartItem::create([
                        'cart_id'        => $userCart->id,
                        'product_id'     => $guestItem->product_id,
                        'quantity'       => $guestItem->quantity,
                        'unit_price'     => $guestItem->unit_price,
                        'currency'       => $guestItem->currency,
                        'price_snapshot' => $guestItem->price_snapshot,
                        'note'           => $guestItem->note,
                        'sort_order'     => $guestItem->sort_order,
                    ]);
                }
            }

            $guestCart->update(['status' => 'abandoned']);
        }
    }

    // Aktuellen Live-Preis für ein Produkt aus dem konfigurierten Preistyp ermitteln
    private function resolvePrice(string $productId, EcommerceCartType $cartType): array
    {
        if (! $cartType->price_type_id || ! $cartType->show_prices) {
            return [];
        }

        $price = ProductPrice::where('product_id', $productId)
            ->where('price_type_id', $cartType->price_type_id)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', now());
            })
            ->orderBy('scale_from')
            ->first();

        if (! $price) {
            return [];
        }

        return [
            'amount'        => (float) $price->amount,
            'currency'      => $price->currency ?? 'EUR',
            'price_type_id' => $price->price_type_id,
            'valid_at'      => now()->toISOString(),
        ];
    }
}
